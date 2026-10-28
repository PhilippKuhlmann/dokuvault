<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Aus einer Bezeichnung und einer hochgeladenen Datei einen Dateinamen bauen,
 * der nur ein Dateiname ist.
 *
 * Der Anlass: Die Bezeichnung ging ungeprüft in den Ablagepfad. Mit
 * "../../../../fremder-kunde/files/x" landete die Datei im Verzeichnis eines
 * anderen Kunden - und überschrieb, was dort lag. Nachgestellt und belegt,
 * bevor diese Klasse entstand.
 *
 * Die Endung kommt genauso wenig ungeprüft durch: Sie stammt aus dem
 * Dateinamen, den der Browser mitschickt, und den bestimmt der Absender.
 */
class Dateiname
{
    /** Was von einer Bezeichnung übrig bleibt: Buchstaben, Ziffern, _ und -. */
    public static function bereinigen(string $roh): string
    {
        $sauber = preg_replace('/[^A-Za-z0-9_-]+/', '_', $roh);

        // Nur Unterstriche heisst: Es war nichts Brauchbares dabei.
        return trim($sauber, '_') !== '' ? trim($sauber, '_') : 'datei';
    }

    /**
     * Der vollständige Name unter dem abgelegt wird.
     *
     * Der Zeitstempel steht vorn, damit zwei gleichnamige Dateien sich nicht
     * überschreiben - er ist kein Schutz gegen den Ausbruch, das war er nie:
     * Ein "../" dahinter wirkt trotzdem.
     */
    public static function fuer(UploadedFile $datei, ?string $bezeichnung = null): string
    {
        $name = self::bereinigen((string) ($bezeichnung ?: pathinfo($datei->getClientOriginalName(), PATHINFO_FILENAME)));

        return time().'_'.$name.'.'.self::extension($datei);
    }

    /**
     * The extension as stored - cleaned like the name. It comes from the
     * browser and later ends up in the download file name; taken raw, it
     * could carry quotes or line breaks into that header.
     */
    public static function extension(UploadedFile $file): string
    {
        return strtolower(self::bereinigen($file->getClientOriginalExtension()));
    }
}
