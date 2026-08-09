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
        return $site;
    }

    public function store(Customer $customer, SiteRequest $request)
    {
        $site = $customer->sites()->create($request->validated());

        return response()->json($site, 201);
    }
}
