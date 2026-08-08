<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecorderRequest;
use App\Models\Customer;
use App\Models\Recorder;

class RecorderController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Recorder::class);

        $recorders = $this->getFilteredQuery(Recorder::class, $customer)
            ->latest()->paginate(25);

        return view('recorder.index', compact('customer', 'recorders'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Recorder::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('recorder.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, RecorderRequest $request)
    {
        $this->authorize('create', Recorder::class);

        $customer->recorders()->create($request->validated());

        return redirect(route('recorder.index', $customer))->withSuccess('Gespeichert!');
    }

    public function edit(Customer $customer, Recorder $recorder)
    {
        $this->authorize('update', Recorder::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('recorder.edit', compact('customer', 'recorder', 'sites'));
    }

    public function update(Customer $customer, Recorder $recorder, RecorderRequest $request)
    {
        $this->authorize('update', Recorder::class);

        $recorder->update($request->validated());

        return redirect(route('recorder.index', $customer))->withSuccess('Gespeichert!');
    }

    public function destroy(Customer $customer, Recorder $recorder)
    {
        $this->authorize('delete', Recorder::class);

        // Verknüpfte Zugangsdaten bleiben stehen: Das Login kann an weiteren
        // Systemen hängen, und beim Wiederherstellen ist die Verknüpfung zurück.
        $recorder->delete();

        return redirect(route('recorder.index', $customer))->withSuccess('Gelöscht!');
    }
}
