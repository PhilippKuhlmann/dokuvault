<?php

namespace App\Livewire;

use App\Jobs\KundenPdfErzeugen;
use App\Livewire\Concerns\GehoertZumKunden;
use App\Models\Customer;
use App\Models\PdfExport;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Knopf und Stand der PDF-Ausgabe.
 *
 * Das PDF entsteht im Hintergrund - ohne diese Anzeige waere nach dem Klick
 * nicht zu erkennen, ob etwas passiert. Nachgefragt wird nur, solange ein
 * Auftrag laeuft; ist er fertig, hoert das Nachfragen auf.
 */
class PdfExportStatus extends Component
{
    use GehoertZumKunden;

    #[Locked]
    public int $customerId;

    public function mount(Customer $customer): void
    {
        $this->nurEigenerKunde($customer->id);

        $this->customerId = $customer->id;
    }

    public function starten(): void
    {
        Gate::authorize('create_pdf');

        $kunde = Customer::findOrFail($this->customerId);

        // Kein zweiter Auftrag, solange einer laeuft: Wer zweimal klickt, soll
        // nicht zweimal denselben Berg Arbeit ausloesen.
        if ($this->auftrag()?->laeuftNoch()) {
            return;
        }

        $export = PdfExport::create([
            'customer_id' => $kunde->id,
            'user_id' => auth()->id(),
            'status' => PdfExport::OFFEN,
        ]);

        KundenPdfErzeugen::dispatch($export->id);

        $this->dispatch('hinweis', text: __('PDF wird erstellt.'));
    }

    /**
     * Der letzte Auftrag dieses Nutzers zu diesem Kunden.
     *
     * Je Nutzer, nicht je Kunde: Das PDF enthaelt alle Zugangsdaten, es darf
     * also nur abholen, wer es bestellt hat.
     */
    public function auftrag(): ?PdfExport
    {
        return PdfExport::where('customer_id', $this->customerId)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();
    }

    public function render()
    {
        $auftrag = $this->auftrag();

        return view('livewire.pdf-export-status', [
            'auftrag' => $auftrag,
            'kunde' => Customer::find($this->customerId),
            // Nur nachfragen, solange etwas laeuft - und nicht endlos, wenn
            // niemand die Warteschlange abarbeitet.
            'nachfragen' => (bool) $auftrag?->laeuftNoch() && ! $auftrag->haengt(),
        ]);
    }
}
