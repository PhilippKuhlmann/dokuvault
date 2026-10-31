<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\Customer;
use App\Models\Network;
use App\Support\IpRangeEditing;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reserved ranges of a network - create and delete again.
 *
 * It sits on the network, not in a list of its own in the menu: a range only
 * makes sense where one sees it. The same pattern as the IP addresses and the
 * credentials on the device.
 *
 * A range takes nothing. It records what a piece of the network is meant for
 * - "10.10.250.10 to .20 are for the Proxmox servers" - even when only two
 * addresses of it are in use.
 */
class IpRanges extends Component
{
    use ScopedToCustomer;
    use ValidatesOnInput;

    // Locked: set in mount() after the tenant check. Changed in the browser,
    // they pointed every action at another customer's network.
    #[Locked]
    public int $customerId;

    #[Locked]
    public int $networkId;

    public bool $offen = false;

    public string $from_ip = '';

    public string $to_ip = '';

    public string $label = '';

    public string $note = '';

    public function mount(Customer $customer, Network $network): void
    {
        // Zwei verschiedene Fragen, beide noetig: Darf dieser Benutzer diesen
        // customers see - and does this network belong to them? Without the
        // first, a user tied to one customer would reach another's network plan.
        $this->onlyOwnCustomer($customer->id);

        abort_unless($network->customer_id === $customer->id, 404);

        $this->customerId = $customer->id;
        $this->networkId = $network->id;
    }

    /** The rules sit in IpRangeEditing - the same ones for the iOS app. */
    protected function regeln(): array
    {
        return app(IpRangeEditing::class)->rules();
    }

    protected function fieldNames(): array
    {
        return app(IpRangeEditing::class)->attributeNames();
    }

    public function oeffnen(): void
    {
        Gate::authorize('network_update');

        $this->reset('from_ip', 'to_ip', 'label', 'note', 'geprueft');
        $this->resetErrorBag();
        $this->offen = true;
    }

    public function cancel(): void
    {
        $this->reset('offen', 'from_ip', 'to_ip', 'label', 'note', 'geprueft');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        Gate::authorize('network_update');

        $this->enableCheck();
        $daten = $this->validate($this->regeln(), [], $this->fieldNames());

        app(IpRangeEditing::class)->create($this->netz(), $daten);

        $this->cancel();

        // The address list above it is drawn by the controller, not by this
        // component - without the rebuild the new range would stay invisible,
        // and that is exactly what one wanted to see.
        $this->redirect(request()->header('Referer') ?: route('ipplan.index', $this->customerId), navigate: true);
    }

    public function delete(int $id): void
    {
        Gate::authorize('network_update');

        // Only ranges of this network - a foreign id slipped in would
        // sonst loeschbar.
        app(IpRangeEditing::class)->delete($this->netz(), $id);

        $this->redirect(request()->header('Referer') ?: route('ipplan.index', $this->customerId), navigate: true);
    }

    private function netz(): Network
    {
        // Every action and every render binds to the user's customer again -
        // not only mount(). The ids are locked as well, see above.
        $this->onlyOwnCustomer($this->customerId);

        return Network::where('customer_id', $this->customerId)->findOrFail($this->networkId);
    }

    public function render()
    {
        return view('livewire.ip-ranges', [
            'bereiche' => app(IpRangeEditing::class)->ranges($this->netz()),
            'darfPflegen' => Gate::allows('network_update'),
        ]);
    }
}
