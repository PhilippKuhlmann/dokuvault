<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecurepointUMARequest;
use App\Models\Customer;
use App\Models\SecurepointUMA;

class SecurepointUMAController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', SecurepointUMA::class);

        return view('securepointuma.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', SecurepointUMA::class);

        return view('securepointuma.create', [
            'customer' => $customer,
        ]);
    }

    public function store(Customer $customer, SecurepointUMARequest $request)
    {
        $this->authorize('create', SecurepointUMA::class);

        $customer->securepointumas()->create($request->validated());

        return redirect(route('securepointuma.index', $customer));
    }

    public function edit(Customer $customer, SecurepointUMA $securepointuma)
    {
        $this->authorize('update', SecurepointUMA::class);

        return view('securepointuma.edit', [
            'customer' => $customer,
            'securepointuma' => $securepointuma,
        ]);
    }

    public function update(Customer $customer, SecurepointUMA $securepointuma, SecurepointUMARequest $request)
    {
        $this->authorize('update', SecurepointUMA::class);

        $securepointuma->update($request->validated());

        return redirect(route('securepointuma.index', $customer));
    }

    public function destroy(Customer $customer, SecurepointUMA $securepointuma)
    {
        $this->authorize('delete', SecurepointUMA::class);

        $securepointuma->delete();

        return redirect(route('securepointuma.index', $customer));
    }
}
