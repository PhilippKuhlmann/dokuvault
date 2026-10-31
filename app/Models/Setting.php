<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Once;
use Illuminate\Validation\Rules\Password;

/**
 * Einstellungen einer Installation.
 *
 * Read through the cache: the remote-access button sits in every device
 * list, which would otherwise be one query per page for a value that
 * changes once a month.
 */
class Setting extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Key of the remote-access solution, e.g. "rustdesk". */
    public const REMOTE_TOOL = 'remote_tool';

    /** Own URL pattern when "custom" is chosen as the solution. */
    public const REMOTE_PATTERN = 'remote_pattern';

    /** Own name of the installation, instead of the name from the .env. */
    public const APP_NAME = 'app_name';

    /** Hash of the one-time code for /setup - see App\Support\InitialSetup. */
    public const SETUP_CODE = 'setup_code';

    /** Groesste erlaubte Datei in Kilobyte - siehe uploadMaxKb(). */
    public const UPLOAD_MAX_KB = 'upload_max_kb';

    /*
     * What a password has to look like for a user to sign in with it - see
     * passwordRule(). Not meant are the documented passwords of the
     * customers: there we record what is, not what ought to be. A customer
     * with a weak password must stay documentable.
     */
    public const PW_MIN = 'pw_min';

    public const PW_MIXED = 'pw_mixed';

    public const PW_NUMBERS = 'pw_numbers';

    public const PW_SYMBOLS = 'pw_symbols';

    public const PW_UNCOMPROMISED = 'pw_uncompromised';

    /**
     * Timezone of the display. Storage stays UTC - see
     * App\Support\LocalTime.
     */
    public const APP_TIMEZONE = 'app_timezone';

    /*
     * Mail sending. Whatever stands here applies instead of the .env - see
     * App\Providers\AppServiceProvider. Enter nothing and the values from
     * the .env stay, just as before this setting existed.
     */
    public const MAIL_HOST = 'mail_host';

    public const MAIL_PORT = 'mail_port';

    public const MAIL_USERNAME = 'mail_username';

    /** Verschluesselt abgelegt - siehe mailKennwort(). */
    public const MAIL_PASSWORD = 'mail_password';

    /** '', 'tls' oder 'ssl'. */
    public const MAIL_ENCRYPTION = 'mail_encryption';

    public const MAIL_FROM_ADDRESS = 'mail_from_address';

    public const MAIL_FROM_NAME = 'mail_from_name';

    /**
     * Paths of the own logos on the local disk, one per place.
     *
     * Three instead of one: the logo on the sign-in page may be large and
     * wide, the one in the header has to fit beside the name, and a favicon
     * is square and tiny. In practice those are different files.
     */
    public const LOGO_STELLEN = ['login', 'header', 'favicon'];

    /**
     * The name that stands in the header, the title bar and the PDF.
     *
     * Falls back to config('app.name'): enter nothing and the name from the
     * .env stays - just as before this setting existed.
     */
    public static function appName(): string
    {
        $eigener = trim((string) self::wert(self::APP_NAME));

        return $eigener !== '' ? $eigener : (string) config('app.name');
    }

    /**
     * The SMTP password in plain text, or null.
     *
     * It is stored encrypted in the setting: the values go through a cache,
     * and a password that lets someone send mail in the company's name has
     * no business sitting in plain text there or in a database dump.
     * sitting in plain text there.
     *
     * The fallback to the raw value is for the case that someone set the row
     * by hand - better a password that works than a
     * Ausnahme beim ersten Mailversand.
     */
    public static function mailPassword(): ?string
    {
        $roh = (string) self::wert(self::MAIL_PASSWORD);

        if ($roh === '') {
            return null;
        }

        try {
            return Crypt::decryptString($roh);
        } catch (DecryptException) {
            return $roh;
        }
    }

    /** Set the SMTP password; null deletes it. */
    public static function setMailPassword(?string $kennwort): void
    {
        self::setzen(self::MAIL_PASSWORD, $kennwort === null || $kennwort === ''
            ? null
            : Crypt::encryptString($kennwort));
    }

    /**
     * The largest allowed file in kilobytes.
     *
     * Capped at what the server lets through at all: PHP and the web server
     * have limits of their own, and a setting above them would be a promise
     * that does not hold - the upload would break mid-transfer, without a
     * message anyone can understand.
     */
    public static function uploadMaxKb(): int
    {
        $eigene = (int) self::wert(self::UPLOAD_MAX_KB);
        $gewuenscht = $eigene > 0 ? $eigene : (int) config('custom.datei_max_kb');

        return max(1, min($gewuenscht, self::serverGrenzeKb()));
    }

    /**
     * The rule for passwords that users sign in with.
     *
     * It is set in one place - Password::defaults() in the AppServiceProvider
     * - and thereby applies everywhere: in one's own profile, when an
     * administrator creates an account, on a reset and when redeeming an
     * invitation. A rule that takes hold in one place only is no rule.
     *
     * Without a setting it stays with what Laravel demands anyway: eight
     * characters. That is exactly what applied before, only nobody knew it.
     */
    public static function passwordRule(): Password
    {
        $regel = Password::min(self::passwordMinLength());

        if (self::ja(self::PW_MIXED)) {
            $regel->mixedCase();
        }

        if (self::ja(self::PW_NUMBERS)) {
            $regel->numbers();
        }

        if (self::ja(self::PW_SYMBOLS)) {
            $regel->symbols();
        }

        if (self::ja(self::PW_UNCOMPROMISED)) {
            $regel->uncompromised();
        }

        return $regel;
    }

    /** Eight is Laravel's default and thereby what applied silently before. */
    public static function passwordMinLength(): int
    {
        $eigene = (int) self::wert(self::PW_MIN);

        return $eigene >= 8 ? min($eigene, 64) : 8;
    }

    /**
     * What is demanded, in one sentence.
     *
     * It stands under every password field. Demanding a special character
     * without writing it down leaves people guessing - and the user learns
     * the rule only after breaking it.
     */
    public static function passwordNotice(): string
    {
        $teile = [];

        if (self::ja(self::PW_MIXED)) {
            $teile[] = __('Groß- und Kleinbuchstaben');
        }

        if (self::ja(self::PW_NUMBERS)) {
            $teile[] = __('eine Ziffer');
        }

        if (self::ja(self::PW_SYMBOLS)) {
            $teile[] = __('ein Sonderzeichen');
        }

        $satz = __('Mindestens :anzahl Zeichen', ['anzahl' => self::passwordMinLength()]);

        if ($teile !== []) {
            $letzter = array_pop($teile);

            $satz .= ', '.($teile === []
                ? $letzter
                : implode(', ', $teile).' '.__('und').' '.$letzter);
        }

        return $satz.'.';
    }

    /** A checkbox from the settings. */
    private static function ja(string $schluessel): bool
    {
        return (bool) self::wert($schluessel, false);
    }

    /**
     * Was PHP hoechstens annimmt, in Kilobyte.
     *
     * The smaller of the two counts: post_max_size covers the whole request,
     * upload_max_filesize only the file inside it. The web server has a
     * limit of its own in front of that (in our case nginx
     * client_max_body_size) - PHP cannot see it, hence the note on the page.
     */
    public static function serverGrenzeKb(): int
    {
        $inKb = fn (string $wert) => match (strtolower(substr(trim($wert), -1))) {
            'g' => (int) $wert * 1024 * 1024,
            'm' => (int) $wert * 1024,
            'k' => (int) $wert,
            default => (int) ((int) $wert / 1024),
        };

        return min(
            $inKb((string) ini_get('upload_max_filesize')),
            $inKb((string) ini_get('post_max_size')),
        );
    }

    /** The setting key of a logo place. */
    public static function logoSchluessel(string $stelle): string
    {
        return 'app_logo_'.$stelle;
    }

    /** Path of the logo of one place, or null when none is stored there. */
    public static function logoPfad(string $stelle): ?string
    {
        $pfad = trim((string) self::wert(self::logoSchluessel($stelle)));

        return $pfad !== '' ? $pfad : null;
    }

    /**
     * How many days an activity entry stays. 0 means unlimited.
     *
     * The previous passwords hang on it and go with it: they are what an
     * activity entry about a password change has to show, and a second
     * deadline would be one number too many.
     */
    public const PROTOKOLL_TAGE = 'protokoll_tage';

    /*
    |--------------------------------------------------------------------------
    | Fristen
    |--------------------------------------------------------------------------
    |
    | Die Vorwarnzeiten standen an sieben Stellen einzeln im Code - zweimal
    | dieselbe Zahl mit dem Kommentar, es sei dieselbe. Genau so laufen zwei
    | Werte auseinander: Jemand aendert die eine Stelle und findet die andere
    | nicht. Ab hier gibt es je Frist eine Funktion, und alle lesen sie.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Anmeldung und Sitzung
    |--------------------------------------------------------------------------
    |
    | Die drei Zahlen der Bremse standen zweimal im Code - in LoginRequest und
    | im TwoFactorChallengeController, dort mit dem Kommentar "wie bei der
    | Anmeldung selbst". Ein Kommentar haelt zwei Zahlen nicht zusammen.
    |
    */

    public const DATEI_FORMATE = 'datei_formate';

    /**
     * Which file extensions may be uploaded.
     *
     * Only a selection from config('custom.datei_formate') - what is not in
     * there does not come out here. That is the whole point of this setting:
     * it lets the allowlist be shortened, not extended. A list anyone can
     * enter "php" into is no allowlist, and the way there would not even
     * have to go through the interface - against a row written straight into
     * the table, cutting here is the only help.
     *
     * Without a selection the whole list from the configuration applies.
     */
    public static function fileFormats(): array
    {
        $erlaubt = array_map('strtolower', config('custom.datei_formate', []));
        $eigene = array_filter(explode(',', (string) self::wert(self::DATEI_FORMATE)));

        $auswahl = array_values(array_intersect($erlaubt, array_map('strtolower', $eigene)));

        return $auswahl ?: $erlaubt;
    }

    public const EXPIRY_MAIL_KINDS = 'expiry_mail_kinds';

    /** What an expiry mail can report, in display order. */
    public const EXPIRY_KINDS = ['certificate', 'domain', 'licensesoftware', 'firewall', 'warranty', 'agent'];

    /**
     * Which kinds the expiry mail reports - set once by an admin, valid for
     * every recipient. Nothing chosen means all; switching the mail off is
     * done per recipient, not here.
     */
    public static function expiryMailKinds(): array
    {
        $chosen = array_filter(explode(',', (string) self::wert(self::EXPIRY_MAIL_KINDS)));

        return array_values(array_intersect(self::EXPIRY_KINDS, $chosen)) ?: self::EXPIRY_KINDS;
    }

    public const AUTO_CHECK = 'auto_check';

    /**
     * Whether DokuVault checks domains and certificates itself every night
     * (domains:check). On by default; a server without internet access
     * switches it off instead of logging a timeout per entry every day.
     */
    public static function autoCheck(): bool
    {
        $own = self::wert(self::AUTO_CHECK);

        return $own === null ? true : (bool) $own;
    }

    public const APP_LOCALE = 'app_locale';

    public const ANMELDE_HINWEIS = 'anmelde_hinweis';

    public const SEITE_LISTE = 'seite_liste';

    public const SEITE_ADMIN = 'seite_admin';

    /**
     * The language of the installation.
     *
     * The last stage of SetLocale: user, then session, then browser, then
     * this one. An unknown value is dropped - it would otherwise reach
     * App::setLocale() unchecked from the database.
     */
    public static function sprache(): string
    {
        $eigene = (string) self::wert(self::APP_LOCALE);

        return array_key_exists($eigene, config('custom.locales', []))
            ? $eigene
            : (string) config('app.locale');
    }

    /** A sentence on the sign-in page, for instance who helps with questions. */
    public static function anmeldeHinweis(): string
    {
        return trim((string) self::wert(self::ANMELDE_HINWEIS));
    }

    /** Rows per page in a customer's lists. */
    public static function seiteListe(): int
    {
        return self::zahl(self::SEITE_LISTE, (int) config('custom.seiten.liste'));
    }

    /** Rows per page in the lists of the admin area. */
    public static function seiteAdmin(): int
    {
        return self::zahl(self::SEITE_ADMIN, (int) config('custom.seiten.admin'));
    }

    public const ANMELDUNG_VERSUCHE = 'anmeldung_versuche';

    public const ANMELDUNG_SPERRE = 'anmeldung_sperre';

    public const ANMELDUNG_HERKUNFT = 'anmeldung_herkunft';

    public const SITZUNG_MINUTEN = 'sitzung_minuten';

    public const SITZUNG_SCHLIESSEN = 'sitzung_schliessen';

    public const REMEMBER_TAGE = 'remember_tage';

    /** Failed attempts per account before it is locked. */
    public static function loginAttempts(): int
    {
        return self::zahl(self::ANMELDUNG_VERSUCHE, (int) config('custom.anmeldung.versuche'));
    }

    /** Duration of the lockout in minutes. */
    public static function loginLockoutMinutes(): int
    {
        return self::zahl(self::ANMELDUNG_SPERRE, (int) config('custom.anmeldung.sperre_minuten'));
    }

    /** The same lockout in seconds - that is how the RateLimiter computes. */
    public static function loginLockoutSeconds(): int
    {
        return self::loginLockoutMinutes() * 60;
    }

    /** Failed attempts per origin across all accounts. */
    public static function loginOrigin(): int
    {
        return self::zahl(self::ANMELDUNG_HERKUNFT, (int) config('custom.anmeldung.versuche_je_herkunft'));
    }

    /** How long an idle session lasts, in minutes. */
    public static function sitzungMinuten(): int
    {
        return self::zahl(self::SITZUNG_MINUTEN, (int) config('session.lifetime'));
    }

    /**
     * Sign out with the browser window.
     *
     * The session only. Whoever ticked "stay signed in" gets back in all the
     * same - there is a cookie of its own for that, with its own lifetime.
     */
    public static function sitzungBeimSchliessen(): bool
    {
        $eigene = self::wert(self::SITZUNG_SCHLIESSEN);

        return $eigene === null ? (bool) config('session.expire_on_close') : (bool) $eigene;
    }

    /** Wie lange "Angemeldet bleiben" gilt, in Tagen. */
    public static function rememberTage(): int
    {
        return self::zahl(self::REMEMBER_TAGE, (int) config('custom.remember_days', 30));
    }

    /**
     * Read a number: own value, otherwise the default.
     *
     * 0 counts as "not set". Zero attempts would be a sign-in that lets
     * nobody through, zero minutes a lockout that is none - both otherwise
     * come out of a field someone emptied.
     */
    private static function zahl(string $schluessel, int $standard): int
    {
        $eigene = (int) self::wert($schluessel);

        return $eigene > 0 ? $eigene : $standard;
    }

    public const FRIST_VERTRAEGE = 'frist_vertraege';

    public const FRIST_GARANTIE = 'frist_garantie';

    public const FRIST_EOL = 'frist_eol';

    public const PDF_STUNDEN = 'pdf_stunden';

    public const BACKUP_VERLAUF = 'backup_verlauf';

    public const BACKUP_AUFBEWAHRUNG = 'backup_aufbewahrung';

    /** How many runs per backup are shown - admin overview and customer card (5-50, default 10). */
    public static function backupHistory(): int
    {
        $wert = (int) self::wert(self::BACKUP_VERLAUF);

        return $wert >= 5 && $wert <= 50 ? $wert : 10;
    }

    /**
     * How many runs per backup are kept before the oldest are deleted
     * (default 100). Never fewer than are shown.
     */
    public static function backupAufbewahrung(): int
    {
        $wert = (int) self::wert(self::BACKUP_AUFBEWAHRUNG);

        return max($wert >= 10 && $wert <= 10000 ? $wert : 100, self::backupHistory());
    }

    /*
     * Formerly fixed in code or config, now under Admin -> Einstellungen.
     * Each with its default and its range; a value outside the range (a
     * typo, an empty field) falls back to the default instead of switching
     * something off.
     */
    public const AGENT_INTERVALL = 'agent_intervall';

    public const AGENT_STILL_STUNDEN = 'agent_still_stunden';

    public const AGENT_TOKEN_TAGE = 'agent_token_tage';

    public const AGENT_TOKEN_MAX_TAGE = 'agent_token_max_tage';

    public const STATISTIK_API_TAGE = 'statistik_api_tage';

    public const STATISTIK_MONATE = 'statistik_monate';

    public const KENNWORT_ABRUFE = 'kennwort_abrufe';

    /** [default, min, max] per setting. */
    public const ZAHLEN = [
        self::AGENT_STILL_STUNDEN => [3, 1, 168],
        self::AGENT_TOKEN_TAGE => [365, 1, 3650],
        self::AGENT_TOKEN_MAX_TAGE => [1095, 1, 3650],
        self::STATISTIK_API_TAGE => [90, 7, 730],
        self::STATISTIK_MONATE => [24, 1, 120],
        self::KENNWORT_ABRUFE => [30, 1, 1000],
    ];

    /** A number setting from ZAHLEN, inside its range or its default. */
    public static function clamped(string $schluessel): int
    {
        [$standard, $min, $max] = self::ZAHLEN[$schluessel];
        $wert = (int) self::wert($schluessel);

        return $wert >= $min && $wert <= $max ? $wert : $standard;
    }

    /** Interval of an agent without its own (minutes, one of custom.agent_intervalle). */
    public static function agentIntervall(): int
    {
        $wert = (int) self::wert(self::AGENT_INTERVALL);

        return array_key_exists($wert, config('custom.agent_intervalle'))
            ? $wert
            : (int) config('custom.agent_intervall_standard');
    }

    /** After how many hours without contact an agent counts as silent. */
    public static function agentSilentHours(): int
    {
        return self::clamped(self::AGENT_STILL_STUNDEN);
    }

    /** Validity of a new or renewed agent token, never above the maximum. */
    public static function agentTokenDays(): int
    {
        return min(self::clamped(self::AGENT_TOKEN_TAGE), self::agentTokenMaxDays());
    }

    public static function agentTokenMaxDays(): int
    {
        return self::clamped(self::AGENT_TOKEN_MAX_TAGE);
    }

    /** How long the API statistics (per hour and endpoint) are kept. */
    public static function statistikApiTage(): int
    {
        return self::clamped(self::STATISTIK_API_TAGE);
    }

    /** How long the nightly snapshots (system, data growth) are kept. */
    public static function statistikMonate(): int
    {
        return self::clamped(self::STATISTIK_MONATE);
    }

    /** Passwords one user may fetch per minute before the brake holds. */
    public static function kennwortAbrufe(): int
    {
        return self::clamped(self::KENNWORT_ABRUFE);
    }

    /** Warning time for licences, certificates and domains, in days. */
    public static function contractDeadline(): int
    {
        return self::deadline(self::FRIST_VERTRAEGE, 'vertraege_tage');
    }

    /** Warning time for expiring warranties, in days. */
    public static function warrantyDeadline(): int
    {
        return self::deadline(self::FRIST_GARANTIE, 'garantie_tage');
    }

    /** Warning time for the support end of operating systems, in days. */
    public static function eolDeadline(): int
    {
        return self::deadline(self::FRIST_EOL, 'eol_tage');
    }

    /**
     * How long a finished PDF export stays, in hours.
     *
     * The cleanup runs hourly, not at the moment the deadline passes - so a
     * file lies around up to an hour longer. Set one hour and you get up to
     * two.
     */
    public static function pdfStunden(): int
    {
        // Capped on read as well: a value written past the form (database,
        // import) must not keep exports for years.
        return min(self::deadline(self::PDF_STUNDEN, 'pdf_stunden'), 8760);
    }

    /**
     * Read a deadline: own value, otherwise the one from the configuration.
     *
     * Null and 0 mean the same here as "not set". A warning time of zero
     * days would not be a setting but a switched-off warning - and one does
     * not switch that off by accident through an empty field.
     */
    private static function deadline(string $schluessel, string $konfiguration): int
    {
        $eigene = (int) self::wert($schluessel);

        return $eigene > 0 ? $eigene : (int) config('custom.fristen.'.$konfiguration);
    }

    /** Retention of the activity log in days, 0 when unlimited. */
    public static function activityDays(): int
    {
        return (int) self::wert(self::PROTOKOLL_TAGE, 0);
    }

    /**
     * All settings at once - not one per key.
     *
     * The reason is a subtle one: Cache::rememberForever treats null as "not
     * in the cache" and calls the callback again. With the default setting
     * there is no row at all, so the value would always be null - and the
     * remote-access button in a list of 25 devices would have triggered 25
     * queries instead of none. Measured: 114 queries for a page that does
     * with eight. An array is never null and gets cached.
     */
    private static function alle(): array
    {
        // once() instead of a static property: that would have outlived the
        // process - between two tests, and in a queue worker across a change
        // as well. once() applies to the request and is reset by the
        // Testumgebung zurueckgesetzt.
        return once(fn () => Cache::rememberForever(
            'settings.alle',
            fn () => static::query()->pluck('value', 'key')->all()
        ));
    }

    public static function wert(string $key, $standard = null)
    {
        return self::alle()[$key] ?? $standard;
    }

    public static function setzen(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.alle');

        // once() holds the old value until the end of the request - when
        // saving a setting that is exactly the wrong way round. In everyday
        // use this line never runs, only when an admin presses save.
        Once::flush();
    }

    /**
     * The configured remote-access solution with its labels and its URL
     * pattern. Falls back to RustDesk - that was the hard-wired solution
     * before this setting existed.
     */
    public static function remoteAccess(): array
    {
        $tools = config('custom.remote_tools');
        $key = static::wert(self::REMOTE_TOOL, 'rustdesk');
        $tool = $tools[$key] ?? $tools['rustdesk'];

        if ($key === 'custom') {
            $tool['url'] = static::wert(self::REMOTE_PATTERN) ?: '';
        }

        return $tool + ['key' => $key];
    }

    /**
     * The finished connection link for a device - or null when the details
     * are missing or the solution knows no link.
     */
    public static function remoteAccessLink(?string $id, ?string $passwort): ?string
    {
        $tool = static::remoteAccess();

        if (blank($id) || blank($tool['url'])) {
            return null;
        }

        // A pattern with {password} needs a password too - otherwise a link
        // would come out that leads to the password prompt instead of the
        if (str_contains($tool['url'], '{password}') && blank($passwort)) {
            return null;
        }

        return str_replace(
            ['{id}', '{password}'],
            [rawurlencode($id), rawurlencode((string) $passwort)],
            $tool['url']
        );
    }
}
