<?php

namespace App\Livewire;

use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Geführte Erstaufnahme: fragt die Schritte aus config('custom.wizard_steps') der Reihe nach ab
 * und legt jede Antwort sofort an. Fortschritt liegt in documentation_runs (App\Models\
 * DocumentationRun), damit ein Durchlauf Logout/Gerätewechsel übersteht.
 *
 * Sicherheitsmodell: isCustomer-Middleware und Autorisierung per Route-Parameter greifen bei
 * Livewire-Aktionen NICHT (die laufen über /livewire/update, ohne {customer} in der Route) -
 * deshalb prüft guard() bei jeder Aktion neu, und rulesForStep() ersetzt jede
 * App\Rules\BelongsToCustomer-Regel durch eine Rule::exists(...)->where('customer_id', ...), die
 * ohne Routen-Parameter auskommt. Siehe App\Livewire\DeviceIpAddresses für dasselbe Muster.
 */
class DocumentationWizard extends Component
{
    use PrueftWaehrendDerEingabe;

    public int $customerId;

    public int $runId;

    /** @var array<string,mixed> */
    public array $form = [];

    /**
     * Felder, bei denen die Datenbank strenger ist als der zugehörige FormRequest (geprüft in
     * den Migrationen, nicht nur in den Requests):
     * - wifis.password / wifis.network_id sind NOT NULL, WifiRequest führt beide nicht als 'required'.
     * - servers.operating_system_id / vms.operating_system_id sind NOT NULL (die ->nullable() in
     *   der Migration hängt nur an der ForeignKeyDefinition, nicht an der Spalte); VMRequest lässt
     *   das Feld sogar leer durch.
     * Reine FormRequest-Übernahme würde hier NOT-NULL-Verletzungen oder - bei verschlüsselten
     * Spalten wie wifis.password - Chiffretext aus einem Leerstring erzeugen.
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
     * Subnetzmaske und CIDR sind zwei Schreibweisen fuer dieselbe Angabe -
     * wer eine eintraegt, bekommt die andere dazu. Dieselbe Rechnung wie im
     * VLAN-Modal, sie steht im Model.
     *
     * Bleibt die Eingabe unvollstaendig oder falsch, wird das Partnerfeld
     * nicht angefasst: Eine geleerte Angabe waere schlimmer als eine, die
     * noch nicht passt.
     */
    public function updatedForm(mixed $wert, string $schluessel): void
    {
        if ($schluessel === 'subnetmask') {
            $cidr = Network::cidrAusMaske(is_string($wert) ? $wert : null);

            if ($cidr !== null) {
                $this->form['cidr'] = $cidr;
                // Das Partnerfeld hat gerade einen gueltigen Wert bekommen -
                // sein roter Rahmen gehoert weg.
                $this->waehrendDerEingabePruefen('form.cidr');
            }
        }

        if ($schluessel === 'cidr') {
            $maske = Network::maskeAusCidr(is_scalar($wert) ? $wert : null);

            if ($maske !== null) {
                $this->form['subnetmask'] = $maske;
                $this->waehrendDerEingabePruefen('form.subnetmask');
            }
        }
    }

    public function mount(Customer $customer): void
    {
        $this->customerId = $customer->id;

        // Ohne ein einziges _create-Recht ist der Assistent für diesen Nutzer nicht nutzbar.
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

        // current_step kann nach einer Config-Änderung oder einem Rechtewechsel ins Leere
        // zeigen oder auf einen Schritt, den dieser Nutzer (mehr) nicht sehen darf.
        $allowedKeys = array_column($this->allowedSteps(), 'key');
        if ($run->current_step !== null && ! in_array($run->current_step, $allowedKeys, true)) {
            $run->update(['current_step' => $allowedKeys[0] ?? null]);
        }

        $this->runId = $run->id;
    }

    // --- Zugriff -------------------------------------------------------

    /**
     * Lädt Kunde + Durchlauf frisch und prüft Mandantenzugehörigkeit. Public Properties sind
     * client-seitig manipulierbar (Livewire hydriert, was der Browser schickt) - deshalb bei
     * jeder Aktion neu prüfen, nicht nur einmalig in mount().
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
     * Nur Schritte, für die der Nutzer das jeweilige _create-Recht hat. Hat er für KEINEN
     * Schritt ein Recht, ist die Seite für ihn insgesamt nicht nutzbar (siehe render()).
     */
    protected function allowedSteps(): array
    {
        return collect(config('custom.wizard_steps'))
            ->filter(fn (array $step) => Gate::allows($step['permission']))
            ->map(fn (array $step) => $this->fernwartungBeschriften($step))
            ->values()
            ->all();
    }

    /**
     * Die beiden Fernwartungsfelder heissen nach dem eingestellten Werkzeug.
     *
     * Die Ersetzung passiert hier und nicht in der Konfiguration: "php artisan
     * config:cache" friert config/custom.php ein, ein Wert aus der Datenbank
     * waere darin fuer immer eingebacken und liesse sich ueber die
     * Einstellungen nicht mehr aendern.
     */
    protected function fernwartungBeschriften(array $step): array
    {
        $tool = Setting::fernwartung();

        $step['fields'] = collect($step['fields'] ?? [])->map(function (array $feld) use ($tool) {
            $feld['label'] = match ($feld['name'] ?? null) {
                'remoteID' => $tool['id_label'],
                'remotePassword' => $tool['password_label'],
                default => $feld['label'] ?? '',
            };

            return $feld;
        })->all();

        return $step;
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

        // Der Server hat das Formular geleert - der Browser nicht. Bei
        // wire:model ohne .live steht der getippte Wert im DOM, und Livewire
        // schuetzt Eingabefelder beim Morphen: Nach "Hinzufuegen" blieb der
        // Name im Feld stehen, und ein zweiter Klick legte denselben Standort
        // noch einmal an.
        //
        // Ein neuer wire:key hat daran nichts geaendert - probiert und
        // verworfen. Also sagt die Komponente es dem Browser ausdruecklich;
        // die View leert die Felder darauf hin.
        $this->dispatch('assistent-formular-geleert');
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
            // Pipe-getrennte Regel-Strings ('required|max:255') müssen vor dem Anhängen
            // weiterer Regeln aufgesplittet werden - als Ein-Element-Array durchgereicht,
            // hält Laravel "required|max:255" für einen einzigen (nicht existierenden) Regelnamen.
            $fieldRules = is_array($raw) ? $raw : explode('|', $raw);

            // BelongsToCustomer liest request()->route('customer') - existiert unter
            // /livewire/update nicht (siehe Klassen-Docblock). Ersatzregel unten.
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
                // Kein Geraetefeld mehr, sondern ein Eintrag im IP-Block - eine
                // Regel dafuer steht deshalb in keinem FormRequest. Angehaengt
                // statt als RULE_OVERRIDES, damit das nullable stehen bleibt.
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
     * Die Regeln des aktuellen Schritts.
     *
     * Der Assistent hat je Schritt eigene Felder; rulesForStep() baut sie aus
     * dem FormRequest des Schritts. Hier wird nur der Schritt herausgesucht -
     * eine zweite Regelliste gaebe es sonst.
     */
    protected function regeln(): array
    {
        [$customer, $run] = $this->guard();
        $step = $this->currentStep($run);

        return $step ? $this->rulesForStep($step, $customer->id) : [];
    }

    protected function feldnamen(): array
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

        $this->pruefungEinschalten();

        $this->validate($this->rulesForStep($step, $customer->id), [], $this->attributesForStep($step));

        // Massenzuweisung: alle Models sind $guarded = [], $form kommt vom Client.
        // Nur die in der Config deklarierten Feldnamen dürfen durch.
        $names = array_column($step['fields'], 'name');
        $data = Arr::only($this->form, $names);

        // Leere Werte raus, statt sie als '' zu speichern: verschlüsselnde Setter
        // (Router::password etc.) machen sonst Chiffretext aus einem Leerstring.
        $data = array_filter($data, fn ($v) => $v !== '' && $v !== null);

        if (($step['scope'] ?? 'site') === 'site') {
            $data['site_id'] = $run->site_id;
        }

        // Die IP ist keine Spalte am Geraet mehr, sondern ein Eintrag im Block
        // "Weitere IP-Adressen" - dort haengen Netz und Bezeichnung dran.
        // Deshalb vor dem create() heraus und danach als eigener Datensatz.
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
     * Im Standort-Schritt: einen vorhandenen Standort für den restlichen Durchlauf übernehmen,
     * statt zwingend einen neuen anzulegen.
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
     * Direkt zu einem Schritt springen - vor oder zurueck.
     *
     * Nur zu erlaubten Schritten: allowedSteps() filtert nach _create-Recht,
     * und $key kommt vom Client. Ein unbekannter oder gesperrter Schluessel
     * wird still verworfen, statt den Durchlauf in einen Zustand zu bringen,
     * den der Nutzer gar nicht sehen darf.
     *
     * Der aktuelle Schritt wird dabei weder als erledigt noch als uebersprungen
     * vermerkt: Springen ist kein Bearbeiten.
     */
    public function gotoStep(string $key): void
    {
        [, $run] = $this->guard();

        if (! collect($this->allowedSteps())->contains(fn ($s) => $s['key'] === $key)) {
            return;
        }

        $run->update(['current_step' => $key]);
        $this->resetForm();
    }

    public function skipStep(): void
    {
        [, $run] = $this->guard();
        $step = $this->currentStep($run);

        if ($step) {
            $run->markStepSkipped($step['key']);
        }

        $this->advance($run);
    }

    public function previousStep(): void
    {
        [, $run] = $this->guard();
        $keys = array_column($this->allowedSteps(), 'key');
        $index = array_search($run->current_step, $keys, true);

        if ($index !== false && $index > 0) {
            $run->update(['current_step' => $keys[$index - 1]]);
        }

        $this->resetForm();
    }

    protected function advance(DocumentationRun $run): void
    {
        $keys = array_column($this->allowedSteps(), 'key');
        $index = array_search($run->current_step, $keys, true);
        $nextKey = ($index !== false && isset($keys[$index + 1])) ? $keys[$index + 1] : null;

        $run->update(['current_step' => $nextKey]);

        if ($nextKey === null) {
            $run->update(['completed_at' => now()]);
        }

        $this->resetForm();
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

    public function render()
    {
        [$customer, $run] = $this->guard();
        $steps = $this->allowedSteps();
        $step = $this->currentStep($run);

        $entries = collect();
        $selectOptions = [];

        if ($step) {
            // Alles, was der Kunde schon hat - nicht nur die Eintraege des
            // laufenden Durchlaufs und nicht nur die am gewaehlten Standort.
            // Wer den Assistenten oeffnet, will sehen, was bereits dokumentiert
            // ist, und Luecken darin nachtragen koennen.
            $entries = $step['model']::where('customer_id', $customer->id)
                ->latest()
                ->limit(50)
                ->get();

            foreach ($step['fields'] as $field) {
                if (($field['type'] ?? null) === 'select' && is_string($field['options'] ?? null)) {
                    $selectOptions[$field['name']] = $this->optionsFor($field['options'], $customer, $run);
                }

                // Vorbelegung aus der Config (z. B. Subnetzmaske 255.255.255.0), nur wenn das
                // Feld noch nicht angefasst wurde - sonst würde jeder Re-Render nach save()
                // die Vorgabe zurückschreiben.
                if (! array_key_exists($field['name'], $this->form) && isset($field['default'])) {
                    $this->form[$field['name']] = $field['default'];
                }
            }
        }

        $existingSites = $step && $step['key'] === 'site'
            ? Site::where('customer_id', $customer->id)->orderBy('name')->get()
            : collect();

        return view('livewire.documentation-wizard', [
            'customer' => $customer,
            'run' => $run,
            'steps' => $steps,
            'step' => $step,
            'entries' => $entries,
            'selectOptions' => $selectOptions,
            'existingSites' => $existingSites,
            'finished' => $run->completed_at !== null,
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
