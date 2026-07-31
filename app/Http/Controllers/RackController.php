<?php

namespace App\Http\Controllers;

use App\Http\Requests\RackRequest;
use App\Models\Customer;
use App\Models\Rack;

class RackController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Rack::class);

        $racks = $this->getFilteredQuery(Rack::class, $customer)
            ->with('items.device')
            ->latest()->paginate(25);

        return view('rack.index', compact('customer', 'racks'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Rack::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('rack.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, RackRequest $request)
    {
        $this->authorize('create', Rack::class);

        $rack = $customer->racks()->create($request->validated());

        // Direkt in den Editor: ein frisches Rack will bestueckt werden.
        return redirect(route('rack.edit', [$customer, $rack]));
    }

    public function edit(Customer $customer, Rack $rack)
    {
        $this->authorize('update', Rack::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('rack.edit', compact('customer', 'rack', 'sites'));
    }

    public function update(Customer $customer, Rack $rack, RackRequest $request)
    {
        $this->authorize('update', Rack::class);

        $data = $request->validated();

        // Verkleinern nur, solange oben kein Einbau herausragen wuerde.
        $highest = $rack->items()->get()->max(fn ($item) => $item->topUnit()) ?? 0;
        if ($data['height_units'] < $highest) {
            return back()->withErrors([
                'height_units' => "Das Rack kann nicht auf {$data['height_units']} HE verkleinert werden – der oberste Einbau reicht bis HE {$highest}.",
            ])->withInput();
        }

        $rack->update($data);

        return redirect(route('rack.index', $customer));
    }

    public function destroy(Customer $customer, Rack $rack)
    {
        $this->authorize('delete', Rack::class);

        $rack->delete();

        return redirect(route('rack.index', $customer));
    }
}
