<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\ADDomainHost;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Customer;
use App\Models\DeviceModel;
use App\Models\Site;
use App\Support\LocalTime;
use App\Support\ObjectForm;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create and edit in a modal - for every kind from config/forms.php.
 *
 * The VLAN led the way: whoever adds a device otherwise loses the list they
 * were just looking at. Instead of copying the pattern forty times,
 * config/forms.php describes the fields and this component builds the form
 * from them.
 *
 * Validation uses the request the controller uses as well - a second set of
 * rules would be the place where the two paths drift apart.
 */
class ObjectModal extends Component
{
    use ScopedToCustomer;
    use ValidatesOnInput;
    use WithFileUploads;

    #[Locked]
    public string $typ;

    #[Locked]
    public int $customerId;

    /**
     * Whether the own "new" button is shown. In the documentation wizard only
     * the edit modal is embedded (opened through the event object-edit) -
     * there the wizard creates things itself, and a second green "new"
     * button would be one too many.
     */
    #[Locked]
    public bool $mitKnopf = true;

    public bool $offen = false;

    #[Locked]
    public ?int $bearbeiteId = null;

    public bool $loeschenGefragt = false;

    /**
     * Previous passwords the user has expanded - by field name.
     *
     * They are loaded on click and not sent along when opening: otherwise
     * half the password history of the device would stand in the source of
     * every edit form, even when nobody asked for it.
     */
    public array $gezeigterVerlauf = [];

    /** The form values, by field name. */
    public array $form = [];

    /**
     * The uploaded file - kept apart from the form, because it is a temporary
     * file and not a value that gets validated or written into the table.
     * Only on save does it become a path.
     */
    public $datei;

    /**
     * A photo of the front panel - for the device model, not for this one
     * device.
     *
     * It lands in device_models and thereby applies to every customer who has
     * the same "APC Smart-UPS 1500". That is why it is kept apart from the
     * form: it is never written into a column of this device.
     */
    public $modellbild;

    public function mount(string $typ, Customer $customer, bool $mitKnopf = true): void
    {
        abort_unless(array_key_exists($typ, config('forms')), 404);
        $this->onlyOwnCustomer($customer->id);

        $this->typ = $typ;
        $this->customerId = $customer->id;
        $this->mitKnopf = $mitKnopf;
        $this->clearForm();
    }

    protected function setting(): array
    {
        return config('forms.'.$this->typ);
    }

    /** Rules, choices and storing - shared with the iOS app. */
    protected function objectForm(): ObjectForm
    {
        return new ObjectForm($this->typ, $this->customerId);
    }

    protected function kunde(): Customer
    {
        return Customer::findOrFail($this->customerId);
    }

    protected function clearForm(): void
    {
        $this->form = $this->objectForm()->emptyValues();

        $this->datei = null;
        $this->modellbild = null;
        $this->bearbeiteId = null;
        $this->loeschenGefragt = false;
        $this->resetValidation();
    }

    public function neu(): void
    {
        Gate::authorize($this->typ.'_create');

        // Emptied before opening: otherwise the modal holds the values of the
        // last entry edited, and "new" silently becomes "edit".
        $this->clearForm();
        $this->offen = true;
    }

    #[On('object-edit')]
    public function bearbeiten(string $typ, int $id): void
    {
        if ($typ !== $this->typ) {
            return;
        }

        Gate::authorize($this->typ.'_update');

        $objekt = $this->fetchObject($id);

        $this->form = $this->objectForm()->values($objekt);

        $this->bearbeiteId = $objekt->id;
        $this->loeschenGefragt = false;
        $this->gezeigterVerlauf = [];
        $this->resetValidation();
        $this->offen = true;
    }

    /**
     * Always loaded through the customer: a foreign id must not get through
     * here, even when it is sent along from outside.
     */
    protected function fetchObject(int $id)
    {
        return $this->objectForm()->find($id);
    }

    public function cancel(): void
    {
        $this->offen = false;
        $this->clearForm();
    }

    /**
     * Have values generated for the form where the kind names a generator.
     *
     * The modal stays generic about it: it only knows there is one
     * (config/forms.php) and writes back whatever it returns. Nothing is
     * saved - only the save button creates.
     */
    public function generate(): void
    {
        Gate::authorize($this->bearbeiteId ? $this->typ.'_update' : $this->typ.'_create');

        $klasse = $this->setting()['erzeuger'] ?? null;
        abort_if($klasse === null, 404);

        try {
            $this->form = array_merge($this->form, app($klasse)->generate($this->form));
        } catch (\RuntimeException $fehler) {
            // As a field error and not as an exception: a missing algorithm is
            // input one can correct, not a server error.
            $this->addError('form.key_type', $fehler->getMessage());
        }
    }

    /**
     * The rules of the object kind, prefixed with "form." - the same ones the
     * App speichert, siehe ObjectForm.
     */
    protected function regeln(): array
    {
        return $this->objectForm()->rules($this->form, 'form.');
    }

    protected function fieldNames(): array
    {
        return $this->objectForm()->attributeNames('form.');
    }

    public function save(): void
    {
        Gate::authorize($this->bearbeiteId ? $this->typ.'_update' : $this->typ.'_create');

        $this->checkModelImage();

        $this->enableCheck();

        $daten = $this->validate($this->regeln(), [], $this->fieldNames())['form'];

        $bisher = $this->bearbeiteId ? $this->fetchObject($this->bearbeiteId) : null;
        $this->objectForm()->save($daten, $bisher, $this->datei);

        $meldung = $this->setting()['einzahl'].($bisher ? ' gespeichert.' : ' angelegt.');

        // After the device but before emptying the form: the assignment needs
        // manufacturer and model, which stand there.
        $this->storeModelImage();

        $this->offen = false;
        $this->clearForm();

        $this->dispatch('notice', text: __($meldung));
        $this->dispatch('object-saved', typ: $this->typ);

        $this->reloadPageIfNeeded();
    }

    /**
     * Some kinds appear outside their list as well: the site shows up in the
     * sidebar switcher and in the select of every device form. Both lie
     * outside this component and would otherwise keep showing the old state -
     * a new site would only be usable after a reload.
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
        $feld = $this->fillingHostField($schluessel);
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
    protected function fillingHostField(string $name): ?array
    {
        $feld = collect($this->setting()['felder'])->firstWhere('name', $name);

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
     * Suggest the label after choosing a file - but only while the field is
     * empty. Whoever has entered something had a reason; a suggestion that
     * overwrites one's own input is worse than none.
     * schlimmer als keiner.
     *
     * Without the extension: it is already in the file name that comes into
     * being on storage, and nobody wants "Urkunde.pdf.pdf".
     */
    public function updatedDatei(): void
    {
        $feld = collect($this->setting()['felder'])->firstWhere('type', 'datei');

        if (! $feld || ! $this->datei || filled($this->form[$feld['name_feld']] ?? null)) {
            return;
        }

        $this->form[$feld['name_feld']] = pathinfo(
            $this->datei->getClientOriginalName(),
            PATHINFO_FILENAME
        );
    }

    /**
     * Check the image before anything is saved.
     *
     * Without a manufacturer it cannot be assigned to a model - it would be
     * an upload into nothing, and nobody would see why the image is missing
     * later.
     */
    protected function checkModelImage(): void
    {
        if (! $this->modellbild || ! $this->showsModelImage()) {
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
     * May a model image be stored here?
     *
     * Derived instead of configured: whatever sits in a rack has a front
     * panel. A new entry in rack_device_types therefore gets the field by
     * itself, and it stands nowhere a second time.
     *
     * Only with admin_catalog: the image applies to all customers. Whoever may
     * only maintain their own documentation should not store anything that
     * shows up elsewhere - seeing it is fine all the same.
     */
    protected function showsModelImage(): bool
    {
        return array_key_exists($this->typ, config('custom.rack_device_types'))
            && Gate::allows('admin_catalog');
    }

    /** The catalog entry for whatever stands in the form right now. */
    protected function modell(): ?DeviceModel
    {
        return DeviceModel::fuer(
            $this->typ,
            $this->form['manufacturer'] ?? null,
            $this->form['model'] ?? null
        );
    }

    /**
     * Assign the uploaded image to the device model.
     *
     * If there is no entry yet for manufacturer and model, it comes into
     * being here - with the height of the device if it carries one. An
     * existing entry is only touched for the image: somebody set its height
     * in the admin area, and a newly created device must not silently
     */
    protected function storeModelImage(): void
    {
        if (! $this->modellbild || ! $this->showsModelImage()) {
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

        // The old file first, or one stays behind with every change.
        $modell->deleteImage();
        $modell->image_path = $this->modellbild->store(DeviceModel::BILDORDNER, 'local');
        $modell->save();

        $this->modellbild = null;
    }

    protected function reloadPageIfNeeded(): void
    {
        if ($this->setting()['seite_neu_laden'] ?? false) {
            $this->js('window.location.reload()');
        }
    }

    public function delete(): void
    {
        Gate::authorize($this->typ.'_delete');

        $this->fetchObject($this->bearbeiteId)->delete();

        $this->offen = false;
        $this->clearForm();

        $this->dispatch('notice', text: __($this->setting()['einzahl'].' gelöscht.'));
        $this->dispatch('object-saved', typ: $this->typ);

        $this->reloadPageIfNeeded();
    }

    /**
     * Which fields have a history, and how old is the newest one?
     *
     * One query for all fields instead of one per field, and without the
     * value - that is only fetched when somebody clicks on it.
     */
    protected function historySummary($objekt): array
    {
        if (! $objekt || ! method_exists($objekt, 'passwordHistory')) {
            return [];
        }

        return $objekt->passwordHistory()
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
     * Expand the previous passwords of a field.
     *
     * Whoever may edit the device may see them - they see the current
     * password in the form anyway. It is checked on the server all the same:
     * the call comes from the browser.
     */
    public function showHistory(string $feld): void
    {
        Gate::authorize($this->typ.'_update');

        if (! $this->bearbeiteId || ! in_array($feld, config('custom.secret_columns'), true)) {
            return;
        }

        $this->gezeigterVerlauf[$feld] = $this->fetchObject($this->bearbeiteId)
            ->passwordHistory()
            ->where('field', $feld)
            ->with('user:id,name')
            ->limit(10)
            ->get()
            ->map(fn ($eintrag) => [
                'wert' => $eintrag->value,
                'wann' => LocalTime::display($eintrag->created_at),
                'seit' => $eintrag->created_at->diffForHumans(),
                'wer' => $eintrag->user?->name,
            ])
            ->all();
    }

    public function hideHistory(string $feld): void
    {
        unset($this->gezeigterVerlauf[$feld]);
    }

    public function render()
    {
        $einstellung = $this->setting();

        // Devices carry IP addresses and credentials in blocks of their own.
        // Those hang on the saved object and can therefore only appear when
        // editing - without them the modal would be a step back from the page
        // it replaces.
        $objekt = $this->bearbeiteId ? $this->fetchObject($this->bearbeiteId) : null;

        $felder = $this->objectForm()->displayFields();

        return view('livewire.object-modal', [
            'objekt' => $objekt,
            // Count and timestamp only - the value comes on click.
            'verlauf' => $this->historySummary($objekt),
            'mitBloecken' => (bool) ($einstellung['bloecke'] ?? false),
            // Made wider than the blocks otherwise get: only where the form
            // itself has few fields and the block is the content.
            'breitesModal' => (bool) ($einstellung['breit'] ?? false),
            // Multi-line fields always need the width, not only when editing:
            // a key would otherwise stand in a field one cannot read it in,
            // right from creation.
            'mehrzeiligeFelder' => collect($einstellung['felder'])->contains('type', 'mehrzeilig'),
            // Label of the generator button where the kind names one.
            'erzeugerLabel' => isset($einstellung['erzeuger']) ? $einstellung['erzeuger_label'] : null,
            // Per block individually: an FTP server carries credentials but no
            // IP addresses. Were the IP block rendered all the same, it would
            // call ipAddresses() on a model that has no such relation.
            'mitIpAdressen' => in_array(HasIpAddresses::class, class_uses_recursive($einstellung['model']), true),
            'mitZugangsdaten' => in_array(HasCredentials::class, class_uses_recursive($einstellung['model']), true),
            // Only the scanner has scan targets.
            'mitScanZielen' => method_exists($einstellung['model'], 'scanTargets'),
            // Photo of the front panel, valid for all customers (device_models).
            'mitModellbild' => $this->showsModelImage(),
            'modell' => $this->showsModelImage() ? $this->modell() : null,
            'felder' => $felder,
            'einzahl' => $einstellung['einzahl'],
            'spalten' => $einstellung['spalten'] ?? 1,
            'kunde' => $this->kunde(),
            // Machines of the customer, only where a 'host' field exists.
            'hosts' => collect($einstellung['felder'])->contains('type', 'host')
                ? ADDomainHost::options($this->customerId, $this->objectForm()->hostTypes())
                : [],
            // Options of many-to-many fields, only this customer's entries.
            'verknuepfungen' => collect($einstellung['felder'])
                ->where('type', 'verknuepfung')
                ->mapWithKeys(fn ($feld) => [$feld['name'] => $this->objectForm()->linkOptions($feld)]),
            // Only load when a site field appears.
            'sites' => collect($einstellung['felder'])->contains('type', 'standort')
                ? Site::where('customer_id', $this->customerId)->orderBy('name')->get()
                : collect(),
            // Select lists from a table, see ObjectForm::selectOptions().
            'auswahlen' => collect($einstellung['felder'])
                ->where('type', 'auswahl')
                ->mapWithKeys(fn ($feld) => [$feld['name'] => collect($this->objectForm()->selectOptions($feld))]),
        ]);
    }
}
