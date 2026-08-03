<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhoneRequest;
use App\Models\Customer;
use App\Models\Phone;

class PhoneController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Phone::class);

        $phones = $this->getFilteredQuery(Phone::class, $customer)
            ->latest()->paginate(25);

        return view('phone.index', compact('customer', 'phones'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Phone::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('phone.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, PhoneRequest $request)
    {
        $this->authorize('create', Phone::class);

        $customer->phones()->create($request->validated());

        return redirect(route('phone.index', $customer));
    }

    public function edit(Customer $customer, Phone $phone)
    {
        $this->authorize('update', Phone::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('phone.edit', compact('customer', 'sites', 'phone'));
    }

    public function update(Customer $customer, Phone $phone, PhoneRequest $request)
    {
        $this->authorize('update', Phone::class);

        $phone->update($request->validated());

        return redirect(route('phone.index', $customer));
    }

    public function destroy(Customer $customer, Phone $phone)
    {
        $this->authorize('delete', Phone::class);

        $phone->delete();

        return redirect(route('phone.index', $customer));
    }
}
