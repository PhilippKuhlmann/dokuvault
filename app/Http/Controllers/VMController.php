<?php

namespace App\Http\Controllers;

use App\Http\Requests\VMRequest;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\VM;

class VMController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', VM::class);

        $vms = $this->getFilteredQuery(VM::class, $customer)
            ->with('operatingSystem', 'host')
            ->latest()->paginate(25);

        return view('vm.index', compact('customer', 'vms'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', VM::class);

        $sites = $this->getSitesForCustomer($customer);
        $operatingSystems = OperatingSystem::all();
        $servers = Server::where('customer_id', $customer->id)->get();

        return view('vm.create', compact('customer', 'sites', 'operatingSystems', 'servers'));
    }

    public function store(Customer $customer, VMRequest $request)
    {
        $this->authorize('create', VM::class);

        $customer->vms()->create($request->validated());

        return redirect(route('vm.index', $customer));
    }

    public function edit(Customer $customer, VM $vm)
    {
        $this->authorize('update', VM::class);

        $sites = $this->getSitesForCustomer($customer);
        $operatingSystems = OperatingSystem::all();
        $servers = Server::where('customer_id', $customer->id)->get();

        return view('vm.edit', compact('customer', 'sites', 'operatingSystems', 'servers', 'vm'));
    }

    public function update(Customer $customer, VM $vm, VMRequest $request)
    {
        $this->authorize('update', VM::class);

        $vm->update($request->validated());

        return redirect(route('vm.index', $customer));
    }

    public function destroy(Customer $customer, VM $vm)
    {
        $this->authorize('delete', VM::class);

        $vm->delete();

        return redirect(route('vm.index', $customer));
    }
}
