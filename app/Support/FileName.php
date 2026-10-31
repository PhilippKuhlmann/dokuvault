<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Build a file name from a label and an uploaded file that is nothing but a
 * file name.
 *
 * What prompted this: the label went into the storage path unchecked. With
 * "../../../../other-customer/files/x" the file landed in another customer's
 * directory - and overwrote what was there. Reproduced and proven before
 * this class came into being.
 *
 * The extension gets through just as unchecked: it comes from the file name
 * the browser sends along, and the sender decides that.
 */
class FileName
{
    /** What is left of a label: letters, digits, _ and -. */
    public static function bereinigen(string $roh): string
    {
        $sauber = preg_replace('/[^A-Za-z0-9_-]+/', '_', $roh);

        // Underscores only means: there was nothing usable in it.
        return trim($sauber, '_') !== '' ? trim($sauber, '_') : 'datei';
    }

    /**
     * The complete name to store under.
     *
     * The timestamp stands at the front so two files of the same name do not
     * overwrite each other - it is no protection against the escape, and never
     * was: a "../" behind it still takes effect.
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
