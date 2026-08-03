<?php

namespace App\Http\Controllers;

use App\Http\Requests\ADUserRequest;
use App\Models\ADUser;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class ADUserController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADUser::class);

        if (Auth::user()->can('see_hidden')) {
            $adusers = ADUser::where('customer_id', $customer->id)
                ->latest()
                ->paginate(25);
        } else {
            $adusers = ADUser::where('customer_id', $customer->id)
                ->where('hidden', false)
                ->latest()
                ->paginate(25);
        }

        return view('aduser.index', compact('customer', 'adusers'));

    }

    public function create(Customer $customer)
    {
        $this->authorize('create', ADUser::class);

        return view('aduser.create', compact('customer'));
    }

    public function store(Customer $customer, ADUserRequest $request)
    {
        $this->authorize('create', ADUser::class);

        $customer->adusers()->create($request->validated());

        return redirect(route('aduser.index', $customer));
    }

    public function edit(Customer $customer, ADUser $aduser)
    {
        $this->authorize('update', ADUser::class);

        return view('aduser.edit', compact('customer', 'aduser'));
    }

    public function update(Customer $customer, ADUser $aduser, ADUserRequest $request)
    {
        $this->authorize('update', ADUser::class);

        $aduser->update($request->validated());

        return redirect(route('aduser.index', $customer));
    }

    public function destroy(Customer $customer, ADUser $aduser)
    {
        $this->authorize('delete', ADUser::class);

        $aduser->delete();

        return redirect(route('aduser.index', $customer));
    }
}
