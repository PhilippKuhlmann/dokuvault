<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServerRequest;
use App\Models\Server;
use App\Rules\BelongsToCustomer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /**
     * The customer comes from the token, otherwise from customer_id in the
     * body. Without {customer} in the URL, ServerRequest alone cannot work:
     * BelongsToCustomer reads the customer from the route and failed on
     * every site_id - POST and PUT answered 422 whatever was sent.
     */
    public function store(Request $request)
    {
        $customerId = auth()->user()->customer_id
            ?? $request->validate(['customer_id' => ['required', 'integer', Rule::exists('customers', 'id')]])['customer_id'];

        $server = Server::create(['customer_id' => (int) $customerId] + $this->validated($request, (int) $customerId));

        return response()->json($server, 201);
    }

    public function update(Request $request, Server $server)
    {
        $this->denyIfForeignCustomer($server);

        // The customer of a server does not change here, even if the body says so.
        $server->update($this->validated($request, $server->customer_id));

        return response()->json($server, 200);
    }

    /** The rules of the browser form, with the customer checks bound to $customerId. */
    private function validated(Request $request, int $customerId): array
    {
        $form = new ServerRequest;

        if ($request->input('form_factor') !== 'rack') {
            $request->merge(['full_depth' => true, 'height_units' => 1]);
        }

        $rules = collect($form->rules())->map(fn ($regeln) => is_array($regeln)
            ? array_map(fn ($regel) => $regel instanceof BelongsToCustomer
                ? new BelongsToCustomer($regel->tabelle(), $customerId)
                : $regel, $regeln)
            : $regeln)->all();

        // Only an existing operating system; the form takes it from a list.
        $rules['operating_system_id'] = ['required', Rule::exists('operating_systems', 'id')];

        return $request->validate($rules, [], $form->attributes());
    }

    public function delete(Server $server)
    {
        $this->denyIfForeignCustomer($server);

        $server->delete();

        return response()->json(null, 204);
    }
}
