<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A started on- or offboarding for one person. The items are a copy of the
 * template at start time.
 */
class Checklist extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'reference_date' => 'date',
        'request' => 'array',
        'force_password_change' => 'boolean',
        'completed_at' => 'datetime',
        'script_token_expires_at' => 'datetime',
        'script_reported_at' => 'datetime',
    ];

    /** How long a downloaded AD script may report back. */
    public const SCRIPT_TOKEN_HOURS = 48;

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort')->orderBy('id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function adUser()
    {
        return $this->belongsTo(ADUser::class, 'ad_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function isOnboarding(): bool
    {
        return $this->type === 'onboarding';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** For the global search: the person, and whether they come or go. */
    public function protokollName(): ?string
    {
        return $this->fullName().' ('.($this->isOnboarding() ? __('Onboarding') : __('Offboarding')).')';
    }

    /**
     * A new key for the AD script; the previous one stops working. Only the
     * hash is kept - the plain key exists in the downloaded file alone.
     */
    public function issueScriptToken(User $by): string
    {
        $token = Str::random(48);

        $this->update([
            'script_token_hash' => hash('sha256', $token),
            'script_token_expires_at' => now()->addHours(self::SCRIPT_TOKEN_HOURS),
            'script_requested_by' => $by->id,
        ]);

        return $token;
    }

    /** The checklist a script key belongs to, while the key is valid. */
    public static function forScriptToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('script_token_hash', hash('sha256', $token))
            ->where('script_token_expires_at', '>', now())->first();
    }

    /** Marks the list completed once every item is done - and reopens it otherwise. */
    public function refreshCompletion(): void
    {
        $open = $this->items()->whereNull('done_at')->exists();

        if (! $open && ! $this->completed_at) {
            $this->update(['completed_at' => now()]);
        } elseif ($open && $this->completed_at) {
            $this->update(['completed_at' => null]);
        }
    }
}
