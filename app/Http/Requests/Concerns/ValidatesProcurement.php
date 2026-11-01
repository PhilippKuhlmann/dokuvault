<?php

namespace App\Http\Requests\Concerns;

/**
 * The procurement fields are the same for every device - repeating the same
 * four rules seventeen times would be an invitation to let them drift on the
 * achtzehnten Geraeteart zu vergessen.
 *
 * It is pulled in by spread in rules() and attributes(), so that the
 * jeweilige Request seine eigenen Regeln behaelt.
 */
trait ValidatesProcurement
{
    protected function procurementRules(): array
    {
        return [
            'purchase_date' => 'nullable|date',
            // No after:purchase_date: on legacy devices the warranty is often
            // added later without the purchase date being known.
            'warranty_until' => 'nullable|date',
            'eol_date' => 'nullable|date',
            'supplier' => 'nullable|max:255',
        ];
    }

    protected function procurementLabels(): array
    {
        return [
            'purchase_date' => 'Kaufdatum',
            'warranty_until' => 'Garantie bis',
            'eol_date' => 'Support-Ende (EOL)',
            'supplier' => 'Lieferant',
        ];
    }
}
