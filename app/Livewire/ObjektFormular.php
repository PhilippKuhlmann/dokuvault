<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Site;
use App\Rules\BelongsToCustomer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

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

    /**
     * Die hochgeladene Datei - getrennt vom Formular, weil sie eine temporaere
     * Datei ist und kein Wert, der validiert oder in die Tabelle geschrieben
     * wird. Erst beim Speichern wird daraus ein Pfad.
     */
    public $datei;

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
            // Eine feste Optionsliste zeigt immer ihren ersten Eintrag an. Ohne
            // denselben Wert im Formular sieht man eine Auswahl und bekommt
            // trotzdem "ist erforderlich".
            $this->form[$feld['name']] = $feld['type'] === 'optionen'
                ? (string) array_key_first($feld['werte'] ?? config($feld['quelle']))
                : '';
        }

        $this->datei = null;
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
        $klasse = $this->einstellung()['model'];

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
            // Beschriftungen aus der eigenen Felddefinition: Nicht jeder Request
            // nennt jedes Feld in attributes(), und dann steht der interne Name
            // in der Meldung ("Das Feld form.form factor ist erforderlich").
            collect($this->einstellung()['felder'])
                ->mapWithKeys(fn ($feld) => ['form.'.$feld['name'] => __($feld['label'])])
                ->merge(collect($request->attributes())->mapWithKeys(fn ($name, $feld) => ['form.'.$feld => $name]))
                ->all()
        )['form'];

        // Leere Felder als null, nicht als Leerstring: MySQL lehnt '' fuer eine
        // date-Spalte ab ("Incorrect date value"), waehrend SQLite es
        // durchlaesst - in den Tests bleibt das deshalb unsichtbar. Fachlich ist
        // null ohnehin richtig: kein Wert ist kein leerer Wert.
        //
        // Ausser die Spalte laesst kein null zu: height_units etwa ist NOT NULL
        // mit Standardwert. Dort wird der Schluessel weggelassen, damit die
        // Datenbank ihren Standard setzt, statt an null zu scheitern.
        $tabelle = (new $klasse)->getTable();
        $spalten = collect(Schema::getColumns($tabelle))->keyBy('name');

        foreach ($daten as $feld => $wert) {
            if ($wert !== '') {
                continue;
            }

            $spalte = $spalten[$feld] ?? null;

            if ($spalte && ! $spalte['nullable']) {
                unset($daten[$feld]);
            } else {
                $daten[$feld] = null;
            }
        }

        $daten = $this->dateiAblegen($daten);

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

        $this->seiteNeuLadenWennNoetig();
    }

    /**
     * Manche Typen stehen auch ausserhalb ihrer Liste: Der Standort erscheint im
     * Umschalter der Seitenleiste und in der Auswahl jedes Geraeteformulars.
     * Beides liegt ausserhalb dieser Komponente und zeigte sonst weiter den
     * alten Stand - ein neuer Standort waere erst nach einem Neuladen zu
     * gebrauchen.
     */
    /**
     * Nach der Dateiwahl die Bezeichnung vorschlagen - aber nur, solange das
     * Feld leer ist. Wer schon etwas eingetragen hat, hat sich dabei etwas
     * gedacht; ein Vorschlag, der die eigene Eingabe ueberschreibt, ist
     * schlimmer als keiner.
     *
     * Ohne Endung: Die steckt schon im Dateinamen, der beim Ablegen entsteht,
     * und "Urkunde.pdf.pdf" will niemand.
     */
    public function updatedDatei(): void
    {
        $feld = collect($this->einstellung()['felder'])->firstWhere('type', 'datei');

        if (! $feld || ! $this->datei || filled($this->form[$feld['name_feld']] ?? null)) {
            return;
        }

        $this->form[$feld['name_feld']] = pathinfo(
            $this->datei->getClientOriginalName(),
            PATHINFO_FILENAME
        );
    }

    /**
     * Eine hochgeladene Datei ablegen und ihren Pfad in die Daten schreiben.
     *
     * Der Ablageort folgt dem bisherigen Controller: {kunde}/{ordner}/ mit
     * Zeitstempel im Dateinamen, damit zwei gleichnamige Dateien sich nicht
     * ueberschreiben. Beim Ersetzen wird die alte geloescht - sonst sammeln
     * sich Karteileichen auf der Platte, die niemand mehr zuordnen kann.
     */
    protected function dateiAblegen(array $daten): array
    {
        $feld = collect($this->einstellung()['felder'])->firstWhere('type', 'datei');

        if (! $feld || ! $this->datei) {
            return $daten;
        }

        $kunde = $this->kunde();
        $bezeichnung = $daten[$feld['name_feld']] ?? $this->datei->getClientOriginalName();

        $dateiname = time().'_'.$bezeichnung.'.'.$this->datei->getClientOriginalExtension();
        $pfad = $this->datei->storeAs($kunde->slug.'/'.$feld['ordner'], $dateiname, 'local');

        if ($this->bearbeiteId) {
            $alt = $this->objektHolen($this->bearbeiteId)->{$feld['pfad_feld']};

            if ($alt) {
                Storage::disk('local')->delete($alt);
            }
        }

        $daten[$feld['pfad_feld']] = $pfad;

        return $daten;
    }

    protected function seiteNeuLadenWennNoetig(): void
    {
        if ($this->einstellung()['seite_neu_laden'] ?? false) {
            $this->js('window.location.reload()');
        }
    }

    public function loeschen(): void
    {
        Gate::authorize($this->typ.'_delete');

        $this->objektHolen($this->bearbeiteId)->delete();

        $this->offen = false;
        $this->formularLeeren();

        $this->dispatch('hinweis', text: __($this->einstellung()['einzahl'].' gelöscht.'));
        $this->dispatch('objekt-gespeichert', typ: $this->typ);

        $this->seiteNeuLadenWennNoetig();
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
            'spalten' => $einstellung['spalten'] ?? 1,
            'kunde' => $this->kunde(),
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
