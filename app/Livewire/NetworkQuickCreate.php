<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\Network;
use App\Models\Site;
use App\Support\NetworkEditing;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Create a VLAN without leaving the page.
 *
 * Two places of use: in the block "Weitere IP-Adressen" on the device (where
 * one notices while entering an address that the network is missing) and above
 * the VLAN list. Hence a component of its own instead of two copies - the same
 * form in two places would go wrong.
 *
 * The site comes from the calling device if there is one; otherwise one picks
 * it in the modal. customer_id always comes from the component, never from the
 * Formular.
 */
class NetworkQuickCreate extends Component
{
    use ScopedToCustomer;
    use ValidatesOnInput;

    #[Locked]
    public int $customerId;

    /** Given by the device - then the select in the modal is dropped. */
    #[Locked]
    public ?int $siteId = null;

    public bool $offen = false;

    /** Set means: edit instead of create. */
    public ?int $bearbeiteId = null;

    /** A confirmation before deleting - as a view of its own instead of a browser dialog. */
    public bool $loeschenGefragt = false;

    public $site_id = '';

    public string $description = '';

    public $vlanId = '';

    public string $network = '';

    public string $subnetmask = '255.255.255.0';

    /** Matches the default of the mask - both fields start in agreement. */
    public $cidr = 24;

    public string $gateway = '';

    public string $dns1 = '';

    public string $dns2 = '';

    public string $dhcpStart = '';

    public string $dhcpEnd = '';

    /** Look of the triggering button - a text link on the device, a full button in the list. */
    public string $knopfKlassen = '';

    public string $label = '';

    public bool $mitSymbol = false;

    /**
     * Whether the own trigger button is shown. In the documentation wizard
     * only the edit modal is embedded (opened through the event vlan-edit) -
     * there the wizard creates things itself, and a second "+ new VLAN"
     * button would be one too many.
     */
    #[Locked]
    public bool $mitKnopf = true;

    public function mount($customer, ?int $siteId = null, string $knopfKlassen = '', string $label = '', bool $mitSymbol = false, bool $mitKnopf = true): void
    {
        $this->onlyOwnCustomer($customer->id);

        $this->customerId = $customer->id;
        $this->siteId = $siteId;
        $this->knopfKlassen = $knopfKlassen;
        $this->label = $label;
        $this->mitSymbol = $mitSymbol;
        $this->mitKnopf = $mitKnopf;
    }

    /**
     * The pencil in the list: the same modal, only with loaded values.
     *
     * The network is checked against the customer - the id comes from the
     * browser, and without the check a foreign network could be opened with it.
     */
    #[On('vlan-edit')]
    public function bearbeiten(int $id): void
    {
        Gate::authorize('network_update');

        $netz = Network::where('customer_id', $this->customerId)->findOrFail($id);

        $this->bearbeiteId = $netz->id;
        $this->site_id = $netz->site_id;
        $this->description = (string) $netz->description;
        $this->vlanId = $netz->vlanId;
        $this->network = (string) $netz->network;
        $this->subnetmask = (string) $netz->subnetmask;
        // Legacy networks partly have the mask only. Then derive the number
        // from it instead of showing the field empty.
        $this->cidr = $netz->cidr ?? Network::cidrAusMaske($netz->subnetmask) ?? '';
        $this->gateway = (string) $netz->gateway;
        $this->dns1 = (string) $netz->dns1;
        $this->dns2 = (string) $netz->dns2;
        $this->dhcpStart = (string) $netz->dhcpStart;
        $this->dhcpEnd = (string) $netz->dhcpEnd;

        $this->resetErrorBag();
        $this->loeschenGefragt = false;
        $this->offen = true;
    }

    /**
     * Mask and CIDR say the same thing in two notations - whoever enters one
     * gets the other with it.
     *
     * If the input yields no valid mask (a typo, half typed), the partner
     * field is left untouched instead of being emptied: otherwise the value
     * would be gone as soon as one mistypes once.
     */
    public function updatedSubnetmask(): void
    {
        $cidr = Network::cidrAusMaske($this->subnetmask);

        if ($cidr !== null) {
            $this->cidr = $cidr;
            // The partner field has just received a valid value - its red
            // border should go. updated() ran before this value was there.
            // Wert stand.
            $this->checkPeer('cidr');
        }
    }

    public function updatedCidr(): void
    {
        $maske = Network::maskeAusCidr($this->cidr);

        if ($maske !== null) {
            $this->subnetmask = $maske;
            $this->checkPeer('subnetmask');
        }
    }

    /** Mask and CIDR set each other - see updatedSubnetmask(). */
    private function checkPeer(string $feld): void
    {
        $this->validateOnInput($feld);
    }

    /**
     * Everything back to the start - one place for it, because it would
     * otherwise be the same long field list in four places and one would drift.
     */
    protected function clearForm(): void
    {
        // subnetmask and cidr are included: reset() falls back to the class
        // defaults, so to 255.255.255.0 and 24 - the two belong together and
        // should stay that way when cleaning up.
        $this->reset('offen', 'bearbeiteId', 'loeschenGefragt', 'site_id', 'description',
            'vlanId', 'network', 'subnetmask', 'cidr', 'gateway', 'dns1', 'dns2',
            'dhcpStart', 'dhcpEnd', 'geprueft');
        $this->resetErrorBag();
    }

    /**
     * The "new" button.
     *
     * It used to set only offen=true. Whoever had edited a VLAN before and
     * cancelled got the modal with "edit VLAN", the old values and the old
     * editedId - saving would have overwritten the existing network instead of
     * creating a new one.
     */
    public function neu(): void
    {
        $this->clearForm();
        $this->offen = true;
    }

    /**
     * Cancel and escape: close and clean up. Closing alone is not enough, the
     * state would otherwise still be there on the next opening.
     */
    public function cancel(): void
    {
        $this->clearForm();
    }

    /**
     * The rules - one source for saving and for the running check while
     * typing. The same list twice would drift apart sooner or later.
     * spaeter auseinander.
     */
    protected function regeln(): array
    {
        // Without a given site one has to be chosen. The rules sit in
        // NetworkEditing - the same ones for the iOS app.
        return app(NetworkEditing::class)->rules($this->customerId, ! $this->siteId || $this->bearbeiteId);
    }

    /** The labels for the messages - "Bitte Bezeichnung angeben.". */
    protected function fieldNames(): array
    {
        return app(NetworkEditing::class)->attributeNames();
    }

    public function save(): void
    {
        Gate::authorize($this->bearbeiteId ? 'network_update' : 'network_create');

        $this->enableCheck();

        $daten = $this->validate($this->regeln(), [], $this->fieldNames());

        $werte = app(NetworkEditing::class)->values(
            $daten, $this->siteId && ! $this->bearbeiteId ? $this->siteId : (int) $daten['site_id']
        );

        if ($this->bearbeiteId) {
            // Checked against the customer again: editedId can be manipulated
            // between opening and saving.
            $netz = Network::where('customer_id', $this->customerId)->findOrFail($this->bearbeiteId);
            $netz->update($werte);
        } else {
            $netz = Network::create($werte + ['customer_id' => $this->customerId]);
        }

        $this->dispatch('notice', text: $this->bearbeiteId
            ? __('VLAN gespeichert.')
            : __('VLAN angelegt.'));

        // Only after the message: editedId decides on its wording.
        $this->clearForm();

        // The IP block picks the new network straight away, the list
        // re-renders. No redirect any more: since the list is Livewire itself
        // the event is enough - that saves the page change and its pitfalls
        // (405 on the update address, lost dark mode).
        $this->dispatch('vlan-created', id: $netz->id);
    }

    /**
     * Delete from the edit modal - the own form had the delete card for that,
     * here the way was missing.
     *
     * Checked against the customer as when saving; the id comes from the
     * browser. The network lands in the trash (soft delete), linked IP
     * addresses keep their network_id and point at nothing - the same
     * behaviour as deleting through the old page.
     */
    public function delete(): void
    {
        Gate::authorize('network_delete');

        if (! $this->bearbeiteId) {
            return;
        }

        Network::where('customer_id', $this->customerId)
            ->findOrFail($this->bearbeiteId)
            ->delete();

        $this->clearForm();

        $this->dispatch('notice', text: __('VLAN gelöscht.'));
        $this->dispatch('vlan-created', id: 0);
    }

    public function render()
    {
        return view('livewire.network-quick-create', [
            // Only when no site is given - otherwise the list would be pointless.
            // Always show when editing: the site of an existing network should
            // be changeable, even when the modal hangs on the device.
            'sites' => $this->siteId && ! $this->bearbeiteId
                ? collect()
                : Site::where('customer_id', $this->customerId)->orderBy('name')->get(),
        ]);
    }
}
