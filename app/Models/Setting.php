<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Once;

/**
 * Einstellungen einer Installation.
 *
 * Gelesen wird ueber den Cache: Der Fernwartungsknopf steht in jeder
 * Geraeteliste, das waere sonst eine Abfrage je Seite fuer einen Wert, der
 * sich im Monat einmal aendert.
 */
class Setting extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Schluessel der Fernwartungsloesung, z. B. "rustdesk". */
    public const REMOTE_TOOL = 'remote_tool';

    /** Eigenes URL-Muster, wenn als Loesung "custom" gewaehlt ist. */
    public const REMOTE_PATTERN = 'remote_pattern';

    /** Eigener Name der Installation, statt des Namens aus der .env. */
    public const APP_NAME = 'app_name';

    /** Groesste erlaubte Datei in Kilobyte - siehe uploadMaxKb(). */
    public const UPLOAD_MAX_KB = 'upload_max_kb';

    /**
     * Zeitzone der Anzeige. Gespeichert wird weiter in UTC - siehe
     * App\Support\Zeit.
     */
    public const APP_TIMEZONE = 'app_timezone';

    /*
     * Der Mailversand. Steht hier etwas, gilt es statt der .env - siehe
     * App\Providers\AppServiceProvider. Wer nichts eintraegt, behaelt die
     * Werte aus der .env, so wie vor dieser Einstellung.
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
     * Pfade der eigenen Logos auf der local-Disk, je Stelle einer.
     *
     * Drei statt eines: Das Logo auf der Anmeldeseite darf gross und breit
     * sein, das in der Kopfzeile muss neben den Namen passen, und ein Favicon
     * ist quadratisch und winzig. In der Praxis sind das verschiedene Dateien.
     */
    public const LOGO_STELLEN = ['login', 'header', 'favicon'];

    /**
     * Der Name, der in Kopfzeile, Titelleiste und PDF steht.
     *
     * Faellt auf config('app.name') zurueck: Wer nichts eintraegt, behaelt den
     * Namen aus der .env - so wie vor dieser Einstellung.
     */
    public static function appName(): string
    {
        $eigener = trim((string) self::wert(self::APP_NAME));

        return $eigener !== '' ? $eigener : (string) config('app.name');
    }

    /**
     * Das SMTP-Kennwort im Klartext, oder null.
     *
     * Es liegt verschluesselt in der Einstellung: Die Werte gehen ueber einen
     * Cache, und ein Kennwort, mit dem sich im Namen der Firma Mail
     * verschicken laesst, hat weder dort noch in einem Datenbank-Abzug etwas
     * im Klartext zu suchen.
     *
     * Der Rueckfall auf den Rohwert ist fuer den Fall, dass jemand die Zeile
     * von Hand gesetzt hat - besser ein Kennwort, das funktioniert, als eine
     * Ausnahme beim ersten Mailversand.
     */
    public static function mailKennwort(): ?string
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

    /** Das SMTP-Kennwort setzen; null loescht es. */
    public static function mailKennwortSetzen(?string $kennwort): void
    {
        self::setzen(self::MAIL_PASSWORD, $kennwort === null || $kennwort === ''
            ? null
            : Crypt::encryptString($kennwort));
    }

    /**
     * Die groesste erlaubte Datei in Kilobyte.
     *
     * Gedeckelt auf das, was der Server ueberhaupt durchlaesst: PHP und der
     * Webserver haben eigene Grenzen, und eine Einstellung darueber waere ein
     * Versprechen, das nicht haelt - der Upload braeche mitten im Hochladen
     * ab, ohne verstaendliche Meldung.
     */
    public static function uploadMaxKb(): int
    {
        $eigene = (int) self::wert(self::UPLOAD_MAX_KB);
        $gewuenscht = $eigene > 0 ? $eigene : (int) config('custom.datei_max_kb');

        return max(1, min($gewuenscht, self::serverGrenzeKb()));
    }

    /**
     * Was PHP hoechstens annimmt, in Kilobyte.
     *
     * Der kleinere der beiden Werte zaehlt: post_max_size umfasst die ganze
     * Anfrage, upload_max_filesize nur die Datei darin. Der Webserver hat
     * davor noch eine eigene Grenze (im Bild: nginx client_max_body_size) -
     * die kann PHP nicht sehen, deshalb steht sie im Hinweis auf der Seite.
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

    /** Der Einstellungs-Schluessel einer Logo-Stelle. */
    public static function logoSchluessel(string $stelle): string
    {
        return 'app_logo_'.$stelle;
    }

    /** Pfad des Logos einer Stelle, oder null wenn dort keines hinterlegt ist. */
    public static function logoPfad(string $stelle): ?string
    {
        $pfad = trim((string) self::wert(self::logoSchluessel($stelle)));

        return $pfad !== '' ? $pfad : null;
    }

    /**
     * Wie viele Tage ein Protokolleintrag bleibt. 0 heisst: unbegrenzt.
     *
     * Die bisherigen Kennwoerter haengen daran und gehen mit: Sie sind das,
     * was ein Protokolleintrag ueber eine Kennwortaenderung zu zeigen hat,
     * eine zweite Frist waere eine Zahl zu viel.
     */
    public const PROTOKOLL_TAGE = 'protokoll_tage';

    /** Aufbewahrungsfrist des Protokolls in Tagen, 0 wenn unbegrenzt. */
    public static function protokollTage(): int
    {
        return (int) self::wert(self::PROTOKOLL_TAGE, 0);
    }

    /**
     * Alle Einstellungen auf einmal - nicht je Schluessel einzeln.
     *
     * Der Grund ist ein feiner: Cache::rememberForever behandelt null als
     * "nicht im Cache" und ruft den Callback erneut auf. Bei der
     * Standardeinstellung gibt es gar keine Zeile, der Wert waere also immer
     * null - und der Fernwartungsknopf in einer Liste mit 25 Geraeten haette
     * 25 Abfragen ausgeloest statt keiner. Gemessen: 114 Abfragen fuer eine
     * Seite, die mit acht auskommt. Ein Array ist nie null und wird gecacht.
     */
    private static function alle(): array
    {
        // once() statt einer statischen Eigenschaft: Die haette den Prozess
        // ueberlebt - zwischen zwei Tests, und in einem Queue-Worker auch ueber
        // eine Aenderung hinweg. once() gilt fuer den Request und wird von der
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

        // once() haelt den alten Wert bis zum Ende des Requests - beim
        // Speichern einer Einstellung ist das genau falsch herum. Im Alltag
        // laeuft diese Zeile nie, nur wenn ein Admin auf Speichern drueckt.
        Once::flush();
    }

    /**
     * Die eingestellte Fernwartungsloesung samt ihrer Beschriftungen und ihrem
     * URL-Muster. Faellt auf RustDesk zurueck - das war die fest verdrahtete
     * Loesung, bevor es diese Einstellung gab.
     */
    public static function fernwartung(): array
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
     * Der fertige Verbindungslink fuer ein Geraet - oder null, wenn die
     * Angaben fehlen oder die Loesung keinen Link kennt.
     */
    public static function fernwartungsLink(?string $id, ?string $passwort): ?string
    {
        $tool = static::fernwartung();

        if (blank($id) || blank($tool['url'])) {
            return null;
        }

        // Ein Muster mit {password} braucht auch ein Passwort - sonst entstuende
        // ein Link, der zur Kennwortabfrage fuehrt statt zur Verbindung.
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
