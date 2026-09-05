<?php

namespace App\Livewire;

use App\Models\PasswordHistory;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Das bisherige Kennwort in einer Protokollzeile - auf Klick.
 *
 * Der Wert steht nicht im Protokolleintrag: Der bleibt ewig stehen und listet
 * alle Kunden auf einer Seite. Er kommt aus der Kennwort-Historie und
 * verschwindet mit deren Frist. Fuer den Betrachter ist das derselbe Handgriff
 * wie bei jeder anderen Aenderung.
 */
class ProtokollKennwort extends Component
{
    /** Ids der Historie-Eintraege, die zu dieser Zeile gehoeren. */
    #[Locked]
    public array $ids = [];

    /** Beschriftungen der Felder, damit klar ist, welches Kennwort gemeint ist. */
    #[Locked]
    public array $felder = [];

    public bool $offen = false;

    /** Geladene Werte - erst nach dem Klick gefuellt. */
    public array $werte = [];

    public function zeigen(): void
    {
        // Dasselbe Recht wie die Seite, auf der die Komponente steht: Wer das
        // Protokoll sehen darf, sieht auch, was vorher im Feld stand. Geprueft
        // wird trotzdem hier - der Aufruf kommt aus dem Browser.
        Gate::authorize('admin_activity');

        $this->werte = PasswordHistory::whereIn('id', $this->ids)
            ->get()
            ->map(fn ($eintrag) => [
                'feld' => config('custom.secret_field_labels')[$eintrag->field] ?? $eintrag->field,
                'wert' => $eintrag->value,
            ])
            ->all();

        $this->offen = true;
    }

    public function verbergen(): void
    {
        $this->werte = [];
        $this->offen = false;
    }

    public function render()
    {
        return view('livewire.protokoll-kennwort');
    }
}
