<?php

namespace App\Livewire;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Customer;
use App\Models\DeviceModel;
use App\Models\Setting;
use App\Models\Site;
use App\Rules\BelongsToCustomer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
    use WithFileUploads;

    #[Locked]
    public string $typ;

    #[Locked]
    public int $customerId;

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
            // 'default' wie im Assistenten (config/custom.php): Ein Feld, das
            // fast immer denselben Wert hat, soll ihn schon anbieten. Ohne das
            // muesste man bei jedem Rackserver die 1 fuer die Hoeheneinheit
            // tippen - das Seitenformular belegt sie laengst vor.
            $this->form[$feld['name']] = match (true) {
                $feld['type'] === 'optionen' => (string) array_key_first($feld['werte'] ?? config($feld['quelle'])),
                isset($feld['default']) => (string) $feld['default'],
                default => '',
            };
        }

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

        foreach ($this->einstellung()['felder'] as $feld) {
            $wert = $objekt->{$feld['name']};
            // Datumsfelder kommen je nach Model als Carbon oder als Text.
            // Dienste kommen als Array aus dem Model (explode beim Lesen),
            // gespeichert wird die Komma-Liste - deshalb wieder zusammenfuegen.
            $this->form[$feld['name']] = match (true) {
                $wert instanceof \DateTimeInterface => $wert->format('Y-m-d'),
                is_array($wert) => implode(',', $wert),
                // (string) false ergibt '' und nicht '0': Ein deaktivierter
                // AD-Benutzer kam so als leeres Feld im Formular an und wurde
                // von der Auswahl als "Aktiv" angezeigt - der gespeicherte Wert
                // war das Gegenteil dessen, was dastand.
                is_bool($wert) => $wert ? '1' : '0',
                default => (string) $wert,
            };
        }

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
        $klasse = $this->einstellung()['model'];

        return $klasse::where('customer_id', $this->customerId)->findOrFail($id);
    }

    public function abbrechen(): void
    {
        $this->offen = false;
        $this->formularLeeren();
    }

    /**
     * Verweise auf andere Felder in den Praefix des Formulars heben.
     *
     * Hier heissen die Felder "form.server_id", in den Requests aber
     * "server_id" - eine Regel wie required_without:server_id suchte deshalb
     * ein Feld, das es hier nicht gibt, und griff nie. Betraf schon vorher
     * required_if:form_factor,rack am Server: Hoeheneinheiten und Einbautiefe
     * waren im Modal nie Pflicht, ohne dass es auffiel.
     *
     * Bei required_if/required_unless ist nur der erste Parameter ein Feld,
     * dahinter stehen Werte. Bei required_with/without sind es alle.
     */
    protected function feldverweiseUmschreiben(mixed $regel): mixed
    {
        $nurErstesFeld = ['required_if', 'required_unless'];
        $alleFelder = ['required_with', 'required_with_all', 'required_without', 'required_without_all', 'same', 'different', 'prohibits'];

        $einzelne = function ($teil) use ($nurErstesFeld, $alleFelder) {
            if (! is_string($teil) || ! str_contains($teil, ':')) {
                return $teil;
            }

            [$name, $parameter] = explode(':', $teil, 2);

            if (! in_array($name, [...$nurErstesFeld, ...$alleFelder], true)) {
                return $teil;
            }

            $werte = explode(',', $parameter);

            $werte = in_array($name, $nurErstesFeld, true)
                ? array_merge(['form.'.$werte[0]], array_slice($werte, 1))
                : array_map(fn ($feld) => 'form.'.$feld, $werte);

            return $name.':'.implode(',', $werte);
        };

        if (is_array($regel)) {
            return array_map($einzelne, $regel);
        }

        // Die Kurzschreibweise "required_if:...|boolean" haengt mehrere Regeln
        // mit | aneinander.
        return is_string($regel)
            ? implode('|', array_map($einzelne, explode('|', $regel)))
            : $regel;
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

    public function speichern(): void
    {
        Gate::authorize($this->bearbeiteId ? $this->typ.'_update' : $this->typ.'_create');

        $this->modellbildPruefen();

        $regeln = $this->einstellung()['request'];
        $request = new $regeln;

        // Die Formularwerte in den Request: Manche Regel wird aus einem anderen
        // Feld gebaut - "das Gateway muss im Netz liegen" etwa liest dafuer
        // $this->input('subnet'). Ohne die Werte bekaeme sie null und pruefte
        // gegen nichts, ohne dass ein Fehler sichtbar wuerde.
        $request->merge($this->form);

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
            collect($regelnMitKunde)
                ->map(fn ($regel) => $this->feldverweiseUmschreiben($regel))
                ->mapWithKeys(fn ($regel, $feld) => ['form.'.$feld => $regel])->all(),
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
                'wann' => $eintrag->created_at->format('d.m.Y H:i'),
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
            // Foto der Frontblende, das fuer alle Kunden gilt (device_models).
            'mitModellbild' => $this->zeigtModellbild(),
            'modell' => $this->zeigtModellbild() ? $this->modell() : null,
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

                    // Einschraenkung des Typs, z. B. "nur Windows" beim
                    // Betriebssystem einer Windows-Lizenz: Debian oder Proxmox
                    // gehoeren dort nicht zur Auswahl.
                    foreach ($feld['einschraenkung'] ?? [] as [$spalte, $verhaeltnis, $wert]) {
                        $abfrage->where($spalte, $verhaeltnis, $wert);
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
