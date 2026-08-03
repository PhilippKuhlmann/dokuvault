<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecurepointUTMRequest;
use App\Models\Customer;
use App\Models\SecurepointUTM;

class SecurepointUTMController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', SecurepointUTM::class);

        $securepointutms = $this->getFilteredQuery(SecurepointUTM::class, $customer)
            ->latest()->paginate(25);

        return view('securepointutm.index', compact('customer', 'securepointutms'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', SecurepointUTM::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('securepointutm.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, SecurepointUTMRequest $request)
    {
        $this->authorize('create', SecurepointUTM::class);

        $customer->securepointutms()->create($request->validated());

        return redirect(route('securepointutm.index', $customer));
    }

    public function edit(Customer $customer, SecurepointUTM $securepointutm)
    {
        $this->authorize('update', SecurepointUTM::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('securepointutm.edit', compact('customer', 'securepointutm', 'sites'));
    }

    public function update(Customer $customer, SecurepointUTM $securepointutm, SecurepointUTMRequest $request)
    {
        $this->authorize('update', SecurepointUTM::class);

        $securepointutm->update($request->validated());

        return redirect(route('securepointutm.index', $customer));
    }

    public function destroy(Customer $customer, SecurepointUTM $securepointutm)
    {
        $this->authorize('delete', SecurepointUTM::class);

        $securepointutm->delete();

        return redirect(route('securepointutm.index', $customer));
    }
}
