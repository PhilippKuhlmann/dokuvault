<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Network;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Die VLAN-Liste als Livewire-Komponente.
 *
 * Grund fuer den Umbau: Das Anlegen laeuft ueber ein Modal (NetworkQuickCreate).
 * Solange die Liste statisches Blade war, musste danach die ganze Seite neu
 * geladen werden - und jeder Weg dorthin hatte seine eigene Falle (Redirect auf
 * die Livewire-Update-Adresse, verlorener Dunkelmodus). Jetzt genuegt ein
 * Rerender.
 */
class NetworkList extends Component
{
    use WithPagination;

    #[Locked]
    public int $customerId;

    /**
     * Wie in den uebrigen Suchkomponenten in der Adresse - ein gefiltertes
     * Ergebnis laesst sich damit weitergeben.
     */
    #[Url]
    public string $search = '';

    /**
     * Nach jedem Tastendruck zurueck auf Seite eins: Sonst sucht man auf
     * Seite drei und sieht nichts, obwohl es Treffer gibt.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(Customer $customer): void
    {
        Gate::authorize('viewAny', Network::class);

        $this->customerId = $customer->id;
    }

    /**
     * Nach dem Anlegen im Modal neu rendern - kein Seitenwechsel noetig.
     * Zurueck auf Seite eins, weil die Liste nach VLAN-Nummer sortiert ist und
     * der neue Eintrag sonst auf einer Seite landet, die man gerade nicht sieht.
     */
    #[On('vlan-angelegt')]
    public function neuGeladen(): void
    {
        $this->resetPage();
    }

    /**
     * Standortfilter und Sortierung wie in Controller::getFilteredQuery().
     *
     * Bewusst hier nachgebaut statt die Basisklasse aller Controller
     * anzufassen: Solange nur diese eine Liste auf Livewire laeuft, waere ein
     * Umbau dort ein Eingriff in vierzig Controller fuer einen Nutzer. Ziehen
     * die uebrigen Listen nach, gehoert das in ein gemeinsames Trait.
     */
    public function render()
    {
        Gate::authorize('viewAny', Network::class);

        $customer = Customer::findOrFail($this->customerId);
        $site = session()->get('site');

        $query = Network::where('customer_id', $customer->id);

        if ($site && $site !== 'all' && $customer->sites()->whereKey($site)->exists()) {
            $query->where('site_id', $site);
        }

        // Gesucht wird in dem, was man im Kopf hat, wenn man ein VLAN sucht:
        // Bezeichnung, Nummer, Netz und Gateway. Die DNS- und DHCP-Felder
        // bleiben draussen - danach sucht niemand, sie wuerden nur die
        // Trefferliste aufblaehen. Der Klammerausdruck ist noetig, damit das
        // ODER den Kunden- und Standortfilter nicht aushebelt.
        if ($this->search !== '') {
            $query->whereEnthaelt(['description', 'vlanId', 'network', 'gateway'], $this->search);
        }

        return view('livewire.network-list', [
            'customer' => $customer,
            'networks' => $query->orderBy('vlanId')->paginate(25),
        ]);
    }
}
