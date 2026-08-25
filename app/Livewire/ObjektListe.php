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

    /**
     * Filterwerte, nach Filtername abgelegt. Welche Filter es gibt, sagt der
     * Typ in config/forms.php - eine Lizenz hat eine Laufzeit, ein Drucker
     * nicht.
     */
    #[Url]
    public array $filter = [];

    /** Schluessel aus 'sortierungen' des Typs; leer heisst: die erste davon. */
    #[Url(except: '')]
    public string $sortierung = '';

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

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortierung(): void
    {
        $this->resetPage();
    }

    public function zuruecksetzen(): void
    {
        $this->reset(['search', 'filter', 'sortierung']);
        $this->resetPage();
    }

    /**
     * Die Filter dieses Typs, oder eine leere Liste.
     *
     * Bei einem Beziehungsfilter kommt die Auswahl aus dem Bestand dieses
     * Kunden, nicht aus dem ganzen Katalog: Ein Betriebssystem, zu dem es
     * keine Lizenz gibt, waere eine Zeile, die immer nichts findet.
     */
    public function filterDefinition(): array
    {
        $klasse = config('forms.'.$this->typ.'.model');

        return collect(config('forms.'.$this->typ.'.filter', []))
            ->map(function (array $def) use ($klasse) {
                if (($def['typ'] ?? '') !== 'beziehung') {
                    return $def;
                }

                $ids = $klasse::where('customer_id', $this->customerId)
                    ->whereNotNull($def['feld'])->distinct()->pluck($def['feld']);

                $def['optionen'] = $def['quelle']::whereIn('id', $ids)
                    ->orderBy($def['anzeige'])->pluck($def['anzeige'], 'id')->all();

                return $def;
            })->all();
    }

    /** Die Sortierungen dieses Typs, oder eine leere Liste. */
    public function sortierungen(): array
    {
        return config('forms.'.$this->typ.'.sortierungen', []);
    }

    /** Wahr, sobald etwas eingeschraenkt ist - fuer den Zuruecksetzen-Knopf. */
    public function gefiltert(): bool
    {
        return $this->search !== ''
            || $this->sortierung !== ''
            || collect($this->filter)->contains(fn ($w) => $w !== '' && $w !== null);
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
            // Die Maskierung stand hier schon, aber ohne ESCAPE-Klausel: Auf
            // MySQL ging das gut, auf SQLite fand ein Begriff mit Unterstrich
            // gar nichts mehr. whereEnthaelt bringt beides mit.
            $abfrage->whereEnthaelt($einstellung['suchfelder'], $this->search);
        }

        $this->filterAnwenden($abfrage);
        $this->sortierungAnwenden($abfrage);

        return view('livewire.objekt-liste', [
            'eintraege' => $abfrage->paginate(25),
            'filterDefinition' => $this->filterDefinition(),
            'sortierungen' => $this->sortierungen(),
            'gefiltert' => $this->gefiltert(),
            'customer' => Customer::findOrFail($this->customerId),
            'einzahl' => $einstellung['einzahl'],
        ]);
    }

    /**
     * Die Filter des Typs auf die Abfrage legen.
     *
     * Drei Arten reichen fuer den Bestand: ein Datum ("laeuft ab"), eine feste
     * Werteliste (Abo) und eine Beziehung (Betriebssystem). Was ein Typ davon
     * anbietet, steht bei ihm in config/forms.php.
     */
    protected function filterAnwenden($abfrage): void
    {
        foreach ($this->filterDefinition() as $definition) {
            $wert = $this->filter[$definition['name']] ?? '';

            if ($wert === '' || $wert === null) {
                continue;
            }

            match ($definition['typ']) {
                'ablauf' => $this->ablaufFiltern($abfrage, $definition['feld'], $wert),
                default => $abfrage->where($definition['feld'], $wert),
            };
        }
    }

    /**
     * Nach Ablaufdatum einschraenken.
     *
     * "offen" statt "laeuft": Eine Lizenz ohne Enddatum laeuft nicht ab und
     * gehoert zu den unproblematischen - sie faellt sonst durch jedes Raster.
     */
    protected function ablaufFiltern($abfrage, string $feld, string $wert): void
    {
        match ($wert) {
            'abgelaufen' => $abfrage->whereNotNull($feld)->whereDate($feld, '<', now()),
            'offen' => $abfrage->where(fn ($a) => $a->whereNull($feld)->orWhereDate($feld, '>=', now())),
            default => $abfrage->whereNotNull($feld)
                ->whereDate($feld, '>=', now())
                ->whereDate($feld, '<=', now()->addDays((int) $wert)),
        };
    }

    /**
     * Sortieren nach dem, was der Typ anbietet.
     *
     * Ohne Auswahl bleibt es bei "zuletzt angelegt zuerst" - so war es, bevor
     * es diese Auswahl gab.
     *
     * Bei einem Datum stehen leere Werte hinten: Eine Lizenz ohne Enddatum ist
     * nicht die, die als naechstes ablaeuft.
     */
    protected function sortierungAnwenden($abfrage): void
    {
        $sortierungen = $this->sortierungen();
        $gewaehlt = $sortierungen[$this->sortierung] ?? null;

        if (! $gewaehlt) {
            $abfrage->latest();

            return;
        }

        [$beschriftung, $spalte, $richtung] = $gewaehlt;

        if (str_ends_with($spalte, '_date') || str_ends_with($spalte, '_at')) {
            $abfrage->orderByRaw($spalte.' IS NULL');
        }

        $abfrage->orderBy($spalte, $richtung);
    }
}
