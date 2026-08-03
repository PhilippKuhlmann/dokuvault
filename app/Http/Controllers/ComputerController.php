<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComputerRequest;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Site;

class ComputerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Computer::class);

        $computers = $this->getFilteredQuery(Computer::class, $customer)
            ->with('operatingSystem')
            ->latest()
            ->paginate(25);

        return view('computer.index', compact('customer', 'computers'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Computer::class);

        $operatingSystems = OperatingSystem::all();
        $sites = Site::where('customer_id', $customer->id)->get();

        return view('computer.create', compact('customer', 'sites', 'operatingSystems'));
    }

    public function store(Customer $customer, ComputerRequest $request)
    {
        $this->authorize('create', Computer::class);

        $customer->computers()->create($request->validated());

        return redirect(route('computer.index', $customer));
    }

    public function edit(Customer $customer, Computer $computer)
    {
        $this->authorize('update', Computer::class);

        $operatingSystems = OperatingSystem::all();
        $sites = Site::where('customer_id', $customer->id)->get();

        return view('computer.edit', compact('customer', 'sites', 'computer', 'operatingSystems'));
    }

    public function update(Customer $customer, Computer $computer, ComputerRequest $request)
    {
        $this->authorize('update', Computer::class);

        $computer->update($request->validated());

        return redirect(route('computer.index', $customer));
    }

    public function destroy(Customer $customer, Computer $computer)
    {
        $this->authorize('delete', Computer::class);

        $computer->delete();

        return redirect(route('computer.index', $customer));
    }
}
