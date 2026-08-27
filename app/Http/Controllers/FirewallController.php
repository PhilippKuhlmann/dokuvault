<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Firewall;

class FirewallController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Firewall::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('firewall.index', compact('customer'));
    }
}
