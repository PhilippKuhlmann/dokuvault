<?php

namespace App\Http\Controllers;

use App\Http\Requests\MachineRequest;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\Site;

class MachineController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Machine::class);

        $machines = $this->getFilteredQuery(Machine::class, $customer)->latest()->paginate(25);

        return view('machine.index', compact('customer', 'machines'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Machine::class);

        $sites = Site::where('customer_id', $customer->id)->get();

        return view('machine.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, MachineRequest $request)
    {
        $this->authorize('create', Machine::class);

        $customer->machines()->create($request->validated());

        return redirect(route('machine.index', $customer));
    }

    public function edit(Customer $customer, Machine $machine)
    {
        $this->authorize('update', Machine::class);

        $sites = Site::where('customer_id', $customer->id)->get();

        return view('machine.edit', compact('customer', 'sites', 'machine'));
    }

    public function update(Customer $customer, Machine $machine, MachineRequest $request)
    {
        $this->authorize('update', Machine::class);

        $machine->update($request->validated());

        return redirect(route('machine.index', $customer));
    }

    public function destroy(Customer $customer, Machine $machine)
    {
        $this->authorize('delete', Machine::class);

        $machine->delete();

        return redirect(route('machine.index', $customer));
    }
}
