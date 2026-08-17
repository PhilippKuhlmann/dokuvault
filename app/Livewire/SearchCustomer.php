<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\Url;
use Livewire\Component;

class SearchCustomer extends Component
{
    /**
     * Wie viele Kunden die Liste hoechstens zeigt.
     *
     * Vorher holte die Suche alle Treffer. Bei 558 Kunden und einem einzigen
     * Buchstaben als Suchbegriff waren das 70 KB und 100 Millisekunden - die
     * einzige Stelle der Anwendung, die mit dem Bestand linear mitwaechst
     * (alle Listen paginieren, die globale Suche begrenzt je Objekttyp auf 20).
     * Wer einen Kunden sucht, tippt ohnehin weiter, statt fuenfhundert Namen
     * zu lesen.
     */
    private const HOECHSTENS = 50;

    #[Url]
    public $search;

    public function render()
    {
        $customers = null;
        $weitere = 0;

        if ($this->search) {
            // Einen mehr holen als angezeigt wird: Damit steht fest, ob es
            // weitere gibt, ohne dafuer ein zweites Mal zu zaehlen.
            $treffer = Customer::where('name', 'like', '%'.$this->search.'%')
                ->orderBy('name')
                ->limit(self::HOECHSTENS + 1)
                ->get();

            $weitere = max(0, $treffer->count() - self::HOECHSTENS);
            $customers = $treffer->take(self::HOECHSTENS);

            if ($customers->isEmpty()) {
                $customers = null;
            }
        }

        return view('livewire.search-customer', [
            'customers' => $customers,
            'weitere' => $weitere,
        ]);
    }
}
