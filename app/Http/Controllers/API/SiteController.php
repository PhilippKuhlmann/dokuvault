<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteRequest;
use App\Models\Customer;
use App\Models\Site;

class SiteController extends Controller
{
    public function index(Customer $customer)
    {
        $sites = $customer->sites()->get();

        return response()->json($sites, 200);
    }

    public function show(Customer $customer, Site $site)
    {
        // Besides scopeBindings on the route: never a site of another customer.
        abort_unless($site->customer_id === $customer->id, 404);

        return $site;
    }

    public function store(Customer $customer, SiteRequest $request)
    {
        $site = $customer->sites()->create($request->validated());

        return response()->json($site, 201);
    }
}
