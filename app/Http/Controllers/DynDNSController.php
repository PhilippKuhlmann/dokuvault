<?php

namespace App\Http\Controllers;

use App\Http\Requests\DynDNSRequest;
use App\Models\Customer;
use App\Models\DynDNS;

class DynDNSController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', DynDNS::class);

        return view('dyndns.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', DynDNS::class);

        return view('dyndns.create', compact('customer'));
    }

    public function store(Customer $customer, DynDNSRequest $request)
    {
        $this->authorize('create', DynDNS::class);

        $customer->dyndns()->create($request->validated());

        return redirect(route('dyndns.index', $customer));
    }

    public function edit(Customer $customer, DynDNS $dyndns)
    {
        $this->authorize('update', DynDNS::class);

        return view('dyndns.edit', compact('customer', 'dyndns'));
    }

    public function update(Customer $customer, DynDNS $dyndns, DynDNSRequest $request)
    {
        $this->authorize('update', DynDNS::class);

        $dyndns->update($request->validated());

        return redirect(route('dyndns.index', $customer));
    }

    public function destroy(Customer $customer, DynDNS $dyndns)
    {
        $this->authorize('delete', DynDNS::class);

        $dyndns->delete();

        return redirect(route('dyndns.index', $customer));
    }
}
