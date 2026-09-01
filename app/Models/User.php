<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use TracksChanges;

    protected $fillable = [
        'name',
        'username',
        'password',
        'email',
        'locale',
        'role_id',
        'customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * Das TOTP-Geheimnis. Wer es liest, kann jeden Code erzeugen - es ist
     * damit so schuetzenswert wie ein Kennwort und liegt verschluesselt.
     */
    protected function twoFactorSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($wert) => ! empty($wert) ? Crypt::decryptString($wert) : null,
            set: fn ($wert) => ! empty($wert) ? Crypt::encryptString($wert) : null,
        );
    }

    /**
     * Die Wiederherstellungscodes. Jeder einzelne ersetzt den Einmalcode -
     * also ebenfalls verschluesselt.
     */
    protected function twoFactorRecoveryCodes(): Attribute
    {
        return Attribute::make(
            get: fn ($wert) => ! empty($wert) ? json_decode(Crypt::decryptString($wert), true) : null,
            set: fn ($wert) => ! empty($wert) ? Crypt::encryptString(json_encode(array_values($wert))) : null,
        );
    }

    public function hasPermission($ability)
    {
        foreach ($this->role->permissions as $permission) {
            if ($permission->name === $ability) {
                return true;
            }
        }

        return false;
    }

    public function hasCustomer()
    {
        if ($this->customer_id) {
            return true;
        }

        return false;
    }

    /**
     * Die zweite Stufe zaehlt erst, wenn der Nutzer einen Code eingegeben hat.
     * Ein Geheimnis allein heisst nur, dass eine Einrichtung begonnen wurde -
     * vielleicht hat die App es nie uebernommen.
     */
    public function hatZweiteStufe(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Verbraucht einen Wiederherstellungscode. Jeder gilt genau einmal -
     * sonst waere ein abgelesener Zettel ein Dauerzugang.
     */
    public function wiederherstellungscodeVerbrauchen(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];

        $rest = array_values(array_filter(
            $codes,
            fn ($vorhanden) => ! hash_equals($vorhanden, trim($code))
        ));

        if (count($rest) === count($codes)) {
            return false;
        }

        $this->forceFill(['two_factor_recovery_codes' => $rest])->save();

        return true;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Ein vom Seeder angelegter Demo-Zugang, der auf der Demo-Instanz nicht
     * veraendert werden darf: Wer das Admin-Passwort aendert oder das Konto
     * loescht, sperrt alle uebrigen Besucher aus.
     *
     * Ausserhalb des Demo-Modus ist niemand geschuetzt - auf einer echten
     * Installation soll ein Admin seine Benutzer natuerlich verwalten koennen.
     */
    public function istDemoGeschuetzt(): bool
    {
        return (bool) config('app.demo')
            && in_array($this->username, config('custom.demo_protected_users', []), true);
    }
}
