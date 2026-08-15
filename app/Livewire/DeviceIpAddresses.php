<?php

namespace App\Livewire;

use App\Models\Network;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DeviceIpAddresses extends Component
{
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

    // Schnell ein VLAN anlegen, ohne das Formular zu verlassen. Nur die
    // Pflichtangaben - Gateway, DNS und DHCP ergaenzt man spaeter im richtigen
    // VLAN-Formular.
    public bool $vlanModal = false;

    public string $vlanDescription = '';

    public $vlanNummer = '';

    public string $vlanNetwork = '';

    public string $vlanSubnetmask = '255.255.255.0';

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

    /**
     * Legt ein VLAN an, ohne das Geraeteformular zu verlassen, und waehlt es
     * gleich aus - man traegt gerade eine IP ein und merkt, dass das Netz noch
     * fehlt; ohne das waere die halb ausgefuellte Zeile weg.
     *
     * Der Standort kommt vom Geraet, nicht aus dem Formular: Ein VLAN gehoert
     * dorthin, wo das Geraet steht, und ein Feld dafuer waere eine Angriffs-
     * flaeche mehr.
     */
    public function vlanAnlegen(): void
    {
        $device = $this->device();
        Gate::authorize('network_create');

        $daten = $this->validate([
            'vlanDescription' => ['required', 'max:255'],
            'vlanNummer' => ['nullable', 'integer', 'min:1', 'max:4094'],
            'vlanNetwork' => ['required', 'ipv4'],
            'vlanSubnetmask' => ['required', 'ipv4'],
        ], [], [
            'vlanDescription' => __('Bezeichnung'),
            'vlanNummer' => __('VLAN-ID'),
            'vlanNetwork' => __('Netz'),
            'vlanSubnetmask' => __('Subnetzmaske'),
        ]);

        $netz = Network::create([
            'customer_id' => $this->customerId,
            'site_id' => $device->site_id ?? null,
            'description' => $daten['vlanDescription'],
            'vlanId' => $daten['vlanNummer'] ?: null,
            'network' => $daten['vlanNetwork'],
            'subnetmask' => $daten['vlanSubnetmask'],
        ]);

        // Gleich ausgewaehlt: Der Nutzer war beim Eintragen einer Adresse.
        $this->network_id = $netz->id;

        $this->reset('vlanModal', 'vlanDescription', 'vlanNummer', 'vlanNetwork');
        $this->vlanSubnetmask = '255.255.255.0';
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
