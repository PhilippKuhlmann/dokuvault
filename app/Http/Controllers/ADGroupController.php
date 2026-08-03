<?php

namespace App\Http\Controllers;

use App\Http\Requests\ADGroupRequest;
use App\Models\ADGroup;
use App\Models\Customer;

class ADGroupController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADGroup::class);

        return view('adgroup.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', ADGroup::class);

        return view('adgroup.create', compact('customer'));
    }

    public function store(Customer $customer, ADGroupRequest $request)
    {
        $this->authorize('create', ADGroup::class);

        $customer->adgroups()->create($request->validated());

        return redirect(route('adgroup.index', $customer));
    }

    public function edit(Customer $customer, ADGroup $adgroup)
    {
        $this->authorize('update', ADGroup::class);

        return view('adgroup.edit', compact('customer', 'adgroup'));
    }

    public function update(Customer $customer, ADGroup $adgroup, ADGroupRequest $request)
    {
        $this->authorize('update', ADGroup::class);

        $adgroup->update($request->validated());

        return redirect(route('adgroup.index', $customer));
    }

    public function destroy(Customer $customer, ADGroup $adgroup)
    {
        $this->authorize('delete', ADGroup::class);

        $adgroup->delete();

        return redirect(route('adgroup.index', $customer));
    }
}
