<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {

        $name = $request->query('name');

        $query = Customer::whereEnthaelt('name', $name);

        // Ein auf einen Kunden beschraenkter Token (customer_id gesetzt) darf
        // hierueber nicht alle Kunden auflisten koennen - nur den eigenen.
        if (auth()->user()->hasCustomer()) {
            $query->where('id', auth()->user()->customer_id);
        }

        return response()->json($query->get());
    }

    public function store(CustomerRequest $request)
    {
        // Kunden anzulegen ist Admin-/Techniker-Aufgabe; ein auf einen Kunden
        // beschraenkter Token darf das nicht.
        if (auth()->user()->hasCustomer()) {
            abort(403);
        }

        $customer = Customer::create($request->validated());

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return $customer;
    }
}
