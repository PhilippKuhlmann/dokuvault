<?php

use App\Support\ExeTag;

/** A minimal PE32+ image: headers and a signature table of one WIN_CERTIFICATE. */
function peMitSignatur(?int $zertifikatLaenge = 24): string
{
    $pe = 0x40;
    $kopf = str_pad('MZ', 0x3C, "\0").pack('V', $pe);
    $kopf .= "PE\0\0".str_repeat("\0", 20);          // signature + COFF header
    $optional = pack('v', 0x20B).str_repeat("\0", 110); // magic + up to the data directories
    $verzeichnisse = str_repeat("\0", 16 * 8);
    $bild = str_pad($kopf.$optional.$verzeichnisse, 512, "\0");

    if ($zertifikatLaenge === null) {
        return $bild;
    }

    $tabelle = pack('Vvv', $zertifikatLaenge, 0x0200, 0x0002).str_repeat('S', $zertifikatLaenge - 8);
    $feld = $pe + 24 + 112 + 4 * 8;

    return substr_replace($bild, pack('VV', strlen($bild), strlen($tabelle)), $feld, 8).$tabelle;
}

test('an unsigned exe gets the data plainly at its end', function () {
    $exe = peMitSignatur(null);

    expect(ExeTag::istSigniert($exe))->toBeFalse()
        ->and(ExeTag::anhaengen($exe, 'DATEN'))->toBe($exe.'DATEN');
});

test('a signed exe gets the data inside its signature table', function () {
    $exe = peMitSignatur();
    $daten = '{"token":"x"}'.pack('V', 13).'DVCFG001';

    $neu = ExeTag::anhaengen($exe, $daten);
    $tabelle = ExeTag::sicherheitsVerzeichnis($neu);
    $zertifikat = unpack('V', substr($neu, $tabelle['offset'], 4))[1];

    // The data is still the last bytes - the exe finds it there.
    expect($neu)->toEndWith($daten)
        // The table covers the file to its end, 8-byte aligned...
        ->and($tabelle['offset'] + $tabelle['groesse'])->toBe(strlen($neu))
        ->and($tabelle['groesse'] % 8)->toBe(0)
        // ...and the certificate entry grew with it.
        ->and($zertifikat)->toBe($tabelle['groesse'])
        // Nothing before the table changed but the size field.
        ->and(substr($neu, 0, 0x40))->toBe(substr($exe, 0, 0x40));
});

test('no PE file stays untouched', function () {
    expect(ExeTag::anhaengen('kein exe', 'X'))->toBe('kein exeX')
        ->and(ExeTag::istSigniert('kein exe'))->toBeFalse();
});
