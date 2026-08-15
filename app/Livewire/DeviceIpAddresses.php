<?php

namespace App\Livewire;

use App\Models\Network;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DeviceIpAddresses extends Component
{
    // Skalare statt Model-Instanz: robust bei polymorphen Modellen und Livewire-Hydration.
    public string $modelClass;

    public int $modelId;

    public int $customerId;

    // Formular für neuen Eintrag
    public string $address = '';

    public $network_id = '';

    public string $label = '';

    // Eingebettet: ohne eigenen Kartenrahmen, weil der Block dann in der Karte
    // des Formulars steht (x-create.main, Slot "nach").
    public bool $eingebettet = false;

    public function mount($model, $customer, bool $eingebettet = false): void
    {
        $this->modelClass = $model::class;
        $this->modelId = $model->id;
        $this->customerId = $customer->id;
        $this->eingebettet = $eingebettet;
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

    public function add(): void
    {
        $device = $this->device();

        $validated = $this->validate([
            'address' => ['required', 'ip'],
            'network_id' => ['nullable', Rule::exists('networks', 'id')->where('customer_id', $this->customerId)],
            'label' => ['nullable', 'max:255'],
        ]);

        $device->ipAddresses()->create([
            'customer_id' => $this->customerId,
            'network_id' => $validated['network_id'] ?: null,
            'address' => $validated['address'],
            'label' => $validated['label'] ?: null,
        ]);

        $this->reset('address', 'network_id', 'label');
    }

    public function remove(int $id): void
    {
        $device = $this->device();

        $device->ipAddresses()->whereKey($id)->delete();
    }

    public function render()
    {
        $device = $this->modelClass::find($this->modelId);

        return view('livewire.device-ip-addresses', [
            'entries' => $device
                ? $device->ipAddresses()->with('network')->latest()->get()
                : collect(),
            'networks' => Network::where('customer_id', $this->customerId)->orderBy('vlanId')->get(),
        ]);
    }
}
