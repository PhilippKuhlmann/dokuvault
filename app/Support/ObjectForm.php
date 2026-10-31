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
                'remoteID' => Setting::remoteAccess()['id_label'],
                'remotePassword' => Setting::remoteAccess()['password_label'],
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
     * Always loaded through the customer: a foreign id must not get
     * through here, even when it is sent along from outside.
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
            // A fixed option list always shows its first entry. Without the
            // same value in the form you see a choice and still get
            // "is required".
            // 'default' as in the wizard (config/custom.php): a field that
            // almost always holds the same value should offer it already.
            // Without it one would type the 1 for the height unit on every
            // rack server - the page form has filled it in for ages.
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
            // Date fields arrive as Carbon or as text, depending on the model.
            // Services arrive as an array from the model (explode on read),
            // stored is the comma list - hence joined back together.
            $werte[$feld['name']] = match (true) {
                // Switches stay booleans - '0' would show as ticked.
                $feld['type'] === 'schalter' => (bool) $wert,
                $wert instanceof \DateTimeInterface => $wert->format('Y-m-d'),
                is_array($wert) => implode(',', $wert),
                // (string) false yields '' and not '0': a disabled AD user
                // arrived as an empty field in the form and was shown as
                // "active" by the select - the stored value was the opposite
                // of what stood there.
                is_bool($wert) => $wert ? '1' : '0',
                default => (string) $wert,
            };
        }

        return $werte;
    }

    /**
     * The rules of the object kind.
     *
     * One source for saving and for the running check while typing -
     * two lists drift apart sooner or later.
     */
    public function rules(array $werte, string $prefix = ''): array
    {
        $request = $this->request($werte);

        // Otherwise the tenant rule takes the customer from the route -
        // in the modal that route is livewire.update and does not know it,
        // and in the app an update carries no customer in the URL.
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
     * Labels from the field definition itself.
     *
     * Not every request names every field in attributes(), and then the
     * internal name would stand in the message ("Please enter form.form
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

        // Empty fields as null, not as an empty string: MySQL rejects ''
        // date-Spalte ab ("Incorrect date value"), waehrend SQLite es
        // lets it through - so it stays invisible in the tests. And null is
        // the right thing anyway: no value is not an empty value.
        //
        // Unless the column allows no null: height_units for one is NOT
        // NULL with a default. There the key is left out so the database
        // sets its default instead of failing on null.
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

        // The stored path is server-owned: only storeFile sets it. Taken from
        // the form, it pointed a licence at another customer's files.
        if ($dateiFeld = collect($this->fields())->firstWhere('type', 'datei')) {
            unset($daten[$dateiFeld['pfad_feld']]);
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
     * Select lists from a table, e.g. the mailbox providers. What belongs
     * to the customer is limited to them; global catalogs such as the
     * providers have no customer_id.
     */
    public function selectOptions(array $feld): array
    {
        $klasse = $feld['quelle'];
        $abfrage = $klasse::query();

        if (Schema::hasColumn((new $klasse)->getTable(), 'customer_id')) {
            $abfrage->where('customer_id', $this->customerId);
        }

        // Restricting the type, e.g. "Windows only" for the operating
        // system of a Windows licence: Debian or Proxmox do not belong
        // Auswahl.
        foreach ($feld['einschraenkung'] ?? [] as [$spalte, $verhaeltnis, $wert]) {
            $abfrage->where($spalte, $verhaeltnis, $wert);
        }

        // Sorted by the first field named - with a pattern therefore by
        // whatever stands at the front.
        $sortierung = preg_match('/\{(\w+)\}/', $feld['anzeige'], $t) ? $t[1] : $feld['anzeige'];

        return $abfrage->orderBy($sortierung)->get()
            ->mapWithKeys(fn ($eintrag) => [$eintrag->id => $this->label($eintrag, $feld['anzeige'])])
            ->all();
    }

    /**
     * The label of a select entry. Either a field name or a pattern
     * mit Platzhaltern wie "VLAN {vlanId} · {description}".
     *
     * A pattern instead of a closure, because config/forms.php is frozen
     * by "php artisan config:cache" - closures do not survive that
     * not. Empty placeholders drop out together with their separator, so
     * a VLAN without a label leaves no lonely separator behind.
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
     * The request of the object kind, filled with the form values.
     *
     * Filled, because some rules are built from another field - "the
     * gateway must lie in the network" reads $this->input('subnet') for
     * that. Without the values it would get null and check against
     * nothing, with no error becoming visible.
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
     * Lift references to other fields into the prefix.
     *
     * In the modal the fields are called "form.server_id", in the
     * requests "server_id" - so a rule like required_without:server_id
     * looked for a field that does not exist there and never took hold.
     * This already affected required_if:form_factor,rack on the server:
     * height units and depth were never required in the modal, unnoticed.
     *
     * With required_if/required_unless only the first parameter is a
     * field, values follow it. With required_with/without all of them are.
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

        // The short form "required_if:...|boolean" hangs several rules
        // mit | aneinander.
        return is_string($regel)
            ? implode('|', array_map($einzelne, explode('|', $regel)))
            : $regel;
    }

    /**
     * Store an uploaded file and write its path into the data.
     *
     * The location follows the previous controller: {customer}/{folder}/ with a
     * timestamp in the file name, so two files of the same name do not
     * overwrite each other. On replacement the old one is deleted - otherwise
     * orphans pile up on disk that nobody can place any more.
     */
    private function storeFile(array $daten, ?Model $objekt, ?UploadedFile $datei): array
    {
        $feld = collect($this->fields())->firstWhere('type', 'datei');

        if (! $feld || ! $datei) {
            return $daten;
        }

        // Up to here the file was not checked at all: neither how big it is
        // nor what it is. A 60 MB file went through, and a .php did too.
        //
        Validator::make(['datei' => $datei], [
            'datei' => [
                'file',
                'max:'.Setting::uploadMaxKb(),
                'mimes:'.implode(',', Setting::fileFormats()),
            ],
        ], [], ['datei' => __('Datei')])->validate();

        // The name comes from a text field and the extension from the browser -
        // the sender decides both. Put into the storage path unchecked,
        // "../../../../other-customer/files/x" wrote the file into another
        // customer's directory and overwrote what was there.
        $dateiname = FileName::fuer($datei, $daten[$feld['name_feld']] ?? null);
        $pfad = $datei->storeAs($this->customer()->slug.'/'.$feld['ordner'], $dateiname, 'local');

        $alterPfad = $objekt?->{$feld['pfad_feld']};
        if (StoragePath::belongsTo($alterPfad, $this->customer(), $feld['ordner'])) {
            Storage::disk('local')->delete($alterPfad);
        }

        $daten[$feld['pfad_feld']] = $pfad;

        return $daten;
    }
}
