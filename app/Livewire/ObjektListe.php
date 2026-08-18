<?php

namespace App\Livewire;

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

    public function render()
    {
        $einstellung = config('forms.'.$this->typ);
        $klasse = $einstellung['model'];

        $abfrage = $klasse::where('customer_id', $this->customerId);

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
