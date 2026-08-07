<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Eine Adresse muss innerhalb des angegebenen Netzes liegen.
 *
 * Der haeufigste Tippfehler beim Dokumentieren eines gerouteten Netzes ist ein
 * Gateway aus dem falschen Block - das faellt sonst erst auf, wenn jemand danach
 * sucht.
 */
class LiegtImNetz implements ValidationRule
{
    public function __construct(protected ?string $netz) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || blank($this->netz) || ! str_contains((string) $this->netz, '/')) {
            return;
        }

        $adresse = @inet_pton((string) $value);
        if ($adresse === false) {
            $fail('Das ist keine gueltige IP-Adresse.');

            return;
        }

        [$netzAdresse, $praefix] = explode('/', (string) $this->netz, 2);
        $netzRoh = @inet_pton($netzAdresse);

        // Ungueltiges Netz meldet bereits IstNetz - hier nichts doppelt sagen.
        if ($netzRoh === false || ! ctype_digit($praefix) || strlen($netzRoh) !== strlen($adresse)) {
            return;
        }

        if (IstNetz::netzadresse($adresse, (int) $praefix) !== IstNetz::netzadresse($netzRoh, (int) $praefix)) {
            $fail('Die Adresse liegt nicht im angegebenen Netz '.$this->netz.'.');
        }
    }
}
