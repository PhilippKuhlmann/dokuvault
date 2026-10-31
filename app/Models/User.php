<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'two_factor_required',
        'deactivated_at',
        'expiry_mail',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_required' => 'boolean',
        'last_login_at' => 'datetime',
        'invited_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'expiry_mail' => 'boolean',
    ];

    /**
     * App sign-ins end when the account's protection changes: a new password,
     * the second factor removed (lost phone, admin reset), or the second
     * factor newly required for someone who has not set it up. Otherwise a
     * token on a lost or stolen phone kept working for its full 30 days -
     * past exactly the steps meant to cut that device off.
     *
     * Only the app tokens ("mobil"). Tokens an administrator creates for an
     * integration are credentials of their own and managed in the admin area.
     *
     * In the model, not in the controllers: password reset, profile, admin
     * and invitation all write here, and a new path is covered as well.
     */
    protected static function booted(): void
    {
        // The database cascade would drop the export rows but leave their
        // files - with every password of a customer - on disk for good.
        static::deleting(function (User $user) {
            $user->pdfExports()->get()->each->delete();
            // Tokens would otherwise stay behind as rows of a user that is gone.
            $user->tokens()->delete();
        });

        static::updated(function (User $user) {
            $twoFactorRemoved = $user->wasChanged('two_factor_confirmed_at') && $user->two_factor_confirmed_at === null;
            $twoFactorDemanded = $user->wasChanged('two_factor_required') && $user->mustEnrollTwoFactor();

            if ($user->wasChanged('password') || $twoFactorRemoved || $twoFactorDemanded) {
                $user->revokeAppTokens();
            }
        });
    }

    public function pdfExports(): HasMany
    {
        return $this->hasMany(PdfExport::class);
    }

    public function revokeAppTokens(): void
    {
        $this->tokens()->get()
            ->filter(fn ($token) => $token->can('mobil') && ! $token->can('*'))
            ->each->delete();
    }

    /**
     * The TOTP secret. Whoever reads it can generate every code - it is
     * therefore as worth protecting as a password and is stored encrypted.
     */
    protected function twoFactorSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($wert) => ! empty($wert) ? Crypt::decryptString($wert) : null,
            set: fn ($wert) => ! empty($wert) ? Crypt::encryptString($wert) : null,
        );
    }

    /**
     * The recovery codes. Each one replaces the one-time code - so they are
     * encrypted as well.
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

    /**
     * Privilege ceiling for delegated admins: whoever holds admin_user or
     * admin_role without being role 1 may only hand out what they hold
     * themselves. Otherwise a delegate could give themselves role 1, or tick
     * every right on their own role, and own the whole installation.
     *
     * @param  iterable<string>  $permissionNames
     */
    public function mayGrant(iterable $permissionNames): bool
    {
        if ((int) $this->role_id === Role::IS_ADMIN) {
            return true;
        }

        $own = $this->role?->permissions->pluck('name') ?? collect();

        return collect($permissionNames)->diff($own)->isEmpty();
    }

    /**
     * May assign or edit this role. Role 1 is above every delegate: its
     * rights come from Gate::before, not from its ticked permissions.
     */
    public function mayAssign(Role $role): bool
    {
        if ((int) $this->role_id === Role::IS_ADMIN) {
            return true;
        }

        return (int) $role->id !== Role::IS_ADMIN && $this->mayGrant($role->permissions->pluck('name'));
    }

    /**
     * May edit, delete or reset this user. A delegate who could set the
     * password of a higher-ranked user could simply log in as them.
     */
    public function mayManage(User $other): bool
    {
        if ((int) $this->role_id === Role::IS_ADMIN) {
            return true;
        }

        return (int) $other->role_id !== Role::IS_ADMIN && (! $other->role || $this->mayAssign($other->role));
    }

    public function hasCustomer()
    {
        if ($this->customer_id) {
            return true;
        }

        return false;
    }

    /**
     * The second factor only counts once the user has entered a code. A
     * secret on its own only means that an enrollment was begun - maybe the
     * app never took it over.
     */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * An administrator demands the second factor, but it is not set up yet.
     * Until it is, this account gets no further than its own profile.
     *
     * Demo accounts are exempt: there all visitors share one account - the
     * first to connect an app would lock everyone else out.
     */
    public function mustEnrollTwoFactor(): bool
    {
        return $this->two_factor_required
            && ! $this->hasTwoFactor()
            && ! $this->isDemoProtected();
    }

    /** An invitation is on its way and has not been redeemed yet. */
    public function invitationPending(): bool
    {
        return $this->invited_at !== null;
    }

    /**
     * The account is locked: no sign-in, no running session, no API tokens.
     * API-Token.
     *
     * Deliberately no deletion: activity entries hang on a user, and a
     * deleted user turns "Rita changed the rack" into "somebody". Whoever
     * leaves the company gets locked - what they did stays readable.
     */
    public function isDisabled(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * The invited person has set their password - the invitation is complete
     * and its link no longer carries.
     *
     * Not the same as "no open invitation": whoever was never invited has
     * nothing here either. Those two cases used to look alike in the user
     * list.
     */
    public function invitationRedeemed(): bool
    {
        return $this->invitation_accepted_at !== null;
    }

    /**
     * The open invitation has expired.
     *
     * The deadline sits with the broker "invitation" in config/auth.php -
     * here it is read and not written down a second time, or the two numbers
     * drift apart sooner or later.
     */
    public function invitationExpired(): bool
    {
        return $this->invitationPending()
            && $this->invited_at->addMinutes((int) config('auth.passwords.invitation.expire'))->isPast();
    }

    /**
     * Uses up a recovery code. Each one is valid exactly once - otherwise a
     * note someone read over your shoulder would be permanent access.
     */
    public function useRecoveryCode(string $code): bool
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
     * A demo account created by the seeder that must not be changed on the
     * demo instance: whoever changes the admin password or deletes the
     * account locks out every other visitor.
     *
     * Outside demo mode nobody is protected - on a real installation an
     * admin should of course be able to manage their users.
     */
    public function isDemoProtected(): bool
    {
        return (bool) config('app.demo')
            && in_array($this->username, config('custom.demo_protected_users', []), true);
    }
}
