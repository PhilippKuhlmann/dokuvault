<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Support\CredentialLinking;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Verknüpft ein Gerät mit Logins aus "Logins Allgemein".
 *
 * Absichtlich keine eigenen Passwortfelder am Gerät: Ein Passwort gilt oft für
 * mehrere Systeme. Es steht einmal im Login-Eintrag und wird hier nur angehängt -
 * beim Wechseln muss man dann eine Stelle ändern, nicht fünf.
 */
class DeviceCredentials extends Component
{
    use GehoertZumKunden;
    use PrueftWaehrendDerEingabe;

    // Skalare statt Model-Instanz: robust bei polymorphen Modellen und Livewire-Hydration.
    #[Locked]
    public string $modelClass;

    #[Locked]
    public int $modelId;

    #[Locked]
    public int $customerId;

    // Vorhandenes Login anhängen
    public $login_id = '';

    public string $note = '';

    // Neues Login anlegen und gleich anhängen
    public bool $neu = false;

    public string $name = '';

    public string $username = '';

    public string $password = '';

    // Eingebettet: ohne eigenen Kartenrahmen, weil der Block dann in der Karte
    // des Formulars steht (x-create.main, Slot "nach").
    #[Locked]
    public bool $eingebettet = false;

    /** Im Modal bringt der Rahmen das Padding mit - dann keins vom Block. */
    public bool $randlos = false;

    /**
     * Die Regeln beider Formulare zusammen.
     *
     * Der Block hat zwei: eines haengt vorhandene Zugangsdaten an, eines legt
     * neue an. Zusammen sind sie richtig, weil waehrend der Eingabe immer nur
     * das eine geaenderte Feld geprueft wird - wer einen Namen tippt, loest
     * keine Pruefung von login_id aus.
     */
    protected function regeln(): array
    {
        $verknuepfung = app(CredentialLinking::class);

        return [
            ...$verknuepfung->attachRules($this->customerId, auth()->user()),
            ...$verknuepfung->createRules(),
        ];
    }

    protected function feldnamen(): array
    {
        return app(CredentialLinking::class)->attributeNames();
    }

    public function mount($model, $customer, bool $eingebettet = false, bool $randlos = false): void
    {
        // Beim Einhaengen, nicht erst bei der Aktion: Ein Block, der sich mit
        // einem fremden Geraet ueberhaupt aufbauen laesst, hat schon zu viel
        // gesagt. Die Pruefungen in den Aktionen bleiben - sie fangen den Fall
        // ab, dass jemand die Nummer spaeter austauscht.
        $this->nurEigenerKunde($customer->id);
        abort_if($model->customer_id !== $customer->id, 403);

        $this->modelClass = $model::class;
        $this->modelId = $model->id;
        $this->customerId = $customer->id;
        $this->eingebettet = $eingebettet;
        $this->randlos = $randlos;
    }

    /**
     * Autorisierung: Recht zum Bearbeiten des Gerätetyps UND Kundenzugehörigkeit.
     * (Public Properties sind client-seitig manipulierbar → bei jeder Aktion prüfen.)
     */
    /**
     * Welche Arten von Zugangsdaten dieser Benutzer sehen darf.
     *
     * Kennwoerter und SSH-Schluessel liegen in derselben Tabelle, haben aber
     * getrennte Rechte. Ohne diese Unterscheidung saehe jeder mit Login-Recht
     * ueber den Geraeteblock auch die Schluessel.
     *
     * @return array<int, string>
     */
    protected function sichtbareArten(): array
    {
        return app(CredentialLinking::class)->visibleKinds(auth()->user());
    }

    protected function device()
    {
        $device = $this->modelClass::findOrFail($this->modelId);

        Gate::authorize(strtolower(class_basename($this->modelClass)).'_update');

        // Eine der beiden Arten genuegt - welche, entscheidet sichtbareArten().
        abort_if($this->sichtbareArten() === [], 403);

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $device->customer_id, 403);
        abort_if($device->customer_id !== $this->customerId, 403);

        return $device;
    }

    public function attach(): void
    {
        $device = $this->device();

        $this->pruefungEinschalten();

        $validated = $this->validate(
            Arr::only($this->regeln(), ['login_id', 'note']), [], $this->feldnamen()
        );

        $this->verknuepfen($device, (int) $validated['login_id'], $validated['note']);
        // Eine Liste um diesen Block herum zeigt die Adressen bzw. Zugangsdaten
        // in ihren Spalten - ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    public function create(): void
    {
        $device = $this->device();
        Gate::authorize('logingeneral_create');

        $this->pruefungEinschalten();

        $validated = $this->validate(
            Arr::only($this->regeln(), ['name', 'username', 'password', 'note']), [], $this->feldnamen()
        );

        app(CredentialLinking::class)->create($device, $validated);
        $this->reset('login_id', 'note');
        $this->reset('name', 'username', 'password', 'neu');
        // Eine Liste um diesen Block herum zeigt die Adressen bzw. Zugangsdaten
        // in ihren Spalten - ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    /** Doppelte Verknüpfungen still übergehen - der Unique-Index würde sonst 500 werfen. */
    protected function verknuepfen($device, int $loginId, ?string $note): void
    {
        app(CredentialLinking::class)->attach($device, $loginId, $note);

        $this->reset('login_id', 'note');
    }

    /** Löst nur die Verknüpfung - der Login-Eintrag selbst bleibt bestehen. */
    public function detach(int $id): void
    {
        $device = $this->device();

        app(CredentialLinking::class)->detach($device, $id);
        // Eine Liste um diesen Block herum zeigt die Adressen bzw. Zugangsdaten
        // in ihren Spalten - ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    public function render()
    {
        $device = $this->modelClass::find($this->modelId);
        $arten = $this->sichtbareArten();
        $entries = ($device ? $device->zugangsdaten() : collect())
            ->filter(fn ($link) => in_array($link->login->kind, $arten, true))
            ->values();

        return view('livewire.device-credentials', [
            'entries' => $entries,
            // Als Model, nicht als ID: die Kunden-Route bindet über den Slug.
            'kunde' => Customer::find($this->customerId),
            // Schon verknüpfte Logins fliegen aus der Auswahl - sie ein zweites
            // Mal anzubieten wäre eine Sackgasse.
            // Auch die Schluessel: An ein Geraet gehoert genauso gut ein
            // SSH-Schluessel wie ein Kennwort - sonst liesse er sich nirgends
            // anhaengen. Nur dieser Filter faellt weg, der Papierkorb bleibt.
            // Nach Art gruppiert: In einer gemischten Liste sieht man einem
            // Namen nicht an, ob dahinter ein Kennwort oder ein Schluessel steht.
            'logins' => LoginGeneral::withoutGlobalScope(LoginGeneral::SCOPE)
                ->where('customer_id', $this->customerId)
                ->whereIn('kind', $arten)
                ->whereNotIn('id', $entries->pluck('login_general_id'))
                ->orderBy('name')->get()
                ->groupBy(fn ($login) => $login->istSchluessel() ? 'sshkey' : 'password'),
        ]);
    }
}
