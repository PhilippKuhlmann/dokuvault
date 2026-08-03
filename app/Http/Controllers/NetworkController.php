<?php

namespace App\Http\Controllers;

use App\Http\Requests\NetworkRequest;
use App\Models\Customer;
use App\Models\Network;

class NetworkController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Network::class);

        $networks = $this->getFilteredQuery(Network::class, $customer)
            ->orderBy('vlanId')
            ->paginate(25);

        return view('network.index', compact('customer', 'networks'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Network::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('network.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, NetworkRequest $request)
    {
        $this->authorize('create', Network::class);

        $customer->networks()->create($request->validated());

        return redirect(route('network.index', $customer))->withSuccess('Gespeichert!');
    }

    public function edit(Customer $customer, Network $network)
    {
        $this->authorize('update', Network::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('network.edit', compact('customer', 'network', 'sites'));
    }

    public function update(Customer $customer, Network $network, NetworkRequest $request)
    {
        $this->authorize('update', Network::class);

        $network->update($request->validated());

        return redirect(route('network.index', $customer))->withSuccess('Gespeichert!');
    }

    public function destroy(Customer $customer, Network $network)
    {
        $this->authorize('delete', Network::class);

        $wifis = $network->wifis()->get();

        foreach ($wifis as $wifi) {
            $wifi->delete();
        }

        $network->delete();

        return redirect(route('network.index', $customer))->withSuccess('Gelöscht!');
    }
}
