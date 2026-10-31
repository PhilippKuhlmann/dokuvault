<?php

namespace App\Support;

/**
 * Erzeugt Feldwerte fuer ein Formular.
 *
 * Damit bleibt das generische Modal generisch: Es kennt nur "dieser Typ hat
 * einen Erzeuger" (config/forms.php), nicht dessen Innenleben.
 */
interface FieldGenerator
{
    /**
     * @param  array<string, mixed>  $form  Der bisherige Formularstand
     * @return array<string, mixed> Nur die Felder, die gesetzt werden sollen
     */
    public function generate(array $form): array;
}
