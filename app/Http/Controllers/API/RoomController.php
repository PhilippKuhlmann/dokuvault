<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Site;

class RoomController extends Controller
{
    public function index(Customer $customer, Site $site)
    {
        $rooms = $site->rooms()->get();

        return response()->json($rooms, 200);
    }

    public function show(Customer $customer, Site $site, Room $room)
    {
        return response()->json($room);
    }

    public function store(Customer $customer, Site $site, RoomRequest $request)
    {
        $room = $site->rooms()->create($request->validated());

        return response()->json($room, 201);
    }
}
