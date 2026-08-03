<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccesspointRequest;
use App\Models\Accesspoint;
use App\Models\Customer;

class AccesspointController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Accesspoint::class);

        $accesspoints = $this->getFilteredQuery(Accesspoint::class, $customer)
            ->latest()->paginate(25);

        return view('accesspoint.index', compact('customer', 'accesspoints'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Accesspoint::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('accesspoint.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, AccesspointRequest $request)
    {
        $this->authorize('create', Accesspoint::class);

        $customer->accesspoints()->create($request->validated());

        return redirect(route('accesspoint.index', $customer));
    }

    public function edit(Customer $customer, Accesspoint $accesspoint)
    {
        $this->authorize('update', Accesspoint::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('accesspoint.edit', compact('customer', 'accesspoint', 'sites'));
    }

    public function update(Customer $customer, Accesspoint $accesspoint, AccesspointRequest $request)
    {
        $this->authorize('update', Accesspoint::class);

        $accesspoint->update($request->validated());

        return redirect(route('accesspoint.index', $customer));
    }

    public function destroy(Customer $customer, Accesspoint $accesspoint)
    {
        $this->authorize('delete', Accesspoint::class);

        $accesspoint->delete();

        return redirect(route('accesspoint.index', $customer));
    }
}
