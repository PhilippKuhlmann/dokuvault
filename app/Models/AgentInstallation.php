<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * An installed agent on one machine (see the migration for the why).
 *
 * The machine reports what it detected; which of it runs is decided here
 * and can be changed on the agent page. Defaults on first contact follow
 * the detection, with one exception: the AD role goes to only one DC per
 * domain - the domain is the same whichever DC reports it.
 */
class AgentInstallation extends Model
{
    use OwnedByCustomer;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'detected' => 'array',
        'roles' => 'array',
        'last_seen_at' => 'datetime',
        'last_run_at' => 'datetime',
        'run_requested_at' => 'datetime',
        'last_results' => 'array',
        'scan_network_ids' => 'array',
        'scan_communities' => 'encrypted:array',
        'last_scan_at' => 'datetime',
        'scan_requested_at' => 'datetime',
    ];

    /** Longest message kept per role - the tail of the output says most. */
    public const MESSAGE_LENGTH = 1000;

    /** Roles that exist once per domain, not once per machine. */
    public const ONCE_PER_DOMAIN = ['windows-ad'];

    /**
     * The network scan: printers and UPS by SNMP, run by the service itself
     * rather than a script. Never switched on by detection - sweeping a
     * network can alarm an EDR or IDS, so someone decides it on the page.
     */
    public const SCAN_ROLE = 'network-scan';

    /** Roles only ever switched on by hand. */
    public const NEVER_AUTOMATIC = [self::SCAN_ROLE];

    /** Largest network the scan takes: a /22, 1024 addresses. */
    public const SCAN_MIN_PREFIX = 22;

    /** At most this many SNMP communities are tried per address. */
    public const SCAN_MAX_COMMUNITIES = 5;

    /** Without a community of its own, the scan asks with the usual default. */
    public const SCAN_DEFAULT_COMMUNITY = 'public';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function agentToken()
    {
        return $this->belongsTo(AgentToken::class);
    }

    /** @return array<string, string> role => label, for this kind of agent */
    public static function availableRoles(string $kind): array
    {
        return config("custom.dienste.$kind.rollen", []);
    }

    /**
     * Called by the agent before every run. Records the machine and returns
     * the roles it should run.
     *
     * Roles detected for the first time are switched on (so a server
     * promoted to DC later starts reporting AD) - unless another machine of
     * the same domain already does it. Roles once switched off here stay
     * off: the choice made on the page wins over the detection.
     *
     * $interval is what the machine was installed with (install -interval,
     * INTERVAL=): taken over once, on first contact - afterwards the agent
     * page decides.
     *
     * @param  array<int, string>  $detected
     */
    public static function checkin(AgentToken $token, string $kind, string $machineId, string $hostname, ?string $domain, ?string $version, array $detected, ?int $interval = null): self
    {
        $available = array_keys(static::availableRoles($kind));
        $detected = array_values(array_intersect($detected, $available));

        $installation = static::firstOrNew([
            'customer_id' => $token->customer_id,
            'machine_id' => $machineId,
        ]);

        $new = array_diff($detected, $installation->detected ?? []);
        $roles = $installation->roles ?? [];
        foreach ($new as $role) {
            if (in_array($role, self::NEVER_AUTOMATIC, true)) {
                continue;
            }
            if (in_array($role, self::ONCE_PER_DOMAIN, true)
                && static::someoneElseRuns($installation, $token->customer_id, $role, $domain)) {
                continue;
            }
            $roles[] = $role;
        }

        if (! $installation->exists && $interval && $interval !== Setting::agentIntervall()) {
            // The nearest offered interval: 45 from an old install becomes 30.
            $installation->interval_minutes = collect(array_keys(config('custom.agent_intervalle')))
                ->sortBy(fn ($m) => abs($m - $interval))
                ->first();
        }

        $installation->fill([
            'agent_token_id' => $token->id,
            'kind' => $kind,
            'hostname' => $hostname,
            'domain' => $domain ?: null,
            'version' => $version ?: null,
            'detected' => $detected,
            'roles' => array_values(array_unique(array_intersect($roles, $available))),
            'last_seen_at' => now(),
        ])->save();

        return $installation;
    }

    /** Whether another machine of this customer and domain runs the role. */
    protected static function someoneElseRuns(self $self, int $customerId, string $role, ?string $domain): bool
    {
        return static::where('customer_id', $customerId)
            ->when($self->exists, fn ($q) => $q->whereKeyNot($self->id))
            ->get()
            ->contains(fn (self $other) => in_array($role, $other->roles ?? [], true)
                && strcasecmp((string) $other->domain, (string) $domain) === 0);
    }

    public function intervalMinutes(): int
    {
        return $this->interval_minutes ?: Setting::agentIntervall();
    }

    /**
     * Whether the agent should run now - asked on every checkin (every five
     * minutes). Due when "Jetzt melden" was pressed, when it never ran, or
     * when the interval has passed. Two minutes of slack: polling every five
     * minutes, an hourly agent would otherwise drift to 65 minutes.
     *
     * Saying yes counts as the run: last_run_at is set now (the report sets
     * it again), so a lost report does not mean a run on every poll.
     */
    public function takeDueRun(): bool
    {
        $due = $this->run_requested_at !== null
            || $this->last_run_at === null
            || $this->last_run_at->lte(now()->subMinutes($this->intervalMinutes())->addMinutes(2));

        if ($due) {
            $this->forceFill(['last_run_at' => now(), 'run_requested_at' => null])->save();
        }

        return $due;
    }

    /**
     * Stores the outcome of a run. Reported roles replace their old result;
     * results of roles no longer assigned are dropped - a red dot for
     * something switched off would only confuse.
     *
     * @param  array<int, array{role: string, ok: bool, message?: string|null}>  $results
     */
    public function recordResults(array $results): void
    {
        $stand = $this->last_results ?? [];

        foreach ($results as $result) {
            $message = trim((string) ($result['message'] ?? ''));
            $stand[$result['role']] = [
                'ok' => (bool) $result['ok'],
                // Keep the end: errors come last in a script's output.
                'message' => $message === '' ? null : mb_substr($message, -self::MESSAGE_LENGTH),
                'at' => now()->toIso8601String(),
            ];
        }

        $this->forceFill([
            'last_results' => array_intersect_key($stand, array_flip($this->roles ?? [])),
            'last_run_at' => now(),
        ])->save();
    }

    /** @return array{ok: bool, message: string|null, at: string}|null */
    public function resultFor(string $role): ?array
    {
        return $this->last_results[$role] ?? null;
    }

    /** @return array<int, string> roles whose last run failed */
    public function failedRoles(): array
    {
        return array_keys(array_filter($this->last_results ?? [], fn ($r) => ! ($r['ok'] ?? true)));
    }

    /**
     * Not heard from for three hours (hourly by default, plus slack) - the
     * agent is probably gone or its token expired.
     */
    public function isStale(): bool
    {
        return $this->last_seen_at === null || $this->last_seen_at->lt(now()->subHours(Setting::agentSilentHours()));
    }

    public function runsScan(): bool
    {
        return in_array(self::SCAN_ROLE, $this->roles ?? [], true);
    }

    /**
     * The networks of a customer the scan can take: IPv4 with a prefix of
     * at least /22. Picked from the documented networks, never typed in -
     * so nobody sweeps a foreign /16 by a typo.
     *
     * @return Collection<int, Network>
     */
    public static function scannableNetworks(int $customerId)
    {
        return Network::forCustomer($customerId)
            ->with('site')
            ->whereNotNull('network')
            ->whereNotNull('cidr')
            ->get()
            ->filter(fn (Network $netz) => static::cidrOf($netz) !== null)
            ->sortBy(fn (Network $netz) => [$netz->site?->name, ip2long((string) $netz->network)])
            ->values();
    }

    /**
     * "192.168.10.0/24" for a scannable network, else null. The address is
     * cut to the network: a network documented as 192.168.10.1/24 still
     * scans the whole /24.
     */
    public static function cidrOf(Network $netz): ?string
    {
        $adresse = trim((string) $netz->network);
        $praefix = filter_var($netz->cidr, FILTER_VALIDATE_INT);

        if (! filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || $praefix === false || $praefix < self::SCAN_MIN_PREFIX || $praefix > 32) {
            return null;
        }

        $maske = $praefix === 0 ? 0 : (-1 << (32 - $praefix)) & 0xFFFFFFFF;

        return long2ip(ip2long($adresse) & $maske).'/'.$praefix;
    }

    /**
     * The networks this agent scans - still documented, still scannable.
     *
     * @return Collection<int, Network>
     */
    public function scanNetworks()
    {
        $ids = array_map('intval', $this->scan_network_ids ?? []);

        return static::scannableNetworks($this->customer_id)
            ->filter(fn (Network $netz) => in_array($netz->id, $ids, true))
            ->values();
    }

    /** @return array<int, string> */
    public function scanCommunities(): array
    {
        return array_values(array_filter($this->scan_communities ?? [], 'filled')) ?: [self::SCAN_DEFAULT_COMMUNITY];
    }

    /**
     * The scan job for this checkin, or null: at most once a day, or when
     * "Jetzt scannen" was pressed - and only with the role on and at least
     * one network. Handing it out counts as the scan, like takeDueRun: a
     * scan that dies on the machine is not repeated every five minutes.
     *
     * @return array{networks: array<int, string>, communities: array<int, string>}|null
     */
    public function takeDueScan(): ?array
    {
        if (! $this->runsScan()) {
            return null;
        }

        $netze = $this->scanNetworks()->map(fn (Network $netz) => static::cidrOf($netz))->unique()->values()->all();
        if ($netze === []) {
            return null;
        }

        $faellig = $this->scan_requested_at !== null
            || $this->last_scan_at === null
            || $this->last_scan_at->lte(now()->subDay()->addMinutes(10));

        if (! $faellig) {
            return null;
        }

        $this->forceFill(['last_scan_at' => now(), 'scan_requested_at' => null])->save();

        return ['networks' => $netze, 'communities' => $this->scanCommunities()];
    }
}
