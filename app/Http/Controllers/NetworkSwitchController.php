<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NetworkSwitch;

class NetworkSwitchController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', NetworkSwitch::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('networkswitch.index', compact('customer'));
    }
}
