<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
