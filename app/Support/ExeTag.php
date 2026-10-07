<?php

namespace App\Support;

/**
 * Appends data (URL and token of the agent) to the Windows service exe
 * without breaking its Authenticode signature.
 *
 * Unsigned exe: the data simply goes at the end - Windows ignores bytes
 * after the PE image. Signed exe: the signature table (attribute
 * certificate table) is the end of the file, and bytes behind it make
 * Windows report the signature as broken. So the data goes into that
 * table: its size in the security data directory and the length of the
 * last WIN_CERTIFICATE grow by the appended bytes, padded to 8. Windows
 * does not hash the certificate table, so the signature stays valid - the
 * same way Chrome's installers carry their tag.
 *
 * The exe still finds its data at its own last bytes
 * (agent/windows-service/embedded.go), so it reads both forms alike.
 */
class ExeTag
{
    public static function anhaengen(string $exe, string $daten): string
    {
        $sicherheit = self::sicherheitsVerzeichnis($exe);

        // Not signed, or the table is not at the end: plain append.
        if (! $sicherheit || strlen($exe) !== $sicherheit['offset'] + $sicherheit['groesse']) {
            return $exe.$daten;
        }

        // The certificate table is 8-byte aligned. The padding goes in front
        // of the data, so the end of the file stays what the exe looks for.
        $fuellung = (8 - strlen($daten) % 8) % 8;
        $zusatz = str_repeat("\0", $fuellung).$daten;
        $neu = $exe.$zusatz;

        // Size in the data directory.
        $neu = substr_replace($neu, pack('V', $sicherheit['groesse'] + strlen($zusatz)), $sicherheit['feld'] + 4, 4);

        // dwLength of the last WIN_CERTIFICATE in the table.
        $letzter = self::letztesZertifikat($exe, $sicherheit['offset'], $sicherheit['groesse']);
        if ($letzter !== null) {
            $laenge = unpack('V', substr($exe, $letzter, 4))[1];
            $neu = substr_replace($neu, pack('V', $laenge + strlen($zusatz)), $letzter, 4);
        }

        return $neu;
    }

    /**
     * Where the security data directory says the certificate table is.
     * ['feld' => offset of the directory entry, 'offset', 'groesse'] or null.
     */
    public static function sicherheitsVerzeichnis(string $exe): ?array
    {
        if (strlen($exe) < 0x40 || substr($exe, 0, 2) !== 'MZ') {
            return null;
        }
        $pe = unpack('V', substr($exe, 0x3C, 4))[1];
        if (substr($exe, $pe, 4) !== "PE\0\0") {
            return null;
        }

        $optional = $pe + 24;
        $magie = unpack('v', substr($exe, $optional, 2))[1];
        $verzeichnisse = match ($magie) {
            0x10B => $optional + 96,   // PE32
            0x20B => $optional + 112,  // PE32+
            default => null,
        };
        if ($verzeichnisse === null) {
            return null;
        }

        // Entry 4 = IMAGE_DIRECTORY_ENTRY_SECURITY; a file offset, not an RVA.
        $feld = $verzeichnisse + 4 * 8;
        [$offset, $groesse] = array_values(unpack('V2', substr($exe, $feld, 8)));

        return $offset > 0 && $groesse > 0 ? ['feld' => $feld, 'offset' => $offset, 'groesse' => $groesse] : null;
    }

    /** Offset of the last WIN_CERTIFICATE in the table. */
    protected static function letztesZertifikat(string $exe, int $offset, int $groesse): ?int
    {
        $ende = $offset + $groesse;
        $letzter = null;
        while ($offset + 8 <= $ende) {
            $laenge = unpack('V', substr($exe, $offset, 4))[1];
            if ($laenge < 8) {
                break;
            }
            $letzter = $offset;
            $offset += ($laenge + 7) & ~7;
        }

        return $letzter;
    }

    public static function istSigniert(string $exe): bool
    {
        return self::sicherheitsVerzeichnis($exe) !== null;
    }
}
