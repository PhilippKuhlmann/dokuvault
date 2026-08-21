<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Ein Kennwort, das einmal gegolten hat.
 *
 * Kein TracksChanges: Ein Protokolleintrag ueber eine Kennwort-Historie waere
 * genau der Klartext, den diese Tabelle vermeiden soll.
 */
class PasswordHistory extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Nie in einer Fehlermeldung, einem Log oder einem dd() sichtbar. */
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
