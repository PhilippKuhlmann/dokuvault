<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginWebsiteRequest;
use App\Models\Customer;
use App\Models\LoginWebsite;

class LoginWebsiteController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginWebsite::class);

        return view('loginwebsite.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LoginWebsite::class);

        return view('loginwebsite.create', compact('customer'));
    }

    public function store(Customer $customer, LoginWebsiteRequest $request)
    {
        $this->authorize('create', LoginWebsite::class);

        $customer->loginwebsites()->create($request->validated());

        return redirect(route('loginwebsite.index', $customer));
    }

    public function edit(Customer $customer, LoginWebsite $loginwebsite)
    {
        $this->authorize('update', LoginWebsite::class);

        return view('loginwebsite.edit', compact('customer', 'loginwebsite'));
    }

    public function update(Customer $customer, LoginWebsite $loginwebsite, LoginWebsiteRequest $request)
    {
        $this->authorize('update', LoginWebsite::class);

        $loginwebsite->update($request->validated());

        return redirect(route('loginwebsite.index', $customer));
    }

    public function destroy(Customer $customer, LoginWebsite $loginwebsite)
    {
        $this->authorize('delete', LoginWebsite::class);

        $loginwebsite->delete();

        return redirect(route('loginwebsite.index', $customer));
    }
}
