<?php

namespace App\Http\Controllers;

use App\Http\Requests\DomainRequest;
use App\Models\Customer;
use App\Models\Domain;

class DomainController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Domain::class);
        $domains = Domain::where('customer_id', $customer->id)->orderBy('expiry_date')->paginate(25);

        return view('domain.index', compact('customer', 'domains'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Domain::class);

        return view('domain.create', compact('customer'));
    }

    public function store(Customer $customer, DomainRequest $request)
    {
        $this->authorize('create', Domain::class);
        $customer->domains()->create($request->validated());

        return redirect(route('domain.index', $customer));
    }

    public function edit(Customer $customer, Domain $domain)
    {
        $this->authorize('update', Domain::class);

        return view('domain.edit', compact('customer', 'domain'));
    }

    public function update(Customer $customer, Domain $domain, DomainRequest $request)
    {
        $this->authorize('update', Domain::class);
        $domain->update($request->validated());

        return redirect(route('domain.index', $customer));
    }

    public function destroy(Customer $customer, Domain $domain)
    {
        $this->authorize('delete', Domain::class);
        $domain->delete();

        return redirect(route('domain.index', $customer));
    }
}
