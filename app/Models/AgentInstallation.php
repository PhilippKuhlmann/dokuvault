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
    ];

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
     * @param  array<int, string>  $detected
     */
    public static function checkin(AgentToken $token, string $kind, string $machineId, string $hostname, ?string $domain, ?string $version, array $detected): self
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

    /**
     * Not heard from for three hours (hourly by default, plus slack) - the
     * agent is probably gone or its token expired.
     */
    public function isStale(): bool
    {
        return $this->last_seen_at === null || $this->last_seen_at->lt(now()->subHours(3));
    }
}
