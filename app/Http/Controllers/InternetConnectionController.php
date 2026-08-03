<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternetConnectionRequest;
use App\Models\Customer;
use App\Models\InternetConnection;

class InternetConnectionController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', InternetConnection::class);
        $internetconnections = $this->getFilteredQuery(InternetConnection::class, $customer)->latest()->paginate(25);

        return view('internetconnection.index', compact('customer', 'internetconnections'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', InternetConnection::class);
        $sites = $this->getSitesForCustomer($customer);

        return view('internetconnection.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, InternetConnectionRequest $request)
    {
        $this->authorize('create', InternetConnection::class);
        $customer->internetconnections()->create($request->validated());

        return redirect(route('internetconnection.index', $customer));
    }

    public function edit(Customer $customer, InternetConnection $internetconnection)
    {
        $this->authorize('update', InternetConnection::class);
        $sites = $this->getSitesForCustomer($customer);

        return view('internetconnection.edit', compact('customer', 'internetconnection', 'sites'));
    }

    public function update(Customer $customer, InternetConnection $internetconnection, InternetConnectionRequest $request)
    {
        $this->authorize('update', InternetConnection::class);
        $internetconnection->update($request->validated());

        return redirect(route('internetconnection.index', $customer));
    }

    public function destroy(Customer $customer, InternetConnection $internetconnection)
    {
        $this->authorize('delete', InternetConnection::class);
        $internetconnection->delete();

        return redirect(route('internetconnection.index', $customer));
    }
}
