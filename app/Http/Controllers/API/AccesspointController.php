<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccesspointRequest;
use App\Models\Accesspoint;
use App\Models\Customer;
use Illuminate\Http\Request;

class AccesspointController extends Controller
{
    public function index(Customer $customer, Request $request)
    {
        $accesspoints = $customer->accesspoints();

        $filters = ['site_id'];

        foreach ($filters as $filter) {
            if ($request->has($filter)) {
                $value = $request->input($filter);
                $accesspoints->where($filter, $value);
            }
        }

        $accesspoints = $accesspoints->get();

        return response()->json($accesspoints, 200);
    }

    public function show(Customer $customer, Accesspoint $accesspoint)
    {
        $this->ensureOwnedBy($customer, $accesspoint);

        return $accesspoint;
    }

    public function store(Customer $customer, AccesspointRequest $request)
    {
        $accesspoint = $customer->accesspoints()->create($request->validated());

        return response()->json($accesspoint, 201);
    }

    public function update(Customer $customer, AccesspointRequest $request, Accesspoint $accesspoint)
    {
        $this->ensureOwnedBy($customer, $accesspoint);

        $accesspoint->update($request->validated());

        return response()->json($accesspoint, 200);
    }

    public function delete(Customer $customer, Accesspoint $accesspoint)
    {
        $this->ensureOwnedBy($customer, $accesspoint);

        $accesspoint->delete();

        return response()->json(null, 204);
    }

    /** Besides scopeBindings on the route: never a record of another customer. */
    private function ensureOwnedBy(Customer $customer, Accesspoint $accesspoint): void
    {
        abort_unless($accesspoint->customer_id === $customer->id, 404);
    }
}
