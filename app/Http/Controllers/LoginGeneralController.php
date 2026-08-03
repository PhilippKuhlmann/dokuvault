<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginGeneralRequest;
use App\Models\Customer;
use App\Models\LoginGeneral;

class LoginGeneralController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginGeneral::class);

        return view('logingeneral.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LoginGeneral::class);

        return view('logingeneral.create', compact('customer'));
    }

    public function store(Customer $customer, LoginGeneralRequest $request)
    {
        $this->authorize('create', LoginGeneral::class);

        $customer->logingenerals()->create($request->validated());

        return redirect(route('logingeneral.index', $customer));
    }

    public function edit(Customer $customer, LoginGeneral $logingeneral)
    {
        $this->authorize('update', LoginGeneral::class);

        return view('logingeneral.edit', compact('customer', 'logingeneral'));
    }

    public function update(Customer $customer, LoginGeneral $logingeneral, LoginGeneralRequest $request)
    {
        $this->authorize('update', LoginGeneral::class);

        $logingeneral->update($request->validated());

        return redirect(route('logingeneral.index', $customer));
    }

    public function destroy(Customer $customer, LoginGeneral $logingeneral)
    {
        $this->authorize('delete', LoginGeneral::class);

        $logingeneral->delete();

        return redirect(route('logingeneral.index', $customer));
    }
}
