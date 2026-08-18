<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Site;
use App\Rules\BelongsToCustomer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Anlegen und Bearbeiten im Modal - fuer jeden Typ aus config/forms.php.
 *
 * Das VLAN hat es vorgemacht: Wer ein Geraet nachtraegt, verliert sonst die
 * Liste, auf die er gerade geschaut hat. Statt das Muster vierzigmal zu
 * kopieren, beschreibt config/forms.php die Felder, und diese Komponente baut
 * das Formular daraus.
 *
 * Validiert wird mit dem Request, den auch der Controller benutzt - eine
 * zweite Regelmenge waere die Stelle, an der die beiden Wege auseinanderlaufen.
 */
class ObjektFormular extends Component
{
    #[Locked]
    public string $typ;

    #[Locked]
    public int $customerId;

    public bool $offen = false;

    #[Locked]
    public ?int $bearbeiteId = null;

    public bool $loeschenGefragt = false;

    /** Die Formularwerte, nach Feldnamen. */
    public array $form = [];

    public function mount(string $typ, Customer $customer): void
    {
        abort_unless(array_key_exists($typ, config('forms')), 404);

        $this->typ = $typ;
        $this->customerId = $customer->id;
        $this->formularLeeren();
    }

    protected function einstellung(): array
    {
        return config('forms.'.$this->typ);
    }

    protected function kunde(): Customer
    {
        return Customer::findOrFail($this->customerId);
    }

    protected function formularLeeren(): void
    {
        $this->form = [];

        foreach ($this->einstellung()['felder'] as $feld) {
            $this->form[$feld['name']] = '';
        }

        $this->bearbeiteId = null;
        $this->loeschenGefragt = false;
        $this->resetValidation();
    }

    public function neu(): void
    {
        Gate::authorize($this->typ.'_create');

        // Leeren vor dem Oeffnen: Sonst stehen im Modal die Werte des zuletzt
        // bearbeiteten Eintrags, und "Neu" wird stillschweigend zu "Bearbeiten".
        $this->formularLeeren();
        $this->offen = true;
    }

    #[On('objekt-bearbeiten')]
    public function bearbeiten(string $typ, int $id): void
    {
        if ($typ !== $this->typ) {
            return;
        }

        Gate::authorize($this->typ.'_update');

        $objekt = $this->objektHolen($id);

        foreach ($this->einstellung()['felder'] as $feld) {
            $wert = $objekt->{$feld['name']};
            // Datumsfelder kommen je nach Model als Carbon oder als Text.
            $this->form[$feld['name']] = $wert instanceof \DateTimeInterface
                ? $wert->format('Y-m-d')
                : (string) $wert;
        }

        $this->bearbeiteId = $objekt->id;
        $this->loeschenGefragt = false;
        $this->resetValidation();
        $this->offen = true;
    }

    /**
     * Immer ueber den Kunden geladen: Eine fremde Id darf hier nicht
     * durchkommen, auch wenn sie von aussen mitgeschickt wird.
     */
    protected function objektHolen(int $id)
    {
        $klasse = $this->einstellung()['model'];

        return $klasse::where('customer_id', $this->customerId)->findOrFail($id);
    }

    public function abbrechen(): void
    {
        $this->offen = false;
        $this->formularLeeren();
    }

    public function speichern(): void
    {
        Gate::authorize($this->bearbeiteId ? $this->typ.'_update' : $this->typ.'_create');

        $regeln = $this->einstellung()['request'];
        $request = new $regeln;

        // Die Mandantenregel holt den Kunden sonst aus der Route - die heisst
        // hier livewire.update und kennt ihn nicht.
        $regelnMitKunde = collect($request->rules())->map(function ($regel) {
            if (! is_array($regel)) {
                return $regel;
            }

            return array_map(
                fn ($einzeln) => $einzeln instanceof BelongsToCustomer
                    ? new BelongsToCustomer($einzeln->tabelle(), $this->customerId)
                    : $einzeln,
                $regel
            );
        })->all();

        $daten = $this->validate(
            collect($regelnMitKunde)->mapWithKeys(fn ($regel, $feld) => ['form.'.$feld => $regel])->all(),
            [],
            collect($request->attributes())->mapWithKeys(fn ($name, $feld) => ['form.'.$feld => $name])->all()
        )['form'];

        if ($this->bearbeiteId) {
            $this->objektHolen($this->bearbeiteId)->update($daten);
            $meldung = $this->einstellung()['einzahl'].' gespeichert.';
        } else {
            $relation = $this->einstellung()['relation'];
            $this->kunde()->{$relation}()->create($daten);
            $meldung = $this->einstellung()['einzahl'].' angelegt.';
        }

        $this->offen = false;
        $this->formularLeeren();

        $this->dispatch('objekt-gespeichert', typ: $this->typ);
        $this->dispatch('success', __($meldung));
    }

    public function loeschen(): void
    {
        Gate::authorize($this->typ.'_delete');

        $this->objektHolen($this->bearbeiteId)->delete();

        $this->offen = false;
        $this->formularLeeren();

        $this->dispatch('objekt-gespeichert', typ: $this->typ);
        $this->dispatch('success', __($this->einstellung()['einzahl'].' gelöscht.'));
    }

    public function render()
    {
        $einstellung = $this->einstellung();

        // Geraete fuehren IP-Adressen und Zugangsdaten in eigenen Bloecken. Die
        // haengen am gespeicherten Objekt und koennen deshalb erst beim
        // Bearbeiten erscheinen - ohne sie waere das Modal ein Rueckschritt
        // gegenueber der Seite, die es ersetzt.
        $objekt = $this->bearbeiteId ? $this->objektHolen($this->bearbeiteId) : null;

        return view('livewire.objekt-formular', [
            'objekt' => $objekt,
            'mitBloecken' => (bool) ($einstellung['bloecke'] ?? false),
            'felder' => $einstellung['felder'],
            'einzahl' => $einstellung['einzahl'],
            // Nur laden, wenn ein Standortfeld vorkommt.
            'sites' => collect($einstellung['felder'])->contains('type', 'standort')
                ? Site::where('customer_id', $this->customerId)->orderBy('name')->get()
                : collect(),
            // Auswahllisten aus einer Tabelle, z. B. die Postfach-Anbieter. Was
            // dem Kunden gehoert, wird auf ihn eingeschraenkt; globale Kataloge
            // wie die Anbieter haben keine customer_id.
            'auswahlen' => collect($einstellung['felder'])
                ->where('type', 'auswahl')
                ->mapWithKeys(function ($feld) {
                    $klasse = $feld['quelle'];
                    $abfrage = $klasse::query();

                    if (Schema::hasColumn((new $klasse)->getTable(), 'customer_id')) {
                        $abfrage->where('customer_id', $this->customerId);
                    }

                    return [$feld['name'] => $abfrage->orderBy($feld['anzeige'])->get()];
                }),
        ]);
    }
}
