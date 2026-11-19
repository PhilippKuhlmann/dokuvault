<?php

namespace App\Support\Directory;

use App\Models\DirectoryGroupRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in through the directory, next to local users.
 *
 * Which way a username goes:
 * - a local account with exactly this username exists: local - invited
 *   customers and the local admin keep working, even with the directory
 *   switched on or out of reach;
 * - otherwise, with the directory switched on: the directory. "name",
 *   "DOMAIN\name" and "name@domain" all work - a technician whose plain name
 *   is taken by a local account uses one of the other two.
 *
 * A directory account is created on its first sign-in and updated on every
 * one after: name, mail and - above all - the role, from the first mapped
 * group it is in. No mapped group, no access. The account stays separate
 * from any local one of the same name.
 */
class DirectoryLogin
{
    public const SOURCE = 'ldap';

    public function __construct(private readonly DirectoryClient $client) {}

    /** Whether this username is meant for the directory rather than a local account. */
    public function handles(string $username): bool
    {
        return LdapConfig::enabled()
            && ! User::where('username', $username)->where('auth_source', 'local')->exists();
    }

    /**
     * The DokuVault user for these credentials, or null when the directory
     * says no (unknown, wrong password, disabled there).
     *
     * @throws ValidationException when the directory cannot be asked, or the
     *                             account is in no group that grants a role
     */
    public function login(string $username, string $password): ?User
    {
        try {
            $konto = $this->client->authenticate($username, $password);
        } catch (DirectoryUnavailable $fehler) {
            report($fehler);

            throw ValidationException::withMessages([
                'username' => __('Die Anmeldung über das Active Directory ist gerade nicht möglich. Bitte später erneut versuchen oder an die Administration wenden.'),
            ]);
        }

        if ($konto === null) {
            return null;
        }

        // The password was right - saying why it ends here gives nothing
        // away, and "wrong password" would send the person off looking for
        // the wrong thing.
        $rolle = DirectoryGroupRole::roleFor(self::SOURCE, $konto->groups);
        if ($rolle === null) {
            throw ValidationException::withMessages([
                'username' => __('Ihr Konto ist in keiner Gruppe, die DokuVault freigibt. Wenden Sie sich an Ihre Administration.'),
            ]);
        }

        return $this->sync($konto, $rolle->id);
    }

    /** Creates or updates the DokuVault account of a directory user. */
    private function sync(DirectoryUser $konto, int $rolleId): User
    {
        $nutzer = User::where('auth_source', self::SOURCE)->where('external_id', $konto->guid)->first()
            // A password nobody knows: the column may not be empty, and a
            // directory account must never sign in locally.
            ?? (new User)->forceFill(['password' => Hash::make(Str::random(64))]);

        // Mail is unique: taken by another account (a local one, say), it
        // stays empty here instead of the save failing.
        $mail = filled($konto->email) && ! User::where('email', $konto->email)->whereKeyNot($nutzer->id)->exists()
            ? $konto->email
            : $nutzer->email;

        $nutzer->forceFill([
            'name' => $konto->name ?: $konto->username,
            // DOMAIN\name: distinct from any local username, and the way
            // Windows writes it.
            'username' => LdapConfig::domain().'\\'.$konto->username,
            'email' => $mail,
            'role_id' => $rolleId,
            // Staff: no customer, they see across customers by their role.
            'customer_id' => null,
            'auth_source' => self::SOURCE,
            'external_id' => $konto->guid,
            // Back in the group, back in: the hourly sync looks at it again.
            'directory_revoked_at' => null,
        ]);

        // The second factor: the start value from Administration > Anmeldung
        // (AD) for a new account only. Afterwards it is set per user in the
        // user admin - admins yes, others no - and a sign-in leaves it alone.
        if (! $nutzer->exists) {
            $nutzer->two_factor_required = LdapConfig::requireTwoFactor();
        }

        $nutzer->save();

        return $nutzer;
    }
}
