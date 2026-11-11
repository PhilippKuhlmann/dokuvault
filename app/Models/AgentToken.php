<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgentToken extends Model
{
    use BelongsToCustomer;
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    /** Locked in the agent list: refused until unlocked, nothing reinstalled. */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * One token per agent: binds the token to the machine of its first
     * check-in. Returns false if it already belongs to another machine.
     * Atomic - two machines checking in at once cannot both win.
     */
    public function bindTo(string $machineId): bool
    {
        if ($this->machine_id === null) {
            static::whereKey($this->id)->whereNull('machine_id')->update(['machine_id' => $machineId]);
            $this->refresh();
        }

        return $this->machine_id === $machineId;
    }

    /**
     * A token has expired only when it has a deadline and that has passed.
     *
     * No expiry date means legacy - created before the mandatory deadline was
     * introduced. Such tokens stay valid, or the migration would have brought
     * every running agent to a halt.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * sha256 hash for storage and lookup (no plain text in the database).
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Creates a new token record and returns the plain-text token (shown
     * once only).
     */
    public static function generateFor(Customer $customer, Site $site, ?string $name = null, ?DateTimeInterface $expiresAt = null): array
    {
        $plain = 'doc_'.Str::random(48);

        $token = static::create([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'name' => $name,
            'token' => static::hashToken($plain),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    /**
     * Renews the token in place: new plain text, new deadline, the old value
     * is invalid from now on. Customer, site and name stay - that way a burnt
     * token can be swapped without losing the assignment and the activity
     * history.
     *
     * last_used_at is reset: that figure belongs to the old value, the new
     * one has never been used.
     */
    public function renew(DateTimeInterface $expiresAt): string
    {
        $plain = 'doc_'.Str::random(48);

        $this->forceFill([
            'token' => static::hashToken($plain),
            'expires_at' => $expiresAt,
            'last_used_at' => null,
        ])->save();

        return $plain;
    }
}
