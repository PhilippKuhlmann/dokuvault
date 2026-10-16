<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-customer checklist settings: how the AD script sets the start
 * password of a new user, and whether new checklists force a change at
 * first sign-in.
 */
class ChecklistSetting extends Model
{
    /** Readable sets: no l/I/O/0/1 that look alike on a note. */
    public const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const LOWER = 'abcdefghijkmnopqrstuvwxyz';

    public const DIGITS = '23456789';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'password_length' => 'integer',
        'password_uppercase' => 'integer',
        'password_special_count' => 'integer',
        'password_digits' => 'integer',
        'force_change_default' => 'boolean',
    ];

    protected $attributes = [
        'password_mode' => 'generated',
        'password_length' => 8,
        'password_uppercase' => 1,
        'password_special_count' => 1,
        'password_digits' => 2,
        'password_specials' => '!-',
        'force_change_default' => false,
    ];

    /** The settings of a customer - unsaved defaults if none exist yet. */
    public static function for(Customer $customer): self
    {
        return static::firstOrNew(['customer_id' => $customer->id]);
    }

    protected function fixedPassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => ! empty($value) ? Crypt::encryptString($value) : null,
        );
    }

    public function isFixed(): bool
    {
        return $this->password_mode === 'fixed';
    }

    /** Upper case letters, special characters and digits must fit the length. */
    public static function fits(int $length, int $uppercase, int $specials, int $digits): bool
    {
        return $uppercase + $specials + $digits <= $length;
    }

    /** A fixed password must be changed at first sign-in; otherwise the checklist decides. */
    public function forcesChange(): bool
    {
        return $this->isFixed() || $this->force_change_default;
    }

    /**
     * What the AD script needs to set the password. A fixed password has to
     * travel in the script; a rule does not reveal any password.
     */
    public function scriptRule(): array
    {
        return $this->isFixed()
            ? ['mode' => 'fixed', 'fixed' => $this->fixed_password]
            : [
                'mode' => 'generated',
                'length' => $this->password_length,
                'uppercase' => $this->password_uppercase,
                'specialCount' => $this->password_special_count,
                'digits' => $this->password_digits,
                'specials' => $this->password_specials,
            ];
    }

    /**
     * An example after the rule, for the settings page. The script builds
     * the real one the same way: the upper case letters, the special
     * characters and the digits, the rest lower case letters - all without
     * look-alikes (see UPPER, LOWER, DIGITS).
     */
    public function example(): string
    {
        $pick = fn (string $set) => $set[random_int(0, strlen($set) - 1)];

        $chars = [];
        for ($i = 0; $i < $this->password_uppercase; $i++) {
            $chars[] = $pick(self::UPPER);
        }
        for ($i = 0; $i < $this->password_special_count; $i++) {
            $chars[] = $pick($this->password_specials ?: '!');
        }
        for ($i = 0; $i < $this->password_digits; $i++) {
            $chars[] = $pick(self::DIGITS);
        }
        while (count($chars) < $this->password_length) {
            $chars[] = $pick(self::LOWER);
        }

        shuffle($chars);

        return implode('', $chars);
    }
}
