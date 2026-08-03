<?php

namespace App\Http\Controllers;

use App\Http\Requests\DECTRequest;
use App\Models\Customer;
use App\Models\DECT;

class DECTController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', DECT::class);

        $dectList = $this->getFilteredQuery(DECT::class, $customer)
            ->latest()->paginate(25);

        return view('dect.index', compact('customer', 'dectList'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', DECT::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('dect.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, DECTRequest $request)
    {
        $this->authorize('create', DECT::class);

        $customer->dects()->create($request->validated());

        return redirect(route('dect.index', $customer));
    }

    public function edit(Customer $customer, DECT $dect)
    {
        $this->authorize('update', DECT::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('dect.edit', compact('customer', 'sites', 'dect'));
    }

    public function update(Customer $customer, DECT $dect, DECTRequest $request)
    {
        $this->authorize('update', DECT::class);

        $dect->update($request->validated());

        return redirect(route('dect.index', $customer));
    }

    public function destroy(Customer $customer, DECT $dect)
    {
        $this->authorize('delete', DECT::class);

        $dect->delete();

        return redirect(route('dect.index', $customer));
    }
}
