<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\LoginGeneral;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
    // Skalare statt Model-Instanz: robust bei polymorphen Modellen und Livewire-Hydration.
    public string $modelClass;

    public int $modelId;

    public int $customerId;

    // Vorhandenes Login anhängen
    public $login_id = '';

    public string $note = '';

    // Neues Login anlegen und gleich anhängen
    public bool $neu = false;

    public string $name = '';

    public string $username = '';

    public string $password = '';

    public function mount($model, $customer): void
    {
        $this->modelClass = $model::class;
        $this->modelId = $model->id;
        $this->customerId = $customer->id;
    }

    /**
     * Autorisierung: Recht zum Bearbeiten des Gerätetyps UND Kundenzugehörigkeit.
     * (Public Properties sind client-seitig manipulierbar → bei jeder Aktion prüfen.)
     */
    protected function device()
    {
        $device = $this->modelClass::findOrFail($this->modelId);

        Gate::authorize(strtolower(class_basename($this->modelClass)).'_update');
        Gate::authorize('logingeneral_viewAny');

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $device->customer_id, 403);
        abort_if($device->customer_id !== $this->customerId, 403);

        return $device;
    }

    public function attach(): void
    {
        $device = $this->device();

        $validated = $this->validate([
            // Kundengebunden geprüft: sonst hängt man sich mit einer geratenen ID
            // fremde Zugangsdaten an das eigene Gerät.
            'login_id' => ['required', Rule::exists('login_generals', 'id')
                ->where('customer_id', $this->customerId)->whereNull('deleted_at')],
            'note' => ['nullable', 'max:255'],
        ]);

        $this->verknuepfen($device, (int) $validated['login_id'], $validated['note']);
    }

    public function create(): void
    {
        $device = $this->device();
        Gate::authorize('logingeneral_create');

        $validated = $this->validate([
            'name' => ['required', 'max:255'],
            'username' => ['nullable', 'max:255'],
            'password' => ['nullable', 'max:255'],
            'note' => ['nullable', 'max:255'],
        ]);

        $login = LoginGeneral::create([
            'customer_id' => $this->customerId,
            'name' => $validated['name'],
            'username' => $validated['username'] ?: null,
            'password' => $validated['password'] ?: null,
        ]);

        $this->verknuepfen($device, $login->id, $validated['note']);
        $this->reset('name', 'username', 'password', 'neu');
    }

    /** Doppelte Verknüpfungen still übergehen - der Unique-Index würde sonst 500 werfen. */
    protected function verknuepfen($device, int $loginId, ?string $note): void
    {
        $device->credentialLinks()->firstOrCreate(
            ['login_general_id' => $loginId],
            ['customer_id' => $this->customerId, 'note' => $note ?: null],
        );

        $this->reset('login_id', 'note');
    }

    /** Löst nur die Verknüpfung - der Login-Eintrag selbst bleibt bestehen. */
    public function detach(int $id): void
    {
        $device = $this->device();

        $device->credentialLinks()->whereKey($id)->delete();
    }

    public function render()
    {
        $device = $this->modelClass::find($this->modelId);
        $entries = $device ? $device->zugangsdaten() : collect();

        return view('livewire.device-credentials', [
            'entries' => $entries,
            // Als Model, nicht als ID: die Kunden-Route bindet über den Slug.
            'kunde' => Customer::find($this->customerId),
            // Schon verknüpfte Logins fliegen aus der Auswahl - sie ein zweites
            // Mal anzubieten wäre eine Sackgasse.
            'logins' => LoginGeneral::where('customer_id', $this->customerId)
                ->whereNotIn('id', $entries->pluck('login_general_id'))
                ->orderBy('name')->get(),
        ]);
    }
}
