<?php

namespace App\Livewire;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Die Liste zu einem Typ aus config/forms.php - als Livewire, damit ein im
 * Modal angelegter Eintrag sofort erscheint statt erst nach einem Neuladen.
 *
 * Die Darstellung der einzelnen Karte bleibt beim Typ: Sie steht weiterhin in
 * resources/views/<typ>/_karte.blade.php. Eine generische Karte haette jede
 * Liste gleich aussehen lassen, und gerade die Unterschiede tragen hier die
 * Information.
 */
class ObjektListe extends Component
{
    use WithPagination;

    #[Locked]
    public string $typ;

    #[Locked]
    public int $customerId;

    #[Url]
    public string $search = '';

    public function mount(string $typ, Customer $customer): void
    {
        abort_unless(array_key_exists($typ, config('forms')), 404);

        Gate::authorize($typ.'_viewAny');

        $this->typ = $typ;
        $this->customerId = $customer->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Nach dem Speichern im Modal neu rendern - nur fuer den eigenen Typ. */
    #[On('objekt-gespeichert')]
    public function neuGeladen(string $typ): void
    {
        if ($typ === $this->typ) {
            $this->resetPage();
        }
    }

    /**
     * IP-Adressen und Zugangsdaten werden in eigenen Bloecken im Modal
     * gepflegt. Die Liste zeigt sie in ihren Spalten und muss deshalb auch auf
     * deren Aenderungen neu zeichnen - ein leerer Rumpf genuegt, Livewire
     * rendert nach jedem Aufruf ohnehin neu.
     */
    #[On('geraet-geaendert')]
    public function geraetGeaendert(): void {}

    public function render()
    {
        $einstellung = config('forms.'.$this->typ);
        $klasse = $einstellung['model'];

        $abfrage = $klasse::where('customer_id', $this->customerId);

        // Dasselbe Vorladen wie in den Controllern (siehe
        // Controller::zugangsdatenVorladen): Ohne das kostet eine Seite mit 25
        // Geraeten rund hundert Abfragen statt acht. Die Bedingungen sind
        // dieselben - Zugangsdaten und Adressen nur, wo das Model sie fuehrt,
        // sonst zaehlt eine Liste ohne beides zwei Abfragen zu viel.
        $traits = class_uses_recursive($klasse);

        if (in_array(HasCredentials::class, $traits, true)) {
            $abfrage->with('credentialLinks.login');
        }

        if (in_array(HasIpAddresses::class, $traits, true)) {
            $abfrage->with('ipAddresses.network');
        }

        foreach (['rackItem.rack', 'operatingSystem', 'site'] as $relation) {
            if (method_exists($klasse, explode('.', $relation)[0])) {
                $abfrage->with($relation);
            }
        }

        if (! empty($einstellung['mitladen'])) {
            $abfrage->with($einstellung['mitladen']);
        }

        if ($this->search !== '') {
            $begriff = '%'.addcslashes($this->search, '%_').'%';

            $abfrage->where(function ($q) use ($einstellung, $begriff) {
                foreach ($einstellung['suchfelder'] as $feld) {
                    $q->orWhere($feld, 'like', $begriff);
                }
            });
        }

        return view('livewire.objekt-liste', [
            'eintraege' => $abfrage->latest()->paginate(25),
            'customer' => Customer::findOrFail($this->customerId),
            'einzahl' => $einstellung['einzahl'],
        ]);
    }
}
