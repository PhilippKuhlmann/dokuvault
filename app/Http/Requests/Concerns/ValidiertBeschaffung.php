<?php

namespace App\Http\Requests\Concerns;

/**
 * Die Beschaffungsfelder gelten fuer jedes Geraet gleich - siebzehnmal die
 * gleichen vier Regeln zu wiederholen waere eine Einladung, sie bei der
 * achtzehnten Geraeteart zu vergessen.
 *
 * Eingebunden wird das per Spread in rules() bzw. attributes(), damit der
 * jeweilige Request seine eigenen Regeln behaelt.
 */
trait ValidiertBeschaffung
{
    protected function beschaffungRegeln(): array
    {
        return [
            'purchase_date' => 'nullable|date',
            // Kein after:purchase_date: Bei Bestandsgeraeten wird die Garantie
            // oft nachgetragen, ohne dass das Kaufdatum bekannt ist.
            'warranty_until' => 'nullable|date',
            'eol_date' => 'nullable|date',
            'supplier' => 'nullable|max:255',
        ];
    }

    protected function beschaffungBezeichnungen(): array
    {
        return [
            'purchase_date' => 'Kaufdatum',
            'warranty_until' => 'Garantie bis',
            'eol_date' => 'Support-Ende (EOL)',
            'supplier' => 'Lieferant',
        ];
    }
}
