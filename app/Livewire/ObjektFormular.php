<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Setting;
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
            // Dienste kommen als Array aus dem Model (explode beim Lesen),
            // gespeichert wird die Komma-Liste - deshalb wieder zusammenfuegen.
            $this->form[$feld['name']] = match (true) {
                $wert instanceof \DateTimeInterface => $wert->format('Y-m-d'),
                is_array($wert) => implode(',', $wert),
                default => (string) $wert,
            };
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

        // Leere Felder als null, nicht als Leerstring: MySQL lehnt '' fuer eine
        // date-Spalte ab ("Incorrect date value"), waehrend SQLite es
        // durchlaesst - in den Tests bleibt das deshalb unsichtbar. Fachlich ist
        // null ohnehin richtig: kein Wert ist kein leerer Wert.
        $daten = array_map(fn ($wert) => $wert === '' ? null : $wert, $daten);

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

        $this->dispatch('hinweis', text: __($meldung));
        $this->dispatch('objekt-gespeichert', typ: $this->typ);
    }

    public function loeschen(): void
    {
        Gate::authorize($this->typ.'_delete');

        $this->objektHolen($this->bearbeiteId)->delete();

        $this->offen = false;
        $this->formularLeeren();

        $this->dispatch('hinweis', text: __($this->einstellung()['einzahl'].' gelöscht.'));
        $this->dispatch('objekt-gespeichert', typ: $this->typ);
    }

    /**
     * Beschriftung eines Auswahleintrags. Entweder ein Feldname oder ein Muster
     * mit Platzhaltern wie "VLAN {vlanId} · {description}".
     *
     * Ein Muster statt einer Closure, weil config/forms.php mit
     * "php artisan config:cache" eingefroren wird - Closures ueberleben das
     * nicht. Leere Platzhalter fallen mitsamt ihrem Trennzeichen weg, damit
     * bei einem VLAN ohne Bezeichnung kein einsames Trennzeichen stehen bleibt.
     */
    protected function beschriftung($eintrag, string $muster): string
    {
        if (! str_contains($muster, '{')) {
            return (string) $eintrag->{$muster};
        }

        $text = preg_replace_callback(
            '/\{(\w+)\}/',
            fn ($treffer) => (string) ($eintrag->{$treffer[1]} ?? ''),
            $muster
        );

        return trim(preg_replace('/\s*·\s*·\s*/', ' · ', trim($text)), " ·\t");
    }

    public function render()
    {
        $einstellung = $this->einstellung();

        // Geraete fuehren IP-Adressen und Zugangsdaten in eigenen Bloecken. Die
        // haengen am gespeicherten Objekt und koennen deshalb erst beim
        // Bearbeiten erscheinen - ohne sie waere das Modal ein Rueckschritt
        // gegenueber der Seite, die es ersetzt.
        $objekt = $this->bearbeiteId ? $this->objektHolen($this->bearbeiteId) : null;

        $felder = array_map(function (array $feld) {
            $feld['label'] = match ($feld['name']) {
                'remoteID' => Setting::fernwartung()['id_label'],
                'remotePassword' => Setting::fernwartung()['password_label'],
                default => $feld['label'],
            };

            return $feld;
        }, $einstellung['felder']);

        return view('livewire.objekt-formular', [
            'objekt' => $objekt,
            'mitBloecken' => (bool) ($einstellung['bloecke'] ?? false),
            'felder' => $felder,
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

                    // Sortiert wird nach dem ersten genannten Feld - bei einem
                    // Muster also nach dem, was vorne steht.
                    $sortierung = preg_match('/\{(\w+)\}/', $feld['anzeige'], $t)
                        ? $t[1]
                        : $feld['anzeige'];

                    return [$feld['name'] => $abfrage->orderBy($sortierung)->get()
                        ->mapWithKeys(fn ($eintrag) => [$eintrag->id => $this->beschriftung($eintrag, $feld['anzeige'])])];
                }),
        ]);
    }
}
