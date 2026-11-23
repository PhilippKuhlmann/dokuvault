<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Support\CustomerTeams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {

        // Without a search term the whole list: whereEnthaelt() expects a
        // string, and a call without ?name= ended in a 500.
        $name = (string) $request->query('name');

        $query = Customer::query()
            ->when($name !== '', fn ($abfrage) => $abfrage->whereEnthaelt('name', $name));

        // Ein auf einen Kunden beschraenkter Token (customer_id gesetzt) darf
        // cannot list every customer through this - only their own.
        if (auth()->user()->hasCustomer()) {
            $query->where('id', auth()->user()->customer_id);
        }

        return response()->json($query->get());
    }

    public function store(CustomerRequest $request)
    {
        // Creating customers is an admin or technician task; a token limited
        // to one customer must not do it.
        if (auth()->user()->hasCustomer()) {
            abort(403);
        }

        // Teams and levels as in the browser (CustomerTeams): with the same
        // lock-out rule - a plain create() dropped them silently, and a
        // customer meant for one team was open to everyone.
        $customer = DB::transaction(function () use ($request) {
            $customer = Customer::create($request->safe()->except(['teams', 'levels']));
            CustomerTeams::fromRequest($customer, $request);

            return $customer;
        });

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return $customer;
    }
}
