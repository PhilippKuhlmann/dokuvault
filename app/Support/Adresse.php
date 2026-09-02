<?php

namespace App\Support;

/**
 * Eine Adresse, die sich anklicken lässt - oder eben nicht.
 *
 * Der Anlass: Mehrere URL-Felder werden nur auf ihre Länge geprüft, nicht auf
 * ihr Schema. Sie landen aber als href in einer Karte. Steht dort
 * "javascript:...", führt jeder Klick den Code in der Sitzung dessen aus, der
 * klickt - und das ist selten der, der ihn eingetragen hat.
 *
 * Geprüft wird beim Anzeigen, nicht nur beim Speichern: In den Feldern steht
 * längst, was jemand eingetragen hat, bevor es eine Regel dafür gab. Und die
 * Regel darf nicht streng sein - dort steht oft nur "192.168.178.1", und das
 * soll weiterhin gehen. Es wird dann nur nicht verlinkt.
 */
class Adresse
{
    /**
     * Die Adresse, wenn man sie gefahrlos verlinken kann - sonst null.
     *
     * Positivliste: Nur http und https. Andersherum müsste man jedes Schema
     * kennen, das ein Browser ausführt - javascript, data, vbscript, und das
     * nächste, das dazukommt.
     */
    public static function sicher(mixed $wert): ?string
    {
        $adresse = trim((string) $wert);

        if ($adresse === '') {
            return null;
        }

        $schema = strtolower((string) parse_url($adresse, PHP_URL_SCHEME));

        return in_array($schema, ['http', 'https'], true) ? $adresse : null;
    }
}
