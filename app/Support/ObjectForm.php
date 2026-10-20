<?php

namespace App\Support;

use App\Models\ADDomainHost;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Site;
use App\Rules\BelongsToCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Creating and editing one type from config/forms.php for one customer.
 *
 * Shared by the modal in the browser (App\Livewire\ObjektFormular) and the
 * iOS app, so both validate with the same request, offer the same choices and
 * store the same way. Permissions stay with the caller.
 *
 * Field names come without a prefix. The modal keeps its values under
 * "form.", so rules() and attributeNames() take the prefix as an argument -
 * including the field references inside rules like required_without.
 */
class ObjectForm
{
    public function __construct(public readonly string $type, public readonly int $customerId)
    {
        abort_unless(array_key_exists($type, config('forms')), 404);
    }

    public function settings(): array
    {
        return config('forms.'.$this->type);
    }

    public function fields(): array
    {
        return $this->settings()['felder'];
    }

    /** The fields with the labels as shown - the remote support ones come from the settings. */
    public function displayFields(): array
    {
        return array_map(function (array $feld) {
            $feld['label'] = match ($feld['name']) {
                'remoteID' => Setting::fernwartung()['id_label'],
                'remotePassword' => Setting::fernwartung()['password_label'],
                default => $feld['label'],
            };

            return $feld;
        }, $this->fields());
    }

    public function customer(): Customer
    {
        return Customer::findOrFail($this->customerId);
    }

    /**
     * Immer ueber den Kunden geladen: Eine fremde Id darf hier nicht
     * durchkommen, auch wenn sie von aussen mitgeschickt wird.
     */
    public function find(int $id): Model
    {
        return $this->settings()['model']::where('customer_id', $this->customerId)->findOrFail($id);
    }

    /** The values of an empty form - what the modal shows after "Neu". */
    public function emptyValues(): array
    {
        $werte = [];

        foreach ($this->fields() as $feld) {
            // Eine feste Optionsliste zeigt immer ihren ersten Eintrag an. Ohne
            // denselben Wert im Formular sieht man eine Auswahl und bekommt
            // trotzdem "ist erforderlich".
            // 'default' wie im Assistenten (config/custom.php): Ein Feld, das
            // fast immer denselben Wert hat, soll ihn schon anbieten. Ohne das
            // muesste man bei jedem Rackserver die 1 fuer die Hoeheneinheit
            // tippen - das Seitenformular belegt sie laengst vor.
            $werte[$feld['name']] = match (true) {
                $feld['type'] === 'host' && ($feld['mehrfach'] ?? false) => [],
                $feld['type'] === 'verknuepfung' => [],
                // A real boolean: the browser takes the string '0' for a
                // ticked checkbox.
                $feld['type'] === 'schalter' => (bool) ($feld['default'] ?? false),
                $feld['type'] === 'optionen' => (string) array_key_first($feld['werte'] ?? config($feld['quelle'])),
                isset($feld['default']) => (string) $feld['default'],
                default => '',
            };
        }

        return $werte;
    }

    /** The values of a stored object, as the form holds them. */
    public function values(Model $objekt): array
    {
        $werte = [];

        foreach ($this->fields() as $feld) {
            // No column: the machines sit in their own table (ADDomain::hosts).
            if ($feld['type'] === 'host') {
                $schluessel = $objekt->hostKeys($feld['rolle']);
                $werte[$feld['name']] = ($feld['mehrfach'] ?? false) ? $schluessel : ($schluessel[0] ?? '');

                continue;
            }

            // Many-to-many (e.g. AD user <-> group): the ids of the linked
            // entries, as strings like the options of the select.
            if ($feld['type'] === 'verknuepfung') {
                $werte[$feld['name']] = array_map('strval', $objekt->{$feld['relation']}->modelKeys());

                continue;
            }

            $wert = $objekt->{$feld['name']};
            // Datumsfelder kommen je nach Model als Carbon oder als Text.
            // Dienste kommen als Array aus dem Model (explode beim Lesen),
            // gespeichert wird die Komma-Liste - deshalb wieder zusammenfuegen.
            $werte[$feld['name']] = match (true) {
                // Switches stay booleans - '0' would show as ticked.
                $feld['type'] === 'schalter' => (bool) $wert,
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

        return $werte;
    }

    /**
     * Die Regeln des Objekttyps.
     *
     * Eine Quelle fuer das Speichern und fuer die laufende Pruefung waehrend
     * der Eingabe - zwei Listen liefen frueher oder spaeter auseinander.
     */
    public function rules(array $werte, string $prefix = ''): array
    {
        $request = $this->request($werte);

        // Die Mandantenregel holt den Kunden sonst aus der Route - die heisst
        // im Modal livewire.update und kennt ihn nicht, in der App steht bei
        // einer Aenderung kein Kunde in der Adresse.
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

        return collect($regelnMitKunde)
            ->map(fn ($regel) => $prefix === '' ? $regel : $this->prefixReferences($regel, $prefix))
            ->merge($this->hostRules())
            ->mapWithKeys(fn ($regel, $feld) => [$prefix.$feld => $regel])
            ->all();
    }

    /**
     * Beschriftungen aus der eigenen Felddefinition.
     *
     * Nicht jeder Request nennt jedes Feld in attributes(), und dann stuende
     * der interne Name in der Meldung ("Bitte form.form factor angeben").
     */
    public function attributeNames(string $prefix = ''): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn ($feld) => [$prefix.$feld['name'] => __($feld['label'])])
            ->merge(collect($this->request([])->attributes())->mapWithKeys(fn ($name, $feld) => [$prefix.$feld => $name]))
            ->all();
    }

    /** Validate without a prefix - for callers that are not the modal. */
    public function validate(array $werte): array
    {
        return Validator::make($werte, $this->rules($werte), [], $this->attributeNames())->validate();
    }

    /**
     * Store validated values: create without an object, update with one.
     *
     * $datei is the upload for a 'datei' field, if the type has one; it is
     * checked here, before anything is written.
     */
    public function save(array $daten, ?Model $objekt = null, ?UploadedFile $datei = null): Model
    {
        $klasse = $this->settings()['model'];

        // Leere Felder als null, nicht als Leerstring: MySQL lehnt '' fuer eine
        // date-Spalte ab ("Incorrect date value"), waehrend SQLite es
        // durchlaesst - in den Tests bleibt das deshalb unsichtbar. Fachlich ist
        // null ohnehin richtig: kein Wert ist kein leerer Wert.
        //
        // Ausser die Spalte laesst kein null zu: height_units etwa ist NOT NULL
        // mit Standardwert. Dort wird der Schluessel weggelassen, damit die
        // Datenbank ihren Standard setzt, statt an null zu scheitern.
        $spalten = collect(Schema::getColumns((new $klasse)->getTable()))->keyBy('name');

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

        $daten = $this->storeFile($daten, $objekt, $datei);

        // Not a column - written after the object exists, see below.
        $hostFelder = collect($this->fields())->where('type', 'host')->keyBy('name');
        $verknuepfungen = collect($this->fields())->where('type', 'verknuepfung')->keyBy('name');
        $spaltenDaten = array_diff_key($daten, $hostFelder->all(), $verknuepfungen->all());

        if ($objekt) {
            $objekt->update($spaltenDaten);
        } else {
            $objekt = $this->customer()->{$this->settings()['relation']}()->create($spaltenDaten);
        }

        // Only what was sent: an update from the app may leave a field out.
        foreach ($hostFelder->intersectByKeys($daten) as $name => $feld) {
            $objekt->syncHosts($feld['rolle'], (array) ($daten[$name] ?? []));
        }

        foreach ($verknuepfungen->intersectByKeys($daten) as $name => $feld) {
            $objekt->{$feld['relation']}()->sync(array_map('intval', (array) ($daten[$name] ?? [])));
        }

        return $objekt;
    }

    /**
     * The choices of every select, as value => label: sites, catalogue
     * tables, fixed lists, machines and linked entries. Only this customer's.
     *
     * @return array<string, array<string|int, string>>
     */
    public function options(): array
    {
        $optionen = [];

        foreach ($this->fields() as $feld) {
            $liste = match ($feld['type']) {
                'standort' => Site::where('customer_id', $this->customerId)->orderBy('name')->pluck('name', 'id')->all(),
                'optionen' => array_map(fn ($text) => __((string) $text), $feld['werte'] ?? config($feld['quelle'], [])),
                'auswahl' => $this->selectOptions($feld),
                'host' => ADDomainHost::options($this->customerId, $this->hostTypes()),
                'verknuepfung' => $this->linkOptions($feld),
                default => null,
            };

            if ($liste !== null) {
                $optionen[$feld['name']] = $liste;
            }
        }

        return $optionen;
    }

    /** Options of a many-to-many field, only this customer's entries. */
    public function linkOptions(array $feld): array
    {
        return $feld['quelle']::where('customer_id', $this->customerId)
            ->orderBy($feld['anzeige'])
            ->get()
            ->mapWithKeys(fn ($eintrag) => [(string) $eintrag->id => $eintrag->{$feld['anzeige']} ?: '#'.$eintrag->id])
            ->all();
    }

    /**
     * Which machines the 'host' fields of this type offer: servers and VMs,
     * unless a field names its own ('typen' => ['server', 'vm', 'nas']).
     *
     * @return array<int, string>
     */
    public function hostTypes(): array
    {
        return collect($this->fields())->where('type', 'host')
            ->pluck('typen')->flatten()->filter()->unique()->values()->all() ?: ['server', 'vm'];
    }

    /**
     * Auswahllisten aus einer Tabelle, z. B. die Postfach-Anbieter. Was dem
     * Kunden gehoert, wird auf ihn eingeschraenkt; globale Kataloge wie die
     * Anbieter haben keine customer_id.
     */
    public function selectOptions(array $feld): array
    {
        $klasse = $feld['quelle'];
        $abfrage = $klasse::query();

        if (Schema::hasColumn((new $klasse)->getTable(), 'customer_id')) {
            $abfrage->where('customer_id', $this->customerId);
        }

        // Einschraenkung des Typs, z. B. "nur Windows" beim Betriebssystem
        // einer Windows-Lizenz: Debian oder Proxmox gehoeren dort nicht zur
        // Auswahl.
        foreach ($feld['einschraenkung'] ?? [] as [$spalte, $verhaeltnis, $wert]) {
            $abfrage->where($spalte, $verhaeltnis, $wert);
        }

        // Sortiert wird nach dem ersten genannten Feld - bei einem Muster also
        // nach dem, was vorne steht.
        $sortierung = preg_match('/\{(\w+)\}/', $feld['anzeige'], $t) ? $t[1] : $feld['anzeige'];

        return $abfrage->orderBy($sortierung)->get()
            ->mapWithKeys(fn ($eintrag) => [$eintrag->id => $this->label($eintrag, $feld['anzeige'])])
            ->all();
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
    public function label($eintrag, string $muster): string
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
     * Der Request des Objekttyps, mit den Formularwerten gefuellt.
     *
     * Gefuellt, weil manche Regel aus einem anderen Feld gebaut wird - "das
     * Gateway muss im Netz liegen" liest dafuer $this->input('subnet'). Ohne
     * die Werte bekaeme sie null und pruefte gegen nichts, ohne dass ein
     * Fehler sichtbar wuerde.
     */
    private function request(array $werte): FormRequest
    {
        $klasse = $this->settings()['request'];
        $request = new $klasse;
        $request->merge($werte);

        return $request;
    }

    /**
     * Rules for 'host' and 'verknuepfung' fields: only machines and entries
     * of this customer. The request cannot say that - it does not know the
     * customer.
     */
    private function hostRules(): array
    {
        $regeln = [];

        // Linked entries must belong to this customer.
        foreach (collect($this->fields())->where('type', 'verknuepfung') as $feld) {
            $tabelle = (new $feld['quelle'])->getTable();
            $regeln[$feld['name']] = ['nullable', 'array'];
            $regeln[$feld['name'].'.*'] = [Rule::exists($tabelle, 'id')->where('customer_id', $this->customerId)->whereNull('deleted_at')];
        }

        $felder = collect($this->fields())->where('type', 'host');

        if ($felder->isEmpty()) {
            return $regeln;
        }

        $erlaubt = Rule::in(array_keys(ADDomainHost::options($this->customerId, $this->hostTypes())));

        foreach ($felder as $feld) {
            if ($feld['mehrfach'] ?? false) {
                $regeln[$feld['name']] = ['nullable', 'array'];
                $regeln[$feld['name'].'.*'] = [$erlaubt];
            } else {
                $regeln[$feld['name']] = ['nullable', $erlaubt];
            }
        }

        return $regeln;
    }

    /**
     * Verweise auf andere Felder in den Praefix heben.
     *
     * Im Modal heissen die Felder "form.server_id", in den Requests aber
     * "server_id" - eine Regel wie required_without:server_id suchte deshalb
     * ein Feld, das es dort nicht gibt, und griff nie. Betraf schon vorher
     * required_if:form_factor,rack am Server: Hoeheneinheiten und Einbautiefe
     * waren im Modal nie Pflicht, ohne dass es auffiel.
     *
     * Bei required_if/required_unless ist nur der erste Parameter ein Feld,
     * dahinter stehen Werte. Bei required_with/without sind es alle.
     */
    private function prefixReferences(mixed $regel, string $prefix): mixed
    {
        $nurErstesFeld = ['required_if', 'required_unless'];
        $alleFelder = ['required_with', 'required_with_all', 'required_without', 'required_without_all', 'same', 'different', 'prohibits'];

        $einzelne = function ($teil) use ($nurErstesFeld, $alleFelder, $prefix) {
            if (! is_string($teil) || ! str_contains($teil, ':')) {
                return $teil;
            }

            [$name, $parameter] = explode(':', $teil, 2);

            if (! in_array($name, [...$nurErstesFeld, ...$alleFelder], true)) {
                return $teil;
            }

            $werte = explode(',', $parameter);

            $werte = in_array($name, $nurErstesFeld, true)
                ? array_merge([$prefix.$werte[0]], array_slice($werte, 1))
                : array_map(fn ($feld) => $prefix.$feld, $werte);

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
     * Eine hochgeladene Datei ablegen und ihren Pfad in die Daten schreiben.
     *
     * Der Ablageort folgt dem bisherigen Controller: {kunde}/{ordner}/ mit
     * Zeitstempel im Dateinamen, damit zwei gleichnamige Dateien sich nicht
     * ueberschreiben. Beim Ersetzen wird die alte geloescht - sonst sammeln
     * sich Karteileichen auf der Platte, die niemand mehr zuordnen kann.
     */
    private function storeFile(array $daten, ?Model $objekt, ?UploadedFile $datei): array
    {
        $feld = collect($this->fields())->firstWhere('type', 'datei');

        if (! $feld || ! $datei) {
            return $daten;
        }

        // Bis hierher wurde die Datei ueberhaupt nicht geprueft: weder wie
        // gross sie ist noch was sie ist. Eine 60-MB-Datei ging durch, eine
        // .php auch.
        Validator::make(['datei' => $datei], [
            'datei' => [
                'file',
                'max:'.Setting::uploadMaxKb(),
                'mimes:'.implode(',', Setting::dateiFormate()),
            ],
        ], [], ['datei' => __('Datei')])->validate();

        // Der Name kommt aus einem Textfeld und die Endung aus dem Browser -
        // beides bestimmt der Absender. Ungeprueft in den Ablagepfad gesetzt,
        // schrieb "../../../../fremder-kunde/files/x" die Datei in das
        // Verzeichnis eines anderen Kunden und ueberschrieb, was dort lag.
        $dateiname = Dateiname::fuer($datei, $daten[$feld['name_feld']] ?? null);
        $pfad = $datei->storeAs($this->customer()->slug.'/'.$feld['ordner'], $dateiname, 'local');

        if ($objekt?->{$feld['pfad_feld']}) {
            Storage::disk('local')->delete($objekt->{$feld['pfad_feld']});
        }

        $daten[$feld['pfad_feld']] = $pfad;

        return $daten;
    }
}
