<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Machine;

class MachineController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Machine::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('machine.index', compact('customer'));
    }
}
