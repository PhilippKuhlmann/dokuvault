<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ein Netz in CIDR-Schreibweise, z. B. 203.0.113.16/28 oder 2001:db8::/64.
 *
 * Verlangt die echte Netzadresse: Wer 203.0.113.17/28 eintraegt, meint das Netz
 * .16/28 und haette in der Doku eine Adresse stehen, die kein Netz ist. Die
 * Meldung nennt die richtige, statt still zu korrigieren - eine stille Korrektur
 * waere in einer Dokumentation die schlechtere Antwort.
 */
class IstNetz implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (substr_count((string) $value, '/') !== 1) {
            $fail('Bitte das Netz in CIDR-Schreibweise angeben, z. B. 203.0.113.16/28.');

            return;
        }

        [$adresse, $praefix] = explode('/', (string) $value, 2);
        $roh = @inet_pton($adresse);

        if ($roh === false || ! ctype_digit($praefix)) {
            $fail('Bitte das Netz in CIDR-Schreibweise angeben, z. B. 203.0.113.16/28.');

            return;
        }

        $bits = strlen($roh) * 8;
        if ((int) $praefix > $bits) {
            $fail("Die Praefixlaenge darf hoechstens /{$bits} sein.");

            return;
        }

        $netz = self::netzadresse($roh, (int) $praefix);
        if ($netz !== $roh) {
            $fail('Das ist keine Netzadresse. Gemeint ist vermutlich '.inet_ntop($netz).'/'.(int) $praefix.'.');
        }
    }

    /** Hostbits auf null setzen. */
    public static function netzadresse(string $roh, int $praefix): string
    {
        $bytes = str_split($roh);
        foreach ($bytes as $i => $byte) {
            $uebrig = $praefix - $i * 8;
            $maske = $uebrig >= 8 ? 0xFF : ($uebrig <= 0 ? 0x00 : (0xFF << (8 - $uebrig)) & 0xFF);
            $bytes[$i] = chr(ord($byte) & $maske);
        }

        return implode('', $bytes);
    }
}
