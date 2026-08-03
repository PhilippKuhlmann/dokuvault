<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhoneSystemRequest;
use App\Models\Customer;
use App\Models\PhoneSystem;

class PhoneSystemController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', PhoneSystem::class);

        $phoneSystems = $this->getFilteredQuery(PhoneSystem::class, $customer)
            ->latest()->paginate(25);

        return view('phonesystem.index', compact('customer', 'phoneSystems'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', PhoneSystem::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('phonesystem.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, PhoneSystemRequest $request)
    {
        $this->authorize('create', PhoneSystem::class);

        $customer->phonesystems()->create($request->validated());

        return redirect(route('phonesystem.index', $customer));
    }

    public function edit(Customer $customer, PhoneSystem $phonesystem)
    {
        $this->authorize('update', PhoneSystem::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('phonesystem.edit', compact('customer', 'sites', 'phonesystem'));
    }

    public function update(Customer $customer, PhoneSystem $phonesystem, PhoneSystemRequest $request)
    {
        $this->authorize('update', PhoneSystem::class);

        $phonesystem->update($request->validated());

        return redirect(route('phonesystem.index', $customer));
    }

    public function destroy(Customer $customer, PhoneSystem $phonesystem)
    {
        $this->authorize('delete', PhoneSystem::class);

        $phonesystem->delete();

        return redirect(route('phonesystem.index', $customer));
    }
}
