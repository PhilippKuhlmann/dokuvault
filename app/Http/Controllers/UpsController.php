<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsRequest;
use App\Models\Customer;
use App\Models\Ups;

class UpsController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Ups::class);
        $ups = $this->getFilteredQuery(Ups::class, $customer)->latest()->paginate(25);

        return view('ups.index', compact('customer', 'ups'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Ups::class);
        $sites = $this->getSitesForCustomer($customer);

        return view('ups.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, UpsRequest $request)
    {
        $this->authorize('create', Ups::class);
        $customer->ups()->create($request->validated());

        return redirect(route('ups.index', $customer));
    }

    public function edit(Customer $customer, Ups $ups)
    {
        $this->authorize('update', Ups::class);
        $sites = $this->getSitesForCustomer($customer);

        return view('ups.edit', compact('customer', 'ups', 'sites'));
    }

    public function update(Customer $customer, Ups $ups, UpsRequest $request)
    {
        $this->authorize('update', Ups::class);
        $ups->update($request->validated());

        return redirect(route('ups.index', $customer));
    }

    public function destroy(Customer $customer, Ups $ups)
    {
        $this->authorize('delete', Ups::class);
        $ups->delete();

        return redirect(route('ups.index', $customer));
    }
}
