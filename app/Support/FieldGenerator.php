<?php

namespace App\Support;

/**
 * Generates field values for a form.
 *
 * That keeps the generic modal generic: it only knows "this kind has a
 * generator" (config/forms.php), not what goes on inside it.
 */
interface FieldGenerator
{
    /**
     * @param  array<string, mixed>  $form  The form as it stands so far
     * @return array<string, mixed> Only the fields that should be set
     */
    public function generate(array $form): array;
}
