<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\Customer;
use App\Models\Network;
use App\Support\IpAssignment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class DeviceIpAddresses extends Component
{
    use ScopedToCustomer;
    use ValidatesOnInput;

    // Scalars instead of a model instance: robust with polymorphic models and Livewire hydration.
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

    /**
     * Whether the address is obtained by DHCP.
     *
     * A field of its own and not the label: it used to be the very word
     * "DHCP" in the label that triggered it - that stood nowhere, written in
     * lower case it did nothing, and beside it there was no room left for
     * "Uplink Dachboden".
     */
    public bool $dhcp = false;

    /**
     * Take away the half-typed address when the box is ticked.
     *
     * Otherwise a half-typed one would stay, the check would report "must not
     * be present", and the user would look for the mistake in a field they
     * cannot see any more.
     */
    public function updatedDhcp(): void
    {
        if ($this->dhcp) {
            $this->reset('address');
            $this->resetValidation('address');
        }
    }

    /**
     * VLAN chosen: write the next free address from it into the field.
     *
     * An address already entered that fits this network stays - whoever typed
     * it meant it. One that does not fit was meant for another network and is
     * replaced.
     */
    public function updatedNetworkId($id): void
    {
        if ($this->dhcp || ! $id) {
            return;
        }

        $netz = Network::where('customer_id', $this->customerId)->find($id);

        if (! $netz || $netz->containsUrl($this->address)) {
            return;
        }

        $this->address = $netz->nextFreeAddress() ?? '';
        $this->resetValidation('address');
    }

    // Embedded: without a card frame of its own, because the block then sits in the card
    // des Formulars steht (x-create.main, Slot "nach").
    #[Locked]
    public bool $eingebettet = false;

    /** In the modal the frame brings the padding - then none from the block. */
    public bool $randlos = false;

    /** One source for creating and for the check while typing, see IpAssignment. */
    protected function regeln(): array
    {
        return app(IpAssignment::class)->rules($this->customerId, $this->dhcp);
    }

    protected function fieldNames(): array
    {
        return app(IpAssignment::class)->attributeNames();
    }

    public function mount($model, $customer, bool $eingebettet = false, bool $randlos = false): void
    {
        // On mounting, not only on the action: a block that can be built at
        // all with a foreign device has already said too much. The checks in
        // the actions stay - they catch the case that someone swaps the number
        // later.
        $this->onlyOwnCustomer($customer->id);
        abort_if($model->customer_id !== $customer->id, 403);

        $this->modelClass = $model::class;
        $this->modelId = $model->id;
        $this->customerId = $customer->id;
        $this->eingebettet = $eingebettet;
        $this->randlos = $randlos;
    }

    /**
     * Authorisation: permission to edit the device kind AND customer membership.
     * (Public properties can be manipulated on the client -> check on every action.)
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

        $this->enableCheck();

        $validated = $this->validate($this->regeln(), app(IpAssignment::class)->messages(), $this->fieldNames());

        app(IpAssignment::class)->add($device, $validated, $this->dhcp);

        $this->reset('address', 'network_id', 'label', 'dhcp');
        // A list around this block shows the addresses or credentials in its
        // columns - without this message it would stay on the old state.
        $this->dispatch('device-changed');
    }

    /**
     * The VLAN modal is a component of its own (the VLAN list uses it too).
     * When it reports a new network it is selected here at once - one was
     * entering an address, after all.
     */
    #[On('vlan-created')]
    public function vlanUebernehmen(int $id): void
    {
        $this->network_id = $id;
        // Set programmatically does not fire the updated hook - the suggestion
        // should come just as it does when choosing by hand.
        $this->updatedNetworkId($id);
        // A list around this block shows the addresses or credentials in its
        // columns - without this message it would stay on the old state.
        $this->dispatch('device-changed');
    }

    public function remove(int $id): void
    {
        $device = $this->device();

        app(IpAssignment::class)->remove($device, $id);
        // A list around this block shows the addresses or credentials in its
        // columns - without this message it would stay on the old state.
        $this->dispatch('device-changed');
    }

    public function render()
    {
        $device = $this->modelClass::find($this->modelId);

        return view('livewire.device-ip-addresses', [
            'entries' => $device
                ? $device->ipAddresses()->with('network')->latest()->get()
                : collect(),
            'networks' => Network::where('customer_id', $this->customerId)->orderBy('vlanId')->get(),
            // For the VLAN modal: the new network inherits the device's site.
            'kunde' => Customer::find($this->customerId),
            'geraeteStandort' => $device?->site_id,
        ]);
    }
}
