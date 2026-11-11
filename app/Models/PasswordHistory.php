<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

/**
 * A password that was once in force.
 *
 * No TracksChanges: an activity entry about a password history would be
 * exactly the plain text this table is meant to avoid.
 */
class PasswordHistory extends Model
{
    use BelongsToCustomer;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Never visible in an error message, a log or a dd(). */
    protected $hidden = ['value'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function value(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => ! empty($value) ? Crypt::encryptString($value) : null,
        );
    }
}
