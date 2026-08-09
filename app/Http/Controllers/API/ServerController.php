<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServerRequest;
use App\Models\Server;

class ServerController extends Controller
{
    // Diese Routen haben - anders als die uebrigen API-Ressourcen - keinen
    // {customer}-Praefix in der URL. Ein auf einen Kunden beschraenkter
    // Token (customer_id gesetzt) darf daher nur auf Server des eigenen
    // Kunden zugreifen; Admin-/Techniker-Token bleiben unbeschraenkt.
    private function denyIfForeignCustomer(Server $server): void
    {
        $user = auth()->user();

        if ($user->hasCustomer() && $server->customer_id != $user->customer_id) {
            abort(403);
        }
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->hasCustomer()) {
            return Server::where('customer_id', $user->customer_id)->get();
        }

        return Server::all();
    }

    public function show(Server $server)
    {
        $this->denyIfForeignCustomer($server);

        return $server;
    }

    public function store(ServerRequest $request)
    {
        $server = Server::create($request->validated());

        return response()->json($server, 201);
    }

    public function update(ServerRequest $request, Server $server)
    {
        $this->denyIfForeignCustomer($server);

        $server->update($request->validated());

        return response()->json($server, 200);
    }

    public function delete(Server $server)
    {
        $this->denyIfForeignCustomer($server);

        $server->delete();

        return response()->json(null, 204);
    }
}
