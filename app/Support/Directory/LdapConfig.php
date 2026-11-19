<?php

namespace App\Support\Directory;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The connection to an on-premises Active Directory, as set under
 * Administration > Anmeldung. Stored in the settings table like the mail
 * server - the bind password encrypted, like the SMTP password.
 *
 * Optional throughout: switched off, or without PHP's ldap extension, the
 * sign-in is exactly what it was - local users only.
 */
class LdapConfig
{
    public const ENABLED = 'ldap_enabled';

    public const HOST = 'ldap_host';

    public const PORT = 'ldap_port';

    /**
     * "ldaps" (port 636), "starttls" (port 389, then TLS) or "none" (port
     * 389, plain). "none" sends every password readable across the network
     * and many domain controllers refuse it (LDAP signing) - offered for a
     * closed lab network, with a warning on the page and in the log.
     */
    public const ENCRYPTION = 'ldap_encryption';

    /** Off only for a self-signed certificate the server does not trust. */
    public const VERIFY_CERT = 'ldap_verify_cert';

    public const BASE_DN = 'ldap_base_dn';

    /** A read-only service account; it finds users and their groups. */
    public const BIND_DN = 'ldap_bind_dn';

    public const BIND_PASSWORD = 'ldap_bind_password';

    /** NetBIOS name ("KUHLMANN") - prefix of the usernames of directory accounts. */
    public const DOMAIN = 'ldap_domain';

    /**
     * Whether a directory account created on its first sign-in must set up
     * DokuVault's second factor. Only the start value: afterwards it is set
     * per user in the user admin, and a sign-in leaves it alone.
     */
    public const REQUIRE_2FA = 'ldap_require_2fa';

    public static function requireTwoFactor(): bool
    {
        return Setting::wert(self::REQUIRE_2FA, '1') !== '0';
    }

    public static function extensionLoaded(): bool
    {
        return extension_loaded('ldap');
    }

    /**
     * Switched on and set up far enough to try. Whether PHP can speak LDAP
     * is PhpLdapClient's business (it says so plainly) and the settings
     * page's (it warns) - not a reason to fall silent here.
     */
    public static function enabled(): bool
    {
        return (bool) Setting::wert(self::ENABLED)
            && filled(self::host())
            && filled(self::baseDn());
    }

    /**
     * Entered far enough to ask the directory - switched on or not. The
     * groups can be picked before the sign-in is switched on.
     */
    public static function configured(): bool
    {
        return filled(self::host()) && filled(self::baseDn()) && filled(self::bindDn()) && self::bindPassword() !== null;
    }

    public static function host(): ?string
    {
        return Setting::wert(self::HOST);
    }

    public static function port(): int
    {
        return (int) (Setting::wert(self::PORT) ?: (self::encryption() === 'ldaps' ? 636 : 389));
    }

    public static function encryption(): string
    {
        $wert = Setting::wert(self::ENCRYPTION);

        return in_array($wert, ['starttls', 'none'], true) ? $wert : 'ldaps';
    }

    /** Passwords cross the network readable. */
    public static function unencrypted(): bool
    {
        return self::encryption() === 'none';
    }

    public static function verifyCert(): bool
    {
        return Setting::wert(self::VERIFY_CERT, '1') !== '0';
    }

    public static function baseDn(): ?string
    {
        return Setting::wert(self::BASE_DN);
    }

    public static function bindDn(): ?string
    {
        return Setting::wert(self::BIND_DN);
    }

    public static function domain(): string
    {
        return strtoupper(trim((string) Setting::wert(self::DOMAIN))) ?: 'AD';
    }

    /** The service account's password in plain text, or null. */
    public static function bindPassword(): ?string
    {
        $roh = (string) Setting::wert(self::BIND_PASSWORD);

        if ($roh === '') {
            return null;
        }

        try {
            return Crypt::decryptString($roh);
        } catch (DecryptException) {
            return $roh;
        }
    }

    /** Set the service account's password; null deletes it. */
    public static function setBindPassword(?string $kennwort): void
    {
        Setting::setzen(self::BIND_PASSWORD, $kennwort === null || $kennwort === ''
            ? null
            : Crypt::encryptString($kennwort));
    }
}
