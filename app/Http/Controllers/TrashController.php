<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Trash;
use Illuminate\Support\Facades\Gate;

class TrashController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('see_hidden');

        // Worked out in Trash - the same class serves the iOS app, so both
        // get the same entries and the same cap
        // kennen.
        $papierkorb = new Trash($customer, auth()->user());

        return view('trash.index', [
            'customer' => $customer,
            'items' => $papierkorb->eintraege(),
            'gekuerzt' => $papierkorb->gekuerzt(),
        ]);
    }

    public function restore(Customer $customer, string $type, int $id)
    {
        Gate::authorize('see_hidden');

        (new Trash($customer, auth()->user()))->restore($type, $id);

        return redirect(route('trash.index', $customer))
            ->with('success', 'Objekt wiederhergestellt.');
    }
}
