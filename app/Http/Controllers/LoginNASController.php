<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginNASRequest;
use App\Models\Customer;
use App\Models\LoginNAS;
use App\Models\NAS;

class LoginNASController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginNAS::class);

        $customer->load('loginnas.nas');

        return view('loginnas.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LoginNAS::class);

        $nas = NAS::where('customer_id', $customer->id)->get();

        return view('loginnas.create', compact('customer', 'nas'));
    }

    public function store(Customer $customer, LoginNASRequest $request)
    {
        $this->authorize('create', LoginNAS::class);

        $customer->loginnas()->create($request->validated());

        return redirect(route('loginnas.index', $customer));
    }

    public function edit(Customer $customer, LoginNAS $loginnas)
    {
        $this->authorize('update', LoginNAS::class);

        $nas = NAS::where('customer_id', $customer->id)->get();

        return view('loginnas.edit', compact('customer', 'loginnas', 'nas'));
    }

    public function update(Customer $customer, LoginNAS $loginnas, LoginNASRequest $request)
    {
        $this->authorize('update', LoginNAS::class);

        $loginnas->update($request->validated());

        return redirect(route('loginnas.index', $customer));
    }

    public function destroy(Customer $customer, LoginNAS $loginnas)
    {
        $this->authorize('delete', LoginNAS::class);

        $loginnas->delete();

        return redirect(route('loginnas.index', $customer));
    }
}
