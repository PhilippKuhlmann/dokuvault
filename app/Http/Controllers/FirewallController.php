<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirewallRequest;
use App\Models\Customer;
use App\Models\Firewall;

class FirewallController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Firewall::class);

        $firewalls = $this->getFilteredQuery(Firewall::class, $customer)
            ->latest()->paginate(25);

        return view('firewall.index', compact('customer', 'firewalls'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Firewall::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('firewall.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, FirewallRequest $request)
    {
        $this->authorize('create', Firewall::class);

        $customer->firewalls()->create($request->validated());

        return redirect(route('firewall.index', $customer));
    }

    public function edit(Customer $customer, Firewall $firewall)
    {
        $this->authorize('update', Firewall::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('firewall.edit', compact('customer', 'firewall', 'sites'));
    }

    public function update(Customer $customer, Firewall $firewall, FirewallRequest $request)
    {
        $this->authorize('update', Firewall::class);

        $firewall->update($request->validated());

        return redirect(route('firewall.index', $customer));
    }

    public function destroy(Customer $customer, Firewall $firewall)
    {
        $this->authorize('delete', Firewall::class);

        $firewall->delete();

        return redirect(route('firewall.index', $customer));
    }
}
