<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccesspointRequest;
use App\Models\Accesspoint;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Site;
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

    public function show(Customer $customer, Site $site, Room $room, Accesspoint $accesspoint)
    {
        if ($accesspoint->customer_id == $customer->id) {
            // Der Accesspoint gehört zum Kunden
            return $accesspoint;
        } else {
            // Der Accesspoint gehört nicht zum Kunden
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store(Customer $customer, Site $site, Room $room, AccesspointRequest $request)
    {
        $accesspoint = $customer->accesspoints()->create($request->validated());

        return response()->json($accesspoint, 201);
    }

    public function update(Customer $customer, Site $site, Room $room, AccesspointRequest $request, Accesspoint $accesspoint)
    {
        $accesspoint->update($request->validated());

        return response()->json($accesspoint, 200);
    }

    public function delete(Customer $customer, Site $site, Room $room, Accesspoint $accesspoint)
    {
        $accesspoint->delete();

        return response()->json(null, 204);
    }
}
