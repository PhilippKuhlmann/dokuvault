<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The certificate a host actually serves - issuer, subject and validity.
 *
 * The peer is not verified on purpose: an expired or self-signed certificate
 * is exactly the one worth documenting, and a verifying handshake would
 * refuse to show it.
 */
class CertificateCheck
{
    /** Shared-address space of carrier-grade NAT - not in IpUtils' list. */
    private const CGNAT = '100.64.0.0/10';

    /** The vetted address to connect to in public-only mode, see check(). */
    private ?string $connectTo = null;

    /**
     * @param  bool  $publicOnly  For customer accounts: no internal targets. Otherwise
     *                            "check now" lets them probe which internal host:port
     *                            answers, from inside the server's network.
     * @return array{common_name: ?string, issuer: ?string, issued_date: ?string, expiry_date: ?string}
     *
     * @throws RuntimeException when the host can't be reached, is internal in
     *                          public-only mode, or sends no certificate
     */
    public function check(string $host, int $port = 443, bool $publicOnly = false): array
    {
        // Resolved once and connected to exactly that address: a second
        // lookup at connect time could be answered with an internal one.
        $this->connectTo = $publicOnly ? self::publicAddress($host) : null;

        try {
            $cert = $this->peerCertificate($host, $port);
        } finally {
            $this->connectTo = null;
        }

        $issuer = $cert['issuer'] ?? [];
        $organisation = $issuer['O'] ?? null;
        $issuerName = $issuer['CN'] ?? null;

        return [
            'common_name' => $cert['subject']['CN'] ?? null,
            // "Let's Encrypt (R11)" says more than either part alone.
            'issuer' => match (true) {
                $organisation && $issuerName && $organisation !== $issuerName => $organisation.' ('.$issuerName.')',
                default => $organisation ?? $issuerName,
            },
            'issued_date' => isset($cert['validFrom_time_t']) ? Carbon::createFromTimestamp($cert['validFrom_time_t'])->toDateString() : null,
            'expiry_date' => isset($cert['validTo_time_t']) ? Carbon::createFromTimestamp($cert['validTo_time_t'])->toDateString() : null,
        ];
    }

    /** The parsed certificate - overridden in tests. */
    protected function peerCertificate(string $host, int $port): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $target = $this->connectTo === null ? $host
            : (str_contains($this->connectTo, ':') ? '['.$this->connectTo.']' : $this->connectTo);

        $client = @stream_socket_client('ssl://'.$target.':'.$port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            throw new RuntimeException(self::connectionError($host, $port, $errno, $errstr));
        }

        $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $parsed = $cert ? openssl_x509_parse($cert) : false;

        if (! $parsed) {
            throw new RuntimeException(__(':ziel liefert kein Zertifikat.', ['ziel' => $host.':'.$port]));
        }

        return $parsed;
    }

    /**
     * Whether a host is no internal address. Names that do not resolve count
     * as public - there is nothing internal to reach behind them.
     */
    public static function isPublicTarget(string $host): bool
    {
        return collect(self::addresses($host))->every(fn (string $ip) => self::isPublicIp($ip));
    }

    /** The first address of a host that resolves only to public ones. */
    private static function publicAddress(string $host): string
    {
        $addresses = self::addresses($host);

        if ($addresses === []) {
            throw new RuntimeException(__(':host ist im öffentlichen DNS nicht bekannt.', ['host' => $host]));
        }

        if (! collect($addresses)->every(fn (string $ip) => self::isPublicIp($ip))) {
            throw new RuntimeException(__(':host zeigt auf eine interne Adresse und wird nicht geprüft.', ['host' => $host]));
        }

        return $addresses[0];
    }

    /** @return list<string> */
    private static function addresses(string $host): array
    {
        $host = trim($host, '[] ');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $v4 = @gethostbynamel($host) ?: [];
        $v6 = collect(@dns_get_record($host, DNS_AAAA) ?: [])->pluck('ipv6')->filter()->all();

        return array_values(array_unique([...$v4, ...$v6]));
    }

    private static function isPublicIp(string $ip): bool
    {
        return ! IpUtils::isPrivateIp($ip)
            && ! IpUtils::checkIp($ip, self::CGNAT)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * What went wrong, in words the customer understands.
     *
     * The socket error is the operating system's ("php_network_getaddresses:
     * getaddrinfo for mail.example.com failed: nodename nor servname provided")
     * and differs between Linux and macOS - so match on the parts both share.
     * Whatever is not recognised still names host and port, without the
     * technical text.
     */
    public static function connectionError(string $host, int $port, int $errno, string $errstr): string
    {
        $error = strtolower($errstr);
        $has = fn (string ...$parts) => collect($parts)->contains(fn ($part) => str_contains($error, $part));
        $values = ['host' => $host, 'port' => $port];

        return match (true) {
            $has('getaddrinfo', 'not known', 'no address associated', 'name resolution') => __(':host ist im öffentlichen DNS nicht bekannt. Läuft der Dienst nur intern, unter „Prüfen an Host“ einen von außen erreichbaren Namen eintragen.', $values),
            $has('refused') => __(':host nimmt auf Port :port keine Verbindung an.', $values),
            $has('timed out', 'timeout') => __(':host antwortet auf Port :port nicht – vermutlich nur intern erreichbar oder durch eine Firewall gesperrt.', $values),
            $has('unreachable', 'no route') => __(':host ist von hier aus nicht erreichbar.', $values),
            $errno === 0 || $has('ssl', 'tls', 'handshake', 'crypto') => __(':host spricht auf Port :port kein TLS – ist der Port richtig?', $values),
            default => __('Keine Verbindung zu :host auf Port :port.', $values),
        };
    }
}
