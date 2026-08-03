<?php

namespace App\Http\Controllers;

use App\Http\Requests\NetworkSwitchRequest;
use App\Models\Customer;
use App\Models\NetworkSwitch;

class NetworkSwitchController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', NetworkSwitch::class);

        $networkswitches = $this->getFilteredQuery(NetworkSwitch::class, $customer)
            ->latest()->paginate(25);

        return view('networkswitch.index', compact('customer', 'networkswitches'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', NetworkSwitch::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('networkswitch.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, NetworkSwitchRequest $request)
    {
        $this->authorize('create', NetworkSwitch::class);

        $customer->networkswitches()->create($request->validated());

        return redirect(route('networkswitch.index', $customer));
    }

    public function edit(Customer $customer, NetworkSwitch $networkswitch)
    {
        $this->authorize('update', NetworkSwitch::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('networkswitch.edit', compact('customer', 'networkswitch', 'sites'));
    }

    public function update(Customer $customer, NetworkSwitch $networkswitch, NetworkSwitchRequest $request)
    {
        $this->authorize('update', NetworkSwitch::class);

        $networkswitch->update($request->validated());

        return redirect(route('networkswitch.index', $customer));
    }

    public function destroy(Customer $customer, NetworkSwitch $networkswitch)
    {
        $this->authorize('delete', NetworkSwitch::class);

        $networkswitch->delete();

        return redirect(route('networkswitch.index', $customer));
    }
}
