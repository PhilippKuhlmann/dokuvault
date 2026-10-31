<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Papierkorb;
use Illuminate\Support\Facades\Gate;

class TrashController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('see_hidden');

        // Gerechnet wird in Papierkorb - dieselbe Klasse bedient die
        // iOS-App, damit beide dieselben Eintraege und dieselbe Grenze
        // kennen.
        $papierkorb = new Papierkorb($customer, auth()->user());

        return view('trash.index', [
            'customer' => $customer,
            'items' => $papierkorb->eintraege(),
            'gekuerzt' => $papierkorb->gekuerzt(),
        ]);
    }

    public function restore(Customer $customer, string $type, int $id)
    {
        Gate::authorize('see_hidden');

        (new Papierkorb($customer, auth()->user()))->wiederherstellen($type, $id);

        return redirect(route('trash.index', $customer))
            ->with('success', 'Objekt wiederhergestellt.');
    }
}
