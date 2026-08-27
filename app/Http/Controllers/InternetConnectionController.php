<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetConnection;

class InternetConnectionController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', InternetConnection::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('internetconnection.index', compact('customer'));
    }
}
