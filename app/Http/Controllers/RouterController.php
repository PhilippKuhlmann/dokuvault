<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouterRequest;
use App\Models\Customer;
use App\Models\Router;

class RouterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Router::class);

        $routers = $this->getFilteredQuery(Router::class, $customer)
            ->latest()->paginate(25);

        return view('router.index', compact('customer', 'routers'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Router::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('router.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, RouterRequest $request)
    {
        $this->authorize('create', Router::class);

        $customer->routers()->create($request->validated());

        return redirect(route('router.index', $customer));
    }

    public function edit(Customer $customer, Router $router)
    {
        $this->authorize('update', Router::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('router.edit', compact('customer', 'router', 'sites'));
    }

    public function update(Customer $customer, Router $router, RouterRequest $request)
    {
        $this->authorize('update', Router::class);

        $router->update($request->validated());

        return redirect(route('router.index', $customer));
    }

    public function destroy(Customer $customer, Router $router)
    {
        $this->authorize('delete', Router::class);

        $router->delete();

        return redirect(route('router.index', $customer));
    }
}
