<?php

namespace App\Http\Controllers;

use App\Http\Requests\OtherClientRequest;
use App\Models\Customer;
use App\Models\OtherClient;
use App\Models\Site;

class OtherClientController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', OtherClient::class);

        $otherclients = $this->getFilteredQuery(OtherClient::class, $customer)->latest()->paginate(25);

        return view('otherclient.index', compact('customer', 'otherclients'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', OtherClient::class);

        $sites = Site::where('customer_id', $customer->id)->get();

        return view('otherclient.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, OtherClientRequest $request)
    {
        $this->authorize('create', OtherClient::class);

        $customer->otherclients()->create($request->validated());

        return redirect(route('otherclient.index', $customer));
    }

    public function edit(Customer $customer, OtherClient $otherclient)
    {
        $this->authorize('update', OtherClient::class);

        $sites = Site::where('customer_id', $customer->id)->get();

        return view('otherclient.edit', compact('customer', 'sites', 'otherclient'));
    }

    public function update(Customer $customer, OtherClient $otherclient, OtherClientRequest $request)
    {
        $this->authorize('update', OtherClient::class);

        $otherclient->update($request->validated());

        return redirect(route('otherclient.index', $customer));
    }

    public function destroy(Customer $customer, OtherClient $otherclient)
    {
        $this->authorize('delete', OtherClient::class);

        $otherclient->delete();

        return redirect(route('otherclient.index', $customer));
    }
}
