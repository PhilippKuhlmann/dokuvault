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
     * Positivliste, und zwar nur vollständige Adressen: http und https.
     * Andersherum müsste man jedes Schema kennen, das ein Browser ausführt -
     * javascript, data, vbscript, und das nächste, das dazukommt.
     *
     * Ein Wert, der mit "/" beginnt, zählt ausdrücklich nicht dazu. Er sah
     * einen Nachmittag lang nach einem Pfad innerhalb der Anwendung aus, bis
     * in der VLAN-Liste "/24" stand - eine Netzmaske, die prompt als Link auf
     * eine Seite "/24" ausgegeben wurde. Aus dem Wert allein lässt sich das
     * nicht unterscheiden. Wer einen anwendungsinternen Link ausgeben will,
     * gibt eine vollständige Adresse mit - dort weiß die Ansicht, dass es
     * einer ist; hier ist es geraten.
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

    /**
     * Ein Pfad innerhalb dieser Anwendung, etwa "/mustermann".
     *
     * Getrennt von sicher(), und das ist der Punkt: Hier hat die Ansicht
     * bereits entschieden, dass ein Link gemeint ist - sie übergibt den Wert
     * unter einem eigenen Schlüssel. Geraten wird nichts mehr; die Netzmaske
     * "/24" steht unter "CIDR" und kommt hier gar nicht an.
     *
     * Geprüft wird trotzdem, damit die nächste Verwendung nicht aus Versehen
     * nach draußen führt: "//example.test" ist protokoll-relativ, und
     * "/\example.test" behandeln Browser genauso.
     */
    public static function pfad(mixed $wert): ?string
    {
        $pfad = trim((string) $wert);

        if (! str_starts_with($pfad, '/')
            || str_starts_with($pfad, '//')
            || str_starts_with($pfad, '/\\')) {
            return null;
        }

        return $pfad;
    }
}
