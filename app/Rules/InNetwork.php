<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An address has to lie inside the given network.
 *
 * The most common typo when documenting a routed network is a gateway from
 * the wrong block - that otherwise only shows when somebody looks for it
 * later.
 */
class InNetwork implements ValidationRule
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

        // An invalid network is already reported by IsNetwork - nothing twice here.
        if ($netzRoh === false || ! ctype_digit($praefix) || strlen($netzRoh) !== strlen($adresse)) {
            return;
        }

        if (IsNetwork::networkAddress($adresse, (int) $praefix) !== IsNetwork::networkAddress($netzRoh, (int) $praefix)) {
            $fail('Die Adresse liegt nicht im angegebenen Netz '.$this->netz.'.');
        }
    }
}
