<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\ADDomainHost;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Customer;
use App\Models\DeviceModel;
use App\Models\Site;
use App\Support\ObjectForm;
use App\Support\Zeit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
    use GehoertZumKunden;
    use PrueftWaehrendDerEingabe;
    use WithFileUploads;

    #[Locked]
    public string $typ;

    #[Locked]
    public int $customerId;

    /**
     * Ob der eigene "Neu"-Knopf gezeigt wird. Im Dokumentations-Assistenten
     * wird nur das Bearbeiten-Modal eingebettet (geoeffnet ueber das Event
     * objekt-bearbeiten) - dort legt der Assistent selbst an, ein zweiter
     * gruener "Neu"-Knopf waere doppelt.
     */
    #[Locked]
    public bool $mitKnopf = true;

    public bool $offen = false;

    #[Locked]
    public ?int $bearbeiteId = null;

    public bool $loeschenGefragt = false;

    /**
     * Vorherige Kennwoerter, die der Nutzer aufgeklappt hat - nach Feldname.
     *
     * Sie werden erst auf Klick geladen und nicht beim Oeffnen mitgeschickt:
     * Sonst stuende in jedem Bearbeiten-Formular die halbe Kennwortgeschichte
     * des Geraets im Quelltext, auch wenn niemand danach gefragt hat.
     */
    public array $gezeigterVerlauf = [];

    /** Die Formularwerte, nach Feldnamen. */
    public array $form = [];

    /**
     * Die hochgeladene Datei - getrennt vom Formular, weil sie eine temporaere
     * Datei ist und kein Wert, der validiert oder in die Tabelle geschrieben
     * wird. Erst beim Speichern wird daraus ein Pfad.
     */
    public $datei;

    /**
     * Ein Foto der Frontblende - fuer das Geraetemodell, nicht fuer dieses
     * eine Geraet.
     *
     * Es landet in device_models und gilt damit fuer jeden Kunden, bei dem
     * dieselbe "APC Smart-UPS 1500" steht. Deshalb liegt es getrennt vom
     * Formular: Es wird nie in eine Spalte dieses Geraets geschrieben.
     */
    public $modellbild;

    public function mount(string $typ, Customer $customer, bool $mitKnopf = true): void
    {
        abort_unless(array_key_exists($typ, config('forms')), 404);
        $this->nurEigenerKunde($customer->id);

        $this->typ = $typ;
        $this->customerId = $customer->id;
        $this->mitKnopf = $mitKnopf;
        $this->formularLeeren();
    }

    protected function einstellung(): array
    {
        return config('forms.'.$this->typ);
    }

    /** Rules, choices and storing - shared with the iOS app. */
    protected function objektForm(): ObjectForm
    {
        return new ObjectForm($this->typ, $this->customerId);
    }

    protected function kunde(): Customer
    {
        return Customer::findOrFail($this->customerId);
    }

    protected function formularLeeren(): void
    {
        $this->form = $this->objektForm()->emptyValues();

        $this->datei = null;
        $this->modellbild = null;
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

        $this->form = $this->objektForm()->values($objekt);

        $this->bearbeiteId = $objekt->id;
        $this->loeschenGefragt = false;
        $this->gezeigterVerlauf = [];
        $this->resetValidation();
        $this->offen = true;
    }

    /**
     * Immer ueber den Kunden geladen: Eine fremde Id darf hier nicht
     * durchkommen, auch wenn sie von aussen mitgeschickt wird.
     */
    protected function objektHolen(int $id)
    {
        return $this->objektForm()->find($id);
    }

    public function abbrechen(): void
    {
        $this->offen = false;
        $this->formularLeeren();
    }

    /**
     * Werte fuer das Formular erzeugen lassen, wo der Typ einen Erzeuger nennt.
     *
     * Das Modal bleibt dabei generisch: Es weiss nur, dass es einen gibt
     * (config/forms.php), und schreibt zurueck, was er liefert. Gespeichert
     * wird nichts - erst der Speichern-Knopf legt an.
     */
    public function erzeugen(): void
    {
        Gate::authorize($this->bearbeiteId ? $this->typ.'_update' : $this->typ.'_create');

        $klasse = $this->einstellung()['erzeuger'] ?? null;
        abort_if($klasse === null, 404);

        try {
            $this->form = array_merge($this->form, app($klasse)->erzeugen($this->form));
        } catch (\RuntimeException $fehler) {
            // Als Feldfehler und nicht als Ausnahme: Ein fehlendes Verfahren
            // ist eine Eingabe, die man korrigieren kann, kein Serverfehler.
            $this->addError('form.key_type', $fehler->getMessage());
        }
    }

    /**
     * Die Regeln des Objekttyps, mit "form." davor - dieselben, mit denen die
     * App speichert, siehe ObjectForm.
     */
    protected function regeln(): array
    {
        return $this->objektForm()->rules($this->form, 'form.');
    }

    protected function feldnamen(): array
    {
        return $this->objektForm()->attributeNames('form.');
    }

    public function speichern(): void
    {
        Gate::authorize($this->bearbeiteId ? $this->typ.'_update' : $this->typ.'_create');

        $this->modellbildPruefen();

        $this->pruefungEinschalten();

        $daten = $this->validate($this->regeln(), [], $this->feldnamen())['form'];

        $bisher = $this->bearbeiteId ? $this->objektHolen($this->bearbeiteId) : null;
        $this->objektForm()->save($daten, $bisher, $this->datei);

        $meldung = $this->einstellung()['einzahl'].($bisher ? ' gespeichert.' : ' angelegt.');

        // Nach dem Geraet, aber vor dem Leeren des Formulars: Die Zuordnung
        // braucht Hersteller und Modell, die dort stehen.
        $this->modellbildAblegen();

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
     * A 'host' field may prefill another field from the chosen machine, e.g.
     * the path of a network drive: 'fuellt' => ['path' => '\\\\{name}\\'].
     *
     * An empty field gets the prefix. One that starts with the previous
     * machine's prefix gets it swapped ("\\SRV-FS01\Buchhaltung" becomes
     * "\\NAS01\Buchhaltung"). Anything else was typed on purpose and stays.
     */
    public function updatingForm($wert, $schluessel): void
    {
        $feld = $this->fuellendesHostFeld($schluessel);
        if (! $feld) {
            return;
        }

        $alt = $this->hostName($this->form[$schluessel] ?? null);
        $neu = $this->hostName($wert);

        foreach ($feld['fuellt'] as $ziel => $vorlage) {
            $aktuell = (string) ($this->form[$ziel] ?? '');
            $altPrefix = $alt !== null ? str_replace('{name}', $alt, $vorlage) : null;
            $neuPrefix = $neu !== null ? str_replace('{name}', $neu, $vorlage) : '';

            if ($aktuell === '' || $aktuell === $altPrefix) {
                $this->form[$ziel] = $neuPrefix;
            } elseif ($altPrefix !== null && $neu !== null && stripos($aktuell, $altPrefix) === 0) {
                $this->form[$ziel] = $neuPrefix.substr($aktuell, strlen($altPrefix));
            }
        }
    }

    /** The 'host' field with this name, if it prefills other fields. */
    protected function fuellendesHostFeld(string $name): ?array
    {
        $feld = collect($this->einstellung()['felder'])->firstWhere('name', $name);

        return $feld && $feld['type'] === 'host' && ! empty($feld['fuellt']) && ! ($feld['mehrfach'] ?? false) ? $feld : null;
    }

    /** Name of the machine behind a form key ("server:5"), only this customer's. */
    protected function hostName(mixed $schluessel): ?string
    {
        [$prefix, $id] = array_pad(explode(':', (string) $schluessel, 2), 2, null);
        $klasse = ADDomainHost::TYPES[$prefix] ?? null;

        return $klasse ? $klasse::where('customer_id', $this->customerId)->whereKey($id)->value('name') : null;
    }

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
     * Das Bild pruefen, bevor irgendetwas gespeichert wird.
     *
     * Ohne Hersteller laesst es sich keinem Modell zuordnen - es waere ein
     * Upload ins Nichts, und niemand saehe, warum das Bild spaeter fehlt.
     */
    protected function modellbildPruefen(): void
    {
        if (! $this->modellbild || ! $this->zeigtModellbild()) {
            return;
        }

        $this->validate([
            'modellbild' => ['image', 'mimes:'.implode(',', config('custom.bild_formate')), 'max:2048'],
        ], [], ['modellbild' => __('Bild der Frontblende')]);

        if (trim((string) ($this->form['manufacturer'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'modellbild' => __('Ohne Hersteller lässt sich das Bild keinem Modell zuordnen.'),
            ]);
        }
    }

    /**
     * Darf hier ein Modellbild hinterlegt werden?
     *
     * Abgeleitet statt konfiguriert: Wer im Rack sitzt, hat eine Frontblende.
     * Ein neuer Eintrag in rack_device_types bekommt das Feld damit von selbst,
     * und es steht nirgends ein zweites Mal.
     *
     * Nur mit admin_catalog: Das Bild gilt fuer alle Kunden. Wer nur seine
     * eigene Dokumentation pflegen darf, soll nichts hinterlegen, das anderswo
     * erscheint - sehen darf er es trotzdem.
     */
    protected function zeigtModellbild(): bool
    {
        return array_key_exists($this->typ, config('custom.rack_device_types'))
            && Gate::allows('admin_catalog');
    }

    /** Der Katalogeintrag zu dem, was gerade im Formular steht. */
    protected function modell(): ?DeviceModel
    {
        return DeviceModel::fuer(
            $this->typ,
            $this->form['manufacturer'] ?? null,
            $this->form['model'] ?? null
        );
    }

    /**
     * Das hochgeladene Bild dem Geraetemodell zuordnen.
     *
     * Gibt es zu Hersteller und Modell noch keinen Eintrag, entsteht er hier -
     * mit der Hoehe des Geraets, wenn es eine fuehrt. Einen vorhandenen Eintrag
     * ruehrt nur das Bild an: Seine Hoehe hat jemand im Adminbereich gesetzt,
     * und ein neu angelegtes Geraet soll sie nicht stillschweigend umwerfen.
     */
    protected function modellbildAblegen(): void
    {
        if (! $this->modellbild || ! $this->zeigtModellbild()) {
            return;
        }

        $hersteller = trim((string) ($this->form['manufacturer'] ?? ''));
        $modellname = trim((string) ($this->form['model'] ?? ''));

        $modell = $this->modell() ?? new DeviceModel([
            'device_type' => $this->typ,
            'manufacturer' => $hersteller,
            'model' => $modellname ?: null,
            'height_units' => max(1, (int) ($this->form['height_units'] ?? 1)),
        ]);

        // Erst die alte Datei weg, sonst bleibt bei jedem Wechsel eine liegen.
        $modell->bildLoeschen();
        $modell->image_path = $this->modellbild->store(DeviceModel::BILDORDNER, 'local');
        $modell->save();

        $this->modellbild = null;
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
     * Zu welchen Feldern gibt es einen Verlauf, und wie alt ist der neueste?
     *
     * Eine Abfrage fuer alle Felder statt einer je Feld, und ohne den Wert -
     * der wird erst geholt, wenn jemand darauf klickt.
     */
    protected function verlaufsUebersicht($objekt): array
    {
        if (! $objekt || ! method_exists($objekt, 'kennwortVerlauf')) {
            return [];
        }

        return $objekt->kennwortVerlauf()
            ->selectRaw('field, count(*) as anzahl, max(created_at) as zuletzt')
            ->groupBy('field')
            ->get()
            ->mapWithKeys(fn ($zeile) => [$zeile->field => [
                'anzahl' => (int) $zeile->anzahl,
                'zuletzt' => Carbon::parse($zeile->zuletzt)->diffForHumans(),
            ]])
            ->all();
    }

    /**
     * Die bisherigen Kennwoerter eines Feldes aufklappen.
     *
     * Wer das Geraet bearbeiten darf, darf sie sehen - er sieht das aktuelle
     * Kennwort ohnehin im Formular. Geprueft wird trotzdem serverseitig: Der
     * Aufruf kommt aus dem Browser.
     */
    public function verlaufZeigen(string $feld): void
    {
        Gate::authorize($this->typ.'_update');

        if (! $this->bearbeiteId || ! in_array($feld, config('custom.secret_columns'), true)) {
            return;
        }

        $this->gezeigterVerlauf[$feld] = $this->objektHolen($this->bearbeiteId)
            ->kennwortVerlauf()
            ->where('field', $feld)
            ->with('user:id,name')
            ->limit(10)
            ->get()
            ->map(fn ($eintrag) => [
                'wert' => $eintrag->value,
                'wann' => Zeit::anzeigen($eintrag->created_at),
                'seit' => $eintrag->created_at->diffForHumans(),
                'wer' => $eintrag->user?->name,
            ])
            ->all();
    }

    public function verlaufVerbergen(string $feld): void
    {
        unset($this->gezeigterVerlauf[$feld]);
    }

    public function render()
    {
        $einstellung = $this->einstellung();

        // Geraete fuehren IP-Adressen und Zugangsdaten in eigenen Bloecken. Die
        // haengen am gespeicherten Objekt und koennen deshalb erst beim
        // Bearbeiten erscheinen - ohne sie waere das Modal ein Rueckschritt
        // gegenueber der Seite, die es ersetzt.
        $objekt = $this->bearbeiteId ? $this->objektHolen($this->bearbeiteId) : null;

        $felder = $this->objektForm()->displayFields();

        return view('livewire.objekt-formular', [
            'objekt' => $objekt,
            // Nur Anzahl und Zeitpunkt - der Wert kommt erst auf Klick.
            'verlauf' => $this->verlaufsUebersicht($objekt),
            'mitBloecken' => (bool) ($einstellung['bloecke'] ?? false),
            // Breiter als die Bloecke sonst bekommen: nur wo das Formular selbst
            // kaum Felder hat und der Block den Inhalt ausmacht.
            'breitesModal' => (bool) ($einstellung['breit'] ?? false),
            // Mehrzeilige Felder brauchen die Breite immer, nicht erst beim
            // Bearbeiten: Ein Schluessel steht sonst schon beim Anlegen in
            // einem Feld, in dem man ihn nicht lesen kann.
            'mehrzeiligeFelder' => collect($einstellung['felder'])->contains('type', 'mehrzeilig'),
            // Beschriftung des Erzeuger-Knopfs, wo der Typ einen nennt.
            'erzeugerLabel' => isset($einstellung['erzeuger']) ? $einstellung['erzeuger_label'] : null,
            // Je Block einzeln: Ein FTP-Server fuehrt Zugangsdaten, aber keine
            // IP-Adressen. Wuerde der IP-Block trotzdem gerendert, riefe er
            // ipAddresses() auf einem Model auf, das die Relation nicht hat.
            'mitIpAdressen' => in_array(HasIpAddresses::class, class_uses_recursive($einstellung['model']), true),
            'mitZugangsdaten' => in_array(HasCredentials::class, class_uses_recursive($einstellung['model']), true),
            // Nur der Scanner hat Scan-Ziele.
            'mitScanZielen' => method_exists($einstellung['model'], 'scanTargets'),
            // Foto der Frontblende, das fuer alle Kunden gilt (device_models).
            'mitModellbild' => $this->zeigtModellbild(),
            'modell' => $this->zeigtModellbild() ? $this->modell() : null,
            'felder' => $felder,
            'einzahl' => $einstellung['einzahl'],
            'spalten' => $einstellung['spalten'] ?? 1,
            'kunde' => $this->kunde(),
            // Machines of the customer, only where a 'host' field exists.
            'hosts' => collect($einstellung['felder'])->contains('type', 'host')
                ? ADDomainHost::options($this->customerId, $this->objektForm()->hostTypes())
                : [],
            // Options of many-to-many fields, only this customer's entries.
            'verknuepfungen' => collect($einstellung['felder'])
                ->where('type', 'verknuepfung')
                ->mapWithKeys(fn ($feld) => [$feld['name'] => $this->objektForm()->linkOptions($feld)]),
            // Nur laden, wenn ein Standortfeld vorkommt.
            'sites' => collect($einstellung['felder'])->contains('type', 'standort')
                ? Site::where('customer_id', $this->customerId)->orderBy('name')->get()
                : collect(),
            // Auswahllisten aus einer Tabelle, siehe ObjectForm::selectOptions().
            'auswahlen' => collect($einstellung['felder'])
                ->where('type', 'auswahl')
                ->mapWithKeys(fn ($feld) => [$feld['name'] => collect($this->objektForm()->selectOptions($feld))]),
        ]);
    }
}
