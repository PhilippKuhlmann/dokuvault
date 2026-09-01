<?php

namespace App\Support;

use App\Models\Setting;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * Zeitpunkte in der eingestellten Zeitzone anzeigen.
 *
 * Gespeichert wird weiter in UTC - daran wird bewusst nicht gerührt. Würde man
 * stattdessen app.timezone umstellen, schriebe die Anwendung ab dann lokale
 * Zeiten in dieselben Spalten, in denen bereits UTC steht: zwei Zeitzonen in
 * einer Spalte, ohne Merkmal, welche Zeile welche ist. Umgerechnet wird
 * deshalb erst beim Anzeigen.
 *
 * Datumsangaben ohne Uhrzeit (Ablaufdatum, Beschaffungsdatum) laufen absichtlich
 * nicht hierdurch: Ein Datum um Stunden zu verschieben kann es auf den Vortag
 * kippen lassen.
 */
class Zeit
{
    /** Ohne Einstellung bleibt es bei dem, was die Konfiguration sagt. */
    public static function zone(): string
    {
        $eigene = trim((string) Setting::wert(Setting::APP_TIMEZONE));

        return $eigene !== '' && in_array($eigene, DateTimeZone::listIdentifiers(), true)
            ? $eigene
            : (string) config('app.timezone');
    }

    /**
     * Ein Zeitpunkt in der eingestellten Zone.
     *
     * @param  string  $wenn_leer  Was dasteht, wenn es den Zeitpunkt nicht gibt -
     *                             "noch nie" liest sich besser als eine leere Zelle.
     */
    public static function anzeigen(?DateTimeInterface $wert, string $format = 'd.m.Y H:i', string $wenn_leer = ''): string
    {
        if ($wert === null) {
            return $wenn_leer;
        }

        return Carbon::instance($wert)->setTimezone(self::zone())->format($format);
    }

    /**
     * Die Zonen zur Auswahl: Europa und UTC.
     *
     * Nicht alle 400 Kennungen der Welt - die Liste soll benutzbar bleiben.
     * Wer eine andere braucht, kann sie in der Konfiguration setzen; die
     * Einstellung überschreibt sie dann nur, wenn sie hier auch auswählbar ist.
     *
     * @return array<int, string>
     */
    public static function auswahl(): array
    {
        return array_values(array_unique(array_merge(
            ['UTC'],
            DateTimeZone::listIdentifiers(DateTimeZone::EUROPE),
        )));
    }
}
