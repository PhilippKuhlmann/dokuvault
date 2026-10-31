<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The VLAN list as a Livewire component.
 *
 * The reason for the rebuild: creating runs through a modal
 * (NetworkQuickCreate). As long as the list was static Blade, the whole page
 * had to be reloaded afterwards - and every way there had a pitfall of its own
 * (a redirect to the Livewire update address, lost dark mode). Now a re-render
 * is enough.
 */
class NetworkList extends Component
{
    use ScopedToCustomer;
    use WithPagination;

    #[Locked]
    public int $customerId;

    /**
     * In the URL as in the other search components - a filtered result can be
     * passed on that way.
     */
    #[Url]
    public string $search = '';

    /**
     * Back to page one after every keystroke: otherwise one searches on page
     * three and sees nothing although there are hits.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(Customer $customer): void
    {
        Gate::authorize('viewAny', Network::class);
        $this->onlyOwnCustomer($customer->id);

        $this->customerId = $customer->id;
    }

    /**
     * Re-render after creating in the modal - no page change needed.
     * Back to page one, because the list is sorted by VLAN number and the new
     * entry would otherwise land on a page one is not looking at.
     */
    #[On('vlan-created')]
    public function reloaded(): void
    {
        $this->resetPage();
    }

    /**
     * Site filter and sorting as in Controller::getFilteredQuery().
     *
     * Deliberately rebuilt here instead of touching the base class of every
     * controller: as long as only this one list runs on Livewire, a rebuild
     * there would be an intervention in forty controllers for one user. Once
     * the other lists follow, this belongs in a shared trait.
     */
    public function render()
    {
        Gate::authorize('viewAny', Network::class);

        $customer = Customer::findOrFail($this->customerId);
        $site = session()->get('site');

        $query = Network::where('customer_id', $customer->id);

        if ($site && $site !== 'all' && $customer->sites()->whereKey($site)->exists()) {
            $query->where('site_id', $site);
        }

        // The search covers what one has in mind when looking for a VLAN:
        // label, number, network and gateway. The DNS and DHCP fields stay
        // out - nobody searches for those, they would only bloat the hit list.
        // The bracketed expression is needed so the OR does not undo the
        // customer and site filter.
        if ($this->search !== '') {
            $query->whereEnthaelt(['description', 'vlanId', 'network', 'gateway'], $this->search);
        }

        return view('livewire.network-list', [
            'customer' => $customer,
            'networks' => $query->orderBy('vlanId')->paginate(Setting::seiteListe()),
        ]);
    }
}
