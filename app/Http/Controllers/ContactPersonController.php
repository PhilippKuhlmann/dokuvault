<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactPersonRequest;
use App\Models\ContactPerson;
use App\Models\Customer;

class ContactPersonController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ContactPerson::class);

        $contactpersons = ContactPerson::where('customer_id', $customer->id)->latest()->paginate(25);

        return view('contactperson.index', compact('customer', 'contactpersons'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', ContactPerson::class);

        return view('contactperson.create', compact('customer'));
    }

    public function store(Customer $customer, ContactPersonRequest $request)
    {
        $this->authorize('create', ContactPerson::class);

        $customer->contactpersons()->create($request->validated());

        return redirect(route('contactperson.index', $customer));
    }

    public function edit(Customer $customer, ContactPerson $contactperson)
    {
        $this->authorize('update', ContactPerson::class);

        return view('contactperson.edit', compact('customer', 'contactperson'));
    }

    public function update(Customer $customer, ContactPerson $contactperson, ContactPersonRequest $request)
    {
        $this->authorize('update', ContactPerson::class);

        $contactperson->update($request->validated());

        return redirect(route('contactperson.index', $customer));
    }

    public function destroy(Customer $customer, ContactPerson $contactperson)
    {
        $this->authorize('delete', ContactPerson::class);

        $contactperson->delete();

        return redirect(route('contactperson.index', $customer));
    }
}
