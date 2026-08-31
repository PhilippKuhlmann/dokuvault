<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Models\Customer;
use App\Models\Network;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class DeviceIpAddresses extends Component
{
    use GehoertZumKunden;

    // Skalare statt Model-Instanz: robust bei polymorphen Modellen und Livewire-Hydration.
    #[Locked]
    public string $modelClass;

    #[Locked]
    public int $modelId;

    #[Locked]
    public int $customerId;

    // Formular für neuen Eintrag
    public string $address = '';

    public $network_id = '';

    public string $label = '';

    // Eingebettet: ohne eigenen Kartenrahmen, weil der Block dann in der Karte
    // des Formulars steht (x-create.main, Slot "nach").
    #[Locked]
    public bool $eingebettet = false;

    /** Im Modal bringt der Rahmen das Padding mit - dann keins vom Block. */
    public bool $randlos = false;

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
    protected function device()
    {
        $device = $this->modelClass::findOrFail($this->modelId);

        Gate::authorize(strtolower(class_basename($this->modelClass)).'_update');

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $device->customer_id, 403);
        abort_if($device->customer_id !== $this->customerId, 403);

        return $device;
    }

    /**
     * Die Adresse darf beim Kunden noch nicht vergeben sein.
     *
     * Geloeschte Eintraege zaehlen nicht mit: Was im Papierkorb liegt, belegt
     * keine Adresse - sonst blieben Adressen nach dem Aufraeumen gesperrt.
     */
    protected function nochNichtVergeben(): Unique
    {
        return Rule::unique('ip_addresses', 'address')
            ->where('customer_id', $this->customerId)
            ->whereNull('deleted_at');
    }

    public function add(): void
    {
        $device = $this->device();

        $validated = $this->validate([
            // Eine Adresse gibt es beim selben Kunden nur einmal. Vorher liess
            // sich dieselbe IP zweimal am selben Geraet und zusaetzlich an
            // einem zweiten eintragen - danach stand in der Doku, sie gehoere
            // zu beiden, und der IP-Plan zeigte sie doppelt als belegt.
            'address' => ['required', 'ip', $this->nochNichtVergeben()],
            'network_id' => ['nullable', Rule::exists('networks', 'id')->where('customer_id', $this->customerId)],
            'label' => ['nullable', 'max:255'],
        ], [
            'address.unique' => __('Diese Adresse ist bei diesem Kunden schon vergeben.'),
        ]);

        $device->ipAddresses()->create([
            'customer_id' => $this->customerId,
            'network_id' => $validated['network_id'] ?: null,
            'address' => $validated['address'],
            'label' => $validated['label'] ?: null,
        ]);

        $this->reset('address', 'network_id', 'label');
        // Eine Liste um diesen Block herum zeigt die Adressen bzw. Zugangsdaten
        // in ihren Spalten - ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    /**
     * Das VLAN-Modal ist eine eigene Komponente (auch die VLAN-Liste nutzt sie).
     * Meldet sie ein neues Netz, wird es hier gleich ausgewaehlt - man war ja
     * beim Eintragen einer Adresse.
     */
    #[On('vlan-angelegt')]
    public function vlanUebernehmen(int $id): void
    {
        $this->network_id = $id;
        // Eine Liste um diesen Block herum zeigt die Adressen bzw. Zugangsdaten
        // in ihren Spalten - ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    public function remove(int $id): void
    {
        $device = $this->device();

        $device->ipAddresses()->whereKey($id)->delete();
        // Eine Liste um diesen Block herum zeigt die Adressen bzw. Zugangsdaten
        // in ihren Spalten - ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    public function render()
    {
        $device = $this->modelClass::find($this->modelId);

        return view('livewire.device-ip-addresses', [
            'entries' => $device
                ? $device->ipAddresses()->with('network')->latest()->get()
                : collect(),
            'networks' => Network::where('customer_id', $this->customerId)->orderBy('vlanId')->get(),
            // Fuer das VLAN-Modal: Das neue Netz erbt den Standort des Geraets.
            'kunde' => Customer::find($this->customerId),
            'geraeteStandort' => $device?->site_id,
        ]);
    }
}
