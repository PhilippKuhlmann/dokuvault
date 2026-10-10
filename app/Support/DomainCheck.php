<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use NetDNS2\Client;
use NetDNS2\ENUM\Error;
use NetDNS2\Resolver;
use Throwable;

/**
 * What the internet says about a domain: expiry and registrar from RDAP,
 * nameservers, MX, SPF, DMARC and DKIM from the DNS, and the reverse DNS
 * (PTR) of its mail servers.
 *
 * Every value is null when it could not be found. "Not found" is not
 * "empty" - the caller keeps what is stored instead of wiping it. DENIC
 * (.de) for example publishes no expiry date over RDAP at all.
 */
class DomainCheck
{
    /** IANA's list of which RDAP server answers for which TLD. */
    public const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    /**
     * Registries that run RDAP without being listed at IANA. DENIC is the
     * one that matters here: without it every .de domain had no registry
     * data at all. It still publishes no expiry date, only nameservers.
     */
    public const UNLISTED_SERVERS = [
        'de' => 'https://rdap.denic.de/',
    ];

    /**
     * DKIM selectors can't be listed, only asked for by name. These are the
     * ones the usual senders publish: Microsoft 365 (selector1/2), Google
     * Workspace (google), Mailchimp/Mandrill (k1, mte1), Amazon SES-style
     * and hosting panels (default, dkim, mail, s1/s2, key1).
     */
    public const DKIM_SELECTORS = [
        'selector1', 'selector2', 'google', 'default', 'dkim', 'mail',
        's1', 's2', 'k1', 'k2', 'key1', 'key2', 'mte1', 'smtp', 'mx',
    ];

    /**
     * Seconds the DNS part of one check may take. dns_get_record() has no
     * timeout of its own, and now and then a single query hangs for several
     * seconds - fifteen of those in a row ran into PHP's 30 seconds and the
     * "check now" button never came back. Past the budget, DKIM and PTR are
     * left for the next run instead.
     */
    public const BUDGET_SECONDS = 20;

    protected float $deadline = INF;

    protected bool $incomplete = false;

    /** More mail servers than this aren't asked for their PTR. */
    private const PTR_LIMIT = 5;

    /** Large providers answer with many addresses per MX - a sample is enough. */
    private const PTR_ROWS = 10;

    /**
     * @param  array<int, string>  $selectors  own DKIM selectors on top of the common ones
     * @return array{expiry_date: ?string, registrar: ?string, nameservers: ?array, mx: ?array, spf: ?string, dmarc: ?string, dkim: ?array<int, array{selector: string, type: string, bits: ?int, revoked: bool}>, ptr: ?array, incomplete: bool, rdap_error: ?string}
     */
    public function check(string $domain, array $selectors = []): array
    {
        $domain = strtolower(trim($domain, " \t\n\r\0\x0B."));
        $this->deadline = microtime(true) + self::BUDGET_SECONDS;
        $this->incomplete = false;

        $rdap = $this->rdap($domain);

        $nameservers = $this->records($domain, DNS_NS, 'target');
        $mx = $this->mx($domain);

        return [
            'expiry_date' => $rdap['expiry_date'] ?? null,
            'registrar' => $rdap['registrar'] ?? null,
            // RDAP knows the delegation even when the NS lookup is blocked.
            'nameservers' => $nameservers ?: ($rdap['nameservers'] ?? null),
            'mx' => $mx,
            'spf' => $this->txt($domain, 'v=spf1'),
            'dmarc' => $this->txt('_dmarc.'.$domain, 'v=DMARC1'),
            'dkim' => $this->dkim($domain, $selectors),
            'ptr' => $mx ? $this->ptr($mx) : null,
            // DKIM and PTR were cut short - the caller keeps what it has.
            'incomplete' => $this->incomplete,
            'rdap_error' => $rdap['error'] ?? null,
        ];
    }

    /**
     * The selectors that have a DKIM key, own ones first. Only TXT is asked:
     * Microsoft 365 publishes selector1/2 as CNAME into its own zone, the
     * resolver follows it, and a CNAME without a key behind it means DKIM
     * is not switched on there yet - which is exactly what to report.
     */
    protected function dkim(string $domain, array $selectors): ?array
    {
        $selectors = collect($selectors)
            ->map(fn ($s) => strtolower(trim($s)))
            ->filter(fn ($s) => preg_match('/^[a-z0-9][a-z0-9._-]*$/', $s))
            ->merge(self::DKIM_SELECTORS)
            ->unique();

        $found = [];

        foreach ($selectors as $selector) {
            if (! $this->timeLeft()) {
                break;
            }

            foreach ($this->lookup($selector.'._domainkey.'.$domain, DNS_TXT) as $record) {
                $text = isset($record['entries']) ? implode('', $record['entries']) : ($record['txt'] ?? '');

                if (stripos($text, 'v=DKIM1') !== false || stripos($text, 'p=') !== false) {
                    $found[] = ['selector' => $selector] + self::dkimKey($text);
                    break;
                }
            }
        }

        return $found ?: null;
    }

    /**
     * Type and length of the key in a DKIM record. An empty p= is a key
     * that was withdrawn on purpose - published, but signs nothing.
     *
     * @return array{type: string, bits: ?int, revoked: bool}
     */
    public static function dkimKey(string $record): array
    {
        $tags = [];
        foreach (explode(';', $record) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $tags[strtolower(trim($key))] = preg_replace('/\s+/', '', $value);
        }

        $type = strtolower($tags['k'] ?? 'rsa') ?: 'rsa';
        $key = $tags['p'] ?? '';

        if ($key === '') {
            return ['type' => $type, 'bits' => null, 'revoked' => true];
        }

        if ($type === 'ed25519') {
            return ['type' => $type, 'bits' => 256, 'revoked' => false];
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split($key, 64, "\n")."-----END PUBLIC KEY-----\n";
        $public = @openssl_pkey_get_public($pem);

        return [
            'type' => $type,
            'bits' => $public ? (openssl_pkey_get_details($public)['bits'] ?? null) : null,
            'revoked' => false,
        ];
    }

    /**
     * Reverse DNS of the mail servers, forward-confirmed: the PTR of the
     * address must point to a name that resolves back to the same address.
     * Receivers check exactly that before they take mail from a server.
     *
     * @return array<int, array{host: string, ip: string, ptr: ?string, ok: bool}>
     */
    protected function ptr(array $mx): array
    {
        $rows = [];

        foreach (array_slice($mx, 0, self::PTR_LIMIT) as $host) {
            if (! $this->timeLeft()) {
                break;
            }

            foreach ($this->records($host, DNS_A, 'ip') as $ip) {
                $reverse = implode('.', array_reverse(explode('.', $ip))).'.in-addr.arpa';
                $name = $this->records($reverse, DNS_PTR, 'target')[0] ?? null;
                $name = $name ? rtrim($name, '.') : null;

                $rows[] = [
                    'host' => $host,
                    'ip' => $ip,
                    'ptr' => $name,
                    'ok' => $name !== null && in_array($ip, $this->records($name, DNS_A, 'ip'), true),
                ];
            }
        }

        return array_slice($rows, 0, self::PTR_ROWS);
    }

    /** Expiry, registrar and nameservers from the registry. */
    protected function rdap(string $domain): array
    {
        $base = $this->rdapServer($domain);

        if ($base === null) {
            return ['error' => __('Kein RDAP-Server für diese Endung bekannt.')];
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get(rtrim($base, '/').'/domain/'.$domain);
        } catch (Throwable) {
            return ['error' => __('RDAP nicht erreichbar.')];
        }

        if (! $response->successful()) {
            return ['error' => __('RDAP antwortet mit :status.', ['status' => $response->status()])];
        }

        $data = $response->json() ?? [];

        $expiry = collect($data['events'] ?? [])
            ->firstWhere('eventAction', 'expiration')['eventDate'] ?? null;

        $registrar = null;
        foreach ($data['entities'] ?? [] as $entity) {
            if (in_array('registrar', $entity['roles'] ?? [], true)) {
                $registrar = $this->vcardName($entity['vcardArray'] ?? []);
                break;
            }
        }

        $nameservers = collect($data['nameservers'] ?? [])
            ->pluck('ldhName')->filter()->map(fn ($n) => rtrim(strtolower($n), '.'))->sort()->values()->all();

        return [
            'expiry_date' => $expiry ? Carbon::parse($expiry)->toDateString() : null,
            'registrar' => $registrar,
            'nameservers' => $nameservers ?: null,
        ];
    }

    /** The RDAP base URL for the domain's TLD, from the cached IANA list. */
    protected function rdapServer(string $domain): ?string
    {
        $services = Cache::remember('rdap.bootstrap', now()->addDay(), function () {
            try {
                return Http::timeout(10)->get(self::BOOTSTRAP_URL)->json('services') ?? [];
            } catch (Throwable) {
                return [];
            }
        });

        $tld = substr(strrchr('.'.$domain, '.'), 1);

        foreach ($services as [$tlds, $urls]) {
            if (in_array($tld, $tlds, true)) {
                return $urls[0] ?? null;
            }
        }

        return self::UNLISTED_SERVERS[$tld] ?? null;
    }

    /** The "fn" (formatted name) of a jCard. */
    protected function vcardName(array $vcard): ?string
    {
        foreach ($vcard[1] ?? [] as $property) {
            if (($property[0] ?? null) === 'fn' && filled($property[3] ?? null)) {
                return $property[3];
            }
        }

        return null;
    }

    /** Mail servers, ordered by priority. */
    protected function mx(string $domain): ?array
    {
        $records = $this->lookup($domain, DNS_MX);

        if ($records === []) {
            return null;
        }

        usort($records, fn ($a, $b) => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));

        return array_values(array_map(fn ($r) => strtolower($r['target']), $records));
    }

    /** The TXT record starting with $prefix, e.g. "v=spf1". */
    protected function txt(string $host, string $prefix): ?string
    {
        foreach ($this->lookup($host, DNS_TXT) as $record) {
            $text = isset($record['entries']) ? implode('', $record['entries']) : ($record['txt'] ?? '');

            if (stripos($text, $prefix) === 0) {
                return $text;
            }
        }

        return null;
    }

    /** One field of all records of a type, sorted. */
    protected function records(string $host, int $type, string $field): array
    {
        return collect($this->lookup($host, $type))
            ->pluck($field)->filter()->map(fn ($v) => strtolower($v))->sort()->values()->all();
    }

    /** False once the budget is spent - and remembers that it was. */
    protected function timeLeft(): bool
    {
        if (microtime(true) < $this->deadline) {
            return true;
        }

        $this->incomplete = true;

        return false;
    }

    /** The DNS query itself - overridden in tests. */
    protected function lookup(string $host, int $type): array
    {
        $name = self::TYPES[$type] ?? null;

        // Unknown type or no resolver to talk to (no /etc/resolv.conf):
        // the system lookup, without a timeout of its own.
        if ($name === null || ! is_readable(Client::RESOLV_CONF)) {
            try {
                return @dns_get_record($host, $type) ?: [];
            } catch (Throwable) {
                return [];
            }
        }

        /*
         * Net_DNS2 instead of dns_get_record(), because only it has a timeout.
         * The system lookup waited 30 seconds for k1._domainkey.stadel.info -
         * the zone's nameservers simply didn't answer for that one name - and
         * took PHP's whole time limit with it. Not found and no answer both
         * mean "nothing" here; the caller tells them apart by what's missing.
         */
        $answer = null;

        // "Does not exist" is an answer. A timeout is not: asked once more,
        // and if it stays silent the check counts as incomplete, so stored
        // values stay instead of a "no PTR" that was only a slow server.
        for ($try = 0; $try < 2 && $answer === null; $try++) {
            try {
                $answer = $this->resolver()->query($host, $name)->answer;
            } catch (Throwable $e) {
                if (in_array($e->getCode(), [Error::DNS_NXDOMAIN->value, Error::NONE->value], true)) {
                    return [];
                }
            }
        }

        if ($answer === null) {
            $this->incomplete = true;

            return [];
        }

        $records = [];

        foreach ($answer as $rr) {
            // Asking for TXT behind a CNAME returns the CNAME as well.
            if (class_basename($rr) !== $name) {
                continue;
            }

            $records[] = match ($name) {
                'NS' => ['target' => rtrim((string) $rr->nsdname, '.')],
                'MX' => ['target' => rtrim((string) $rr->exchange, '.'), 'pri' => (int) $rr->preference],
                'PTR' => ['target' => rtrim((string) $rr->ptrdname, '.')],
                'CNAME' => ['target' => rtrim((string) $rr->cname, '.')],
                'A' => ['ip' => (string) $rr->address],
                'TXT' => ['entries' => $entries = array_map('strval', $rr->text), 'txt' => implode('', $entries)],
            };
        }

        return $records;
    }

    private const TYPES = [
        DNS_A => 'A', DNS_NS => 'NS', DNS_MX => 'MX',
        DNS_TXT => 'TXT', DNS_PTR => 'PTR', DNS_CNAME => 'CNAME',
    ];

    /** Seconds one DNS query may take before it counts as unanswered. */
    private const QUERY_TIMEOUT = 3;

    private ?Resolver $resolver = null;

    private function resolver(): Resolver
    {
        return $this->resolver ??= new Resolver(['timeout' => self::QUERY_TIMEOUT]);
    }
}
