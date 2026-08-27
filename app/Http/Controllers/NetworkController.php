<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Network;

class NetworkController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Network::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('network.index', compact('customer'));
    }
}
