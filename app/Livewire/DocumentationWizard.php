<?php

namespace App\Livewire;

use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Rules\BelongsToCustomer;
use App\Support\WizardFlow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Guided initial survey: asks the steps from config('custom.wizard_steps') in order and
 * creates every answer at once. Progress sits in documentation_runs (App\Models\
 * DocumentationRun), so a run survives a logout or a change of device.
 *
 * Security model: the isCustomer middleware and authorisation by route parameter do NOT
 * take hold on Livewire actions (those run through /livewire/update, without {customer} in
 * the route) - hence guard() checks anew on every action, and rulesForStep() replaces every
 * App\Rules\BelongsToCustomer rule with a Rule::exists(...)->where('customer_id', ...) that
 * needs no route parameter. See App\Livewire\DeviceIpAddresses for the same pattern.
 */
class DocumentationWizard extends Component
{
    use ValidatesOnInput;

    #[Locked]
    public int $customerId;

    #[Locked]
    public int $runId;

    /** @var array<string,mixed> */
    public array $form = [];

    /**
     * Fields where the database is stricter than the matching FormRequest (checked in the
     * migrations, not only in the requests):
     * - wifis.password / wifis.network_id are NOT NULL, WifiRequest lists neither as 'required'.
     * - servers.operating_system_id / vms.operating_system_id are NOT NULL (the ->nullable()
     *   in the migration hangs on the ForeignKeyDefinition only, not on the column); VMRequest
     *   even lets the field through empty.
     * Taking the FormRequest as it is would produce NOT NULL violations here or - on
     * encrypted columns such as wifis.password - ciphertext from an empty string.
     */
    protected const RULE_OVERRIDES = [
        'password' => 'required',
        'network_id' => 'required',
        'operating_system_id' => 'required',
    ];

    protected const FOREIGN_KEY_TABLES = [
        'network_id' => 'networks',
        'server_id' => 'servers',
    ];

    /**
     * Subnet mask and CIDR are two notations for the same thing - whoever
     * enters one gets the other with it. The same computation as in the VLAN
     * modal; it sits in the model.
     *
     * If the input stays incomplete or wrong, the partner field is left
     * alone: a value wiped out would be worse than one that does not fit
     * yet.
     */
    public function updatedForm(mixed $wert, ?string $schluessel = null): void
    {
        // Livewire calls this hook not only when a single sub-key changes
        // (then $schluessel is 'nachname', say), but also on a bundled update
        // of the whole form array - then $wert is the complete array and
        // $schluessel is null. The non-nullable signature used to throw a
        // TypeError (500) here as soon as two fields landed in one commit
        // (first name, tab, last name). A whole-array update does not need the
        // mask/CIDR sync - so simply bail out.
        if ($schluessel === null) {
            return;
        }

        if ($schluessel === 'subnetmask') {
            $cidr = Network::cidrAusMaske(is_string($wert) ? $wert : null);

            if ($cidr !== null) {
                $this->form['cidr'] = $cidr;
                // The partner field has just received a valid value - its red
                // border should go.
                $this->validateOnInput('form.cidr');
            }
        }

        if ($schluessel === 'cidr') {
            $maske = Network::maskeAusCidr(is_scalar($wert) ? $wert : null);

            if ($maske !== null) {
                $this->form['subnetmask'] = $maske;
                $this->validateOnInput('form.subnetmask');
            }
        }
    }

    public function mount(Customer $customer): void
    {
        $this->customerId = $customer->id;

        // Without a single _create permission the wizard is unusable for this user.
        abort_if(empty($this->allowedSteps()), 403);

        $run = DocumentationRun::where('customer_id', $customer->id)
            ->where('user_id', auth()->id())
            ->whereNull('completed_at')
            ->latest('id')
            ->first();

        if (! $run) {
            $firstKey = $this->allowedSteps()[0]['key'] ?? null;

            $run = DocumentationRun::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'current_step' => $firstKey,
                'completed_steps' => [],
                'skipped_steps' => [],
            ]);
        }

        // current_step can point at nothing after a config change or a change of
        // permissions, or at a step this user may (no longer) see.
        $allowedKeys = array_column($this->allowedSteps(), 'key');
        if ($run->current_step !== null && ! in_array($run->current_step, $allowedKeys, true)) {
            $run->update(['current_step' => $allowedKeys[0] ?? null]);
        }

        $this->runId = $run->id;
    }

    // --- Zugriff -------------------------------------------------------

    /**
     * Loads customer and run afresh and checks the tenant. Public properties can be
     * manipulated on the client (Livewire hydrates what the browser sends) - hence a fresh
     * check on every action, not just once in mount().
     *
     * @return array{0: Customer, 1: DocumentationRun}
     */
    protected function guard(): array
    {
        $customer = Customer::findOrFail($this->customerId);

        $user = auth()->user();
        abort_if(! $user, 403);
        abort_if($user->customer_id && $user->customer_id !== $customer->id, 403);

        $run = DocumentationRun::where('id', $this->runId)
            ->where('customer_id', $customer->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return [$customer, $run];
    }

    /**
     * Only steps the user holds the matching _create permission for. If they hold one for NO
     * step, the page is unusable for them altogether (see render()).
     */
    protected function allowedSteps(): array
    {
        return $this->ablauf()->steps();
    }

    /**
     * The flow of the wizard - which steps, what comes next. The same class
     * serves the iOS app; two flows for the same wizard would be two
     * truths.
     */
    protected function ablauf(): WizardFlow
    {
        return new WizardFlow(Customer::findOrFail($this->customerId), auth()->user());
    }

    protected function currentStep(DocumentationRun $run): ?array
    {
        if ($run->current_step === null) {
            return null;
        }

        return collect($this->allowedSteps())->firstWhere('key', $run->current_step);
    }

    // --- Formular --------------------------------------------------------

    protected function resetForm(): void
    {
        $this->form = [];
        $this->resetValidation();

        // The server has emptied the form - the browser has not. With
        // wire:model without .live the typed value sits in the DOM, and
        // Livewire protects input fields while morphing: after "add" the name
        // stayed in the field, and a second click created the same site a
        // second time.
        //
        // A new wire:key changed nothing about it - tried and discarded. So
        // the component tells the browser explicitly; the view empties the
        // fields in response.
        $this->dispatch('wizard-form-cleared');
    }

    protected function rulesForStep(array $step, int $customerId): array
    {
        /** @var FormRequest $request */
        $request = new $step['request'];
        $baseRules = $request->rules();

        $rules = [];

        foreach ($step['fields'] as $field) {
            $name = $field['name'];
            $raw = $baseRules[$name] ?? 'nullable';
            // Pipe-separated rule strings ('required|max:255') have to be split before
            // further rules are appended - passed through as a one-element array, Laravel
            // takes "required|max:255" for a single (non-existent) rule name.
            $fieldRules = is_array($raw) ? $raw : explode('|', $raw);

            // BelongsToCustomer liest request()->route('customer') - existiert unter
            // /livewire/update does not (see the class docblock). Replacement rule below.
            $fieldRules = collect($fieldRules)
                ->reject(fn ($rule) => $rule instanceof BelongsToCustomer)
                ->values()
                ->all();

            if (isset(self::FOREIGN_KEY_TABLES[$name])) {
                $fieldRules[] = Rule::exists(self::FOREIGN_KEY_TABLES[$name], 'id')
                    ->where('customer_id', $customerId)
                    ->whereNull('deleted_at');
            } elseif ($name === 'operating_system_id') {
                $fieldRules[] = Rule::exists('operating_systems', 'id')->whereNull('deleted_at');
            } elseif ($name === 'ip_address') {
                // No longer a device field but an entry in the IP block - the
                // rule for it therefore sits in no FormRequest. Appended
                // instead of as RULE_OVERRIDES, so the nullable stays.
                $fieldRules[] = 'ipv4';
            }

            if (isset(self::RULE_OVERRIDES[$name])) {
                $fieldRules = collect($fieldRules)
                    ->reject(fn ($r) => is_string($r) && in_array($r, ['nullable', 'sometimes'], true))
                    ->push(self::RULE_OVERRIDES[$name])
                    ->unique()
                    ->values()
                    ->all();
            }

            $rules["form.$name"] = $fieldRules;
        }

        return $rules;
    }

    protected function attributesForStep(array $step): array
    {
        return collect($step['fields'])
            ->mapWithKeys(fn (array $f) => ["form.{$f['name']}" => $f['label']])
            ->all();
    }

    // --- Aktionen --------------------------------------------------------

    /**
     * The rules of the current step.
     *
     * The wizard has fields of its own per step; rulesForStep() builds them
     * from the step's FormRequest. Here only the step is looked up - a second
     * rule list would exist otherwise.
     */
    protected function regeln(): array
    {
        [$customer, $run] = $this->guard();
        $step = $this->currentStep($run);

        return $step ? $this->rulesForStep($step, $customer->id) : [];
    }

    protected function fieldNames(): array
    {
        [, $run] = $this->guard();
        $step = $this->currentStep($run);

        return $step ? $this->attributesForStep($step) : [];
    }

    public function save(): void
    {
        [$customer, $run] = $this->guard();
        $step = $this->currentStep($run);
        abort_if(! $step, 404);

        Gate::authorize($step['permission']);

        if (($step['scope'] ?? 'site') === 'site' && ! $run->site_id) {
            $this->addError('form.name', 'Bitte zuerst einen Standort anlegen oder auswählen.');

            return;
        }

        $this->enableCheck();

        $this->validate($this->rulesForStep($step, $customer->id), [], $this->attributesForStep($step));

        // Mass assignment: every model is $guarded = [], and $form comes from the client.
        // Only the field names declared in the config may get through.
        $names = array_column($step['fields'], 'name');
        $data = Arr::only($this->form, $names);

        // Empty values out instead of storing them as '': encrypting setters
        // (Router::password and the like) would otherwise make ciphertext from an empty string.
        $data = array_filter($data, fn ($v) => $v !== '' && $v !== null);

        // Fixed values from the config (not from the form): kind = 'password'
        // on the credentials step, for one - LoginGeneral does not set that
        // itself, and without kind the global scope hides the entry.
        $data = array_merge($step['defaults'] ?? [], $data);

        if (($step['scope'] ?? 'site') === 'site') {
            $data['site_id'] = $run->site_id;
        }

        // The IP is no longer a column on the device but an entry in the block
        // "Weitere IP-Adressen" - network and label hang on it there. Hence
        // taken out before create() and added afterwards as a record of its own.
        $adresse = trim((string) ($data['ip_address'] ?? ''));
        unset($data['ip_address']);

        $record = $customer->{$step['relation']}()->create($data);

        if ($adresse !== '' && method_exists($record, 'ipAddresses')) {
            $record->ipAddresses()->create([
                'customer_id' => $customer->id,
                'address' => $adresse,
                'label' => __('Primär'),
            ]);
        }

        if ($step['sets_site'] ?? false) {
            $run->update(['site_id' => $record->id]);
        }

        $run->recordCreated($step['key'], $record->id);

        $this->resetForm();
    }

    /**
     * On the site step: take over an existing site for the rest of the run instead of
     * having to create a new one.
     */
    public function selectSite(int $siteId): void
    {
        [$customer, $run] = $this->guard();

        $site = Site::where('customer_id', $customer->id)->whereKey($siteId)->firstOrFail();

        $run->update(['site_id' => $site->id]);
    }

    public function nextStep(): void
    {
        [, $run] = $this->guard();
        $step = $this->currentStep($run);

        if ($step) {
            $run->markStepCompleted($step['key']);
        }

        $this->advance($run);
    }

    /**
     * Jump straight to a step - forwards or back.
     *
     * Only to allowed steps: allowedSteps() filters by _create permission,
     * and $key comes from the client. An unknown or blocked key is dropped
     * silently instead of putting the run into a state the user may not see
     * at all.
     *
     * The current step is marked neither done nor skipped in the process:
     * jumping is not editing.
     */
    public function gotoStep(string $key): void
    {
        [, $run] = $this->guard();

        if (! $this->ablauf()->springen($run, $key)) {
            return;
        }

        $this->resetForm();
        $this->dispatch('wizard-step');
    }

    public function skipStep(): void
    {
        [, $run] = $this->guard();
        $step = $this->currentStep($run);

        if ($step) {
            $this->leaveStep($run, $step['key']);
        }

        $this->advance($run);
    }

    /**
     * Leave a step without clicking "next". If something was created in it
     * during this run it still counts as captured - only a step genuinely
     * left empty counts as skipped. That way "skipped" does not end up on a
     * step where something was just entered.
     */
    protected function leaveStep(DocumentationRun $run, string $key): void
    {
        $this->ablauf()->verlassen($run, $key);
    }

    public function previousStep(): void
    {
        [, $run] = $this->guard();

        $this->ablauf()->zurueck($run);

        $this->resetForm();
        $this->dispatch('wizard-step');
    }

    protected function advance(DocumentationRun $run): void
    {
        $this->ablauf()->weiter($run);

        $this->resetForm();
        $this->dispatch('wizard-step');
    }

    /**
     * Skip the whole group of the current step ("no telephony", say): mark
     * every step of that group still open as skipped and jump to the first
     * step after it.
     */
    public function skipGroup(): void
    {
        [, $run] = $this->guard();

        $this->ablauf()->skipGroup($run);

        $this->resetForm();
        $this->dispatch('wizard-step');
    }

    public function finish(): void
    {
        [$customer, $run] = $this->guard();
        $run->update(['current_step' => null, 'completed_at' => now()]);

        $this->redirectRoute('customer.dashboard', ['customer' => $customer], navigate: false);
    }

    public function restart(): void
    {
        [$customer] = $this->guard();
        $firstKey = $this->allowedSteps()[0]['key'] ?? null;

        $run = DocumentationRun::create([
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
            'current_step' => $firstKey,
            'completed_steps' => [],
            'skipped_steps' => [],
        ]);

        $this->runId = $run->id;
        $this->resetForm();
    }

    // --- Anzeige -----------------------------------------------------------

    /**
     * An entry was saved in the embedded edit modal (ObjectModal reports
     * object-saved, the network modal NetworkQuickCreate reports
     * vlan-created). The empty body is enough: the mere fact that Livewire
     * calls this hook makes the component re-render, and render() pulls the
     * "already captured" list from the database afresh anyway.
     */
    #[On('object-saved')]
    #[On('vlan-created')]
    public function objektGespeichert(): void
    {
        //
    }

    /**
     * How an entry is named in the wizard: a model's own full name if it has
     * one (a contact is "Petra Kruse", not "Kruse"), otherwise the field
     * from config('custom.wizard_steps').
     */
    public static function bezeichnung($model, string $feld): string
    {
        // Only a model's own protokollName(), not the generic one from
        // TracksChanges - that picks the first filled name field and would
        // change what other steps show.
        $eigener = method_exists($model, 'activityName')
            && (new \ReflectionMethod($model, 'activityName'))->getDeclaringClass()->getName() === $model::class;
        $name = $eigener ? $model->activityName() : $model->{$feld};

        return filled($name) ? (string) $name : '—';
    }

    public function render()
    {
        [$customer, $run] = $this->guard();
        $steps = $this->allowedSteps();
        $step = $this->currentStep($run);

        $entries = collect();
        $selectOptions = [];

        if ($step) {
            // Everything the customer already has - not only this run's entries
            // and not only those at the chosen site: whoever opens the wizard
            // wants to see what is documented and fill the gaps.
            //
            // But only with the right to see that kind. A step needs only the
            // _create right, and a create-only role must not read the names
            // of everything documented through it.
            $viewRight = str_replace('_create', '_viewAny', $step['permission']);
            $entries = Gate::allows($viewRight)
                ? $step['model']::where('customer_id', $customer->id)->latest()->limit(50)->get()
                : collect();

            foreach ($step['fields'] as $field) {
                if (($field['type'] ?? null) === 'select' && is_string($field['options'] ?? null)) {
                    $selectOptions[$field['name']] = $this->optionsFor($field['options'], $customer, $run);
                }

                // Prefill from the config (subnet mask 255.255.255.0, say), only if the
                // field has not been touched yet - otherwise every re-render after save()
                // would write the default back.
                if (! array_key_exists($field['name'], $this->form) && isset($field['default'])) {
                    $this->form[$field['name']] = $field['default'];
                }
            }
        }

        $existingSites = $step && $step['key'] === 'site'
            ? Site::where('customer_id', $customer->id)->orderBy('name')->get()
            : collect();

        // Final summary: what this run created, per area with the names and a
        // jump into the list. The ids sit in created_records (see
        // DocumentationRun::recordCreated).
        $zusammenfassung = [];

        if ($run->completed_at !== null && ! empty($run->created_records)) {
            $alleSchritte = collect(config('custom.wizard_steps'))->keyBy('key');

            foreach ($run->created_records as $key => $ids) {
                $def = $alleSchritte->get($key);

                if (! $def) {
                    continue;
                }

                $modelle = $def['model']::where('customer_id', $customer->id)->whereIn('id', $ids)->get();

                if ($modelle->isEmpty()) {
                    continue;
                }

                $zusammenfassung[] = [
                    'label' => __($def['label']),
                    'route' => Route::has($key.'.index') ? $key.'.index' : null,
                    'namen' => $modelle->map(fn ($m) => self::bezeichnung($m, $def['label_field']))->all(),
                ];
            }
        }

        return view('livewire.documentation-wizard', [
            'customer' => $customer,
            'run' => $run,
            'steps' => $steps,
            'step' => $step,
            'entries' => $entries,
            'selectOptions' => $selectOptions,
            'existingSites' => $existingSites,
            'finished' => $run->completed_at !== null,
            'zusammenfassung' => $zusammenfassung,
        ]);
    }

    protected function optionsFor(string $source, Customer $customer, DocumentationRun $run): Collection
    {
        return match ($source) {
            'networks' => Network::where('customer_id', $customer->id)
                ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
                ->orderBy('vlanId')
                ->get(['id', 'description', 'vlanId']),
            'servers' => Server::where('customer_id', $customer->id)
                ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'operatingSystems' => OperatingSystem::orderBy('name')->get(['id', 'name']),
            default => collect(),
        };
    }
}
