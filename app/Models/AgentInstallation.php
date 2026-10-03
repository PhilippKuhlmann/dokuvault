<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'detected' => 'array',
        'roles' => 'array',
        'last_seen_at' => 'datetime',
        'last_run_at' => 'datetime',
        'run_requested_at' => 'datetime',
        'last_results' => 'array',
    ];

    /** Longest message kept per role - the tail of the output says most. */
    public const MESSAGE_LENGTH = 1000;

    /** Roles that exist once per domain, not once per machine. */
    public const ONCE_PER_DOMAIN = ['windows-ad'];

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
            if (in_array($role, self::ONCE_PER_DOMAIN, true)
                && static::someoneElseRuns($installation, $token->customer_id, $role, $domain)) {
                continue;
            }
            $roles[] = $role;
        }

        if (! $installation->exists && $interval && $interval !== config('custom.agent_intervall_standard')) {
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
        return $this->interval_minutes ?: (int) config('custom.agent_intervall_standard');
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
        return $this->last_seen_at === null || $this->last_seen_at->lt(now()->subHours(3));
    }
}
