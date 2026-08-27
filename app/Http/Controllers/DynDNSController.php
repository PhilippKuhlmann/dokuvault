<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DynDNS;

class DynDNSController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', DynDNS::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('dyndns.index', compact('customer'));
    }
}
