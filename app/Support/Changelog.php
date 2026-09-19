<?php

namespace App\Support;

/**
 * Der Changelog als Quelle der Versionsnummer.
 *
 * Die Nummer im Schriftkopf der Anmeldeseite und in der Seitenleiste ist die
 * oberste Überschrift von changelog.md. Das ist bewusst so: Eine Version, die
 * an einer zweiten Stelle gepflegt werden müsste, läuft irgendwann gegen den
 * Changelog, und dann stimmt eine von beiden nicht mehr.
 *
 * Gelesen wird die Datei einmal je Anfrage. Vorher stand das
 * file_get_contents samt Regex in einem View-Composer auf '*' - und
 * Blade-Komponenten sind Views: Eine Seite mit vierzig Komponenten las die
 * Datei vierzigmal von der Platte. Sichtbar war davon nichts, messbar schon.
 *
 * Absichtlich kein Cache-Eintrag, sondern nur für die Dauer der Anfrage
 * gemerkt: Ein gecachter Wert überlebte das Bearbeiten des Changelogs, und die
 * angezeigte Version wäre danach still falsch - bis jemand den Cache leert.
 * Ein Dateizugriff je Anfrage ist der Preis dafür, dass das nicht passieren
 * kann.
 */
class Changelog
{
    protected static ?string $roh = null;

    protected static ?string $version = null;

    /** Der vollständige Changelog, wie er auf der Platte steht. */
    public static function roh(): string
    {
        return static::$roh ??= file_get_contents(base_path('changelog.md'));
    }

    /**
     * Die oberste Überschrift, etwa "26.09.19".
     *
     * Findet sich keine, bleibt es bei "Unbekannt" - eine geratene Nummer wäre
     * schlimmer als keine.
     */
    public static function version(): string
    {
        if (static::$version !== null) {
            return static::$version;
        }

        preg_match('/## (\d{2}\.\d{2}\.\d{2})/', static::roh(), $treffer);

        return static::$version = $treffer[1] ?? 'Unbekannt';
    }
}
