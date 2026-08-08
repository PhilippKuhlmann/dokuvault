<?php

namespace App\Http\Controllers;

use App\Http\Requests\NASRequest;
use App\Models\Customer;
use App\Models\NAS;

class NASController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', NAS::class);

        $nasList = $this->getFilteredQuery(NAS::class, $customer)
            ->latest()->paginate(25);

        return view('nas.index', compact('customer', 'nasList'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', NAS::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('nas.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, NASRequest $request)
    {
        $this->authorize('create', NAS::class);

        $customer->nas()->create($request->validated());

        return redirect(route('nas.index', $customer));
    }

    public function edit(Customer $customer, NAS $nas)
    {
        $this->authorize('update', NAS::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('nas.edit', compact('customer', 'sites', 'nas'));
    }

    public function update(Customer $customer, NAS $nas, NASRequest $request)
    {
        $this->authorize('update', NAS::class);

        $nas->update($request->validated());

        return redirect(route('nas.index', $customer));
    }

    public function destroy(Customer $customer, NAS $nas)
    {
        $this->authorize('delete', NAS::class);

        // Verknüpfte Zugangsdaten bleiben stehen: Das Login kann an weiteren
        // Systemen hängen, und beim Wiederherstellen ist die Verknüpfung zurück.
        $nas->delete();

        return redirect(route('nas.index', $customer));
    }
}
