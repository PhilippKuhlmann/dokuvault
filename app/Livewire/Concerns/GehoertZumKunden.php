<?php

namespace App\Livewire\Concerns;

/**
 * Der angemeldete Nutzer muss zu dem Kunden gehoeren, mit dem die Komponente
 * arbeitet.
 *
 * Warum die Komponente das selbst pruefen muss: Die isCustomer-Middleware
 * haengt an den Routen, und Livewire ruft nicht ueber sie, sondern ueber
 * /livewire/update. Wer den Kunden als Parameter entgegennimmt, nimmt ihn
 * damit von aussen entgegen.
 *
 * Ein Nutzer ohne customer_id - Techniker, Admin - arbeitet fuer alle Kunden
 * und wird nicht aufgehalten; fuer ihn entscheiden die Rechte.
 */
trait GehoertZumKunden
{
    protected function nurEigenerKunde(int $customerId): void
    {
        $nutzer = auth()->user();

        abort_if($nutzer && $nutzer->customer_id && $nutzer->customer_id !== $customerId, 403);
    }
}
