<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServerRequest;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;

class ServerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Server::class);

        $servers = $this->getFilteredQuery(Server::class, $customer)
            ->with('operatingSystem')
            ->latest()->paginate(25);

        return view('server.index', compact('customer', 'servers'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Server::class);

        $sites = $this->getSitesForCustomer($customer);
        $operatingSystems = OperatingSystem::all();

        return view('server.create', compact('customer', 'sites', 'operatingSystems'));
    }

    public function store(Customer $customer, ServerRequest $request)
    {
        $this->authorize('create', Server::class);

        $customer->servers()->create($request->validated());

        return redirect(route('server.index', $customer));
    }

    public function edit(Customer $customer, Server $server)
    {
        $this->authorize('update', Server::class);

        $sites = $this->getSitesForCustomer($customer);
        $operatingSystems = OperatingSystem::all();

        return view('server.edit', compact('customer', 'sites', 'operatingSystems', 'server'));
    }

    public function update(Customer $customer, Server $server, ServerRequest $request)
    {
        $this->authorize('update', Server::class);

        $server->update($request->validated());

        return redirect(route('server.index', $customer));
    }

    public function destroy(Customer $customer, Server $server)
    {
        $this->authorize('delete', Server::class);

        $server->delete();

        return redirect(route('server.index', $customer));
    }
}
