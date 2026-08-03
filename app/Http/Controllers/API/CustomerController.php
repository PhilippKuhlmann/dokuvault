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

        $data = Customer::where('name', 'like', '%'.$name.'%')->get();

        return response()->json($data);
    }

    public function store(CustomerRequest $request)
    {
        $customer = Customer::create($request->all());

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return $customer;
    }
}
