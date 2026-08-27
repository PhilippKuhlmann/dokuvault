<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\Customer;

class ComputerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Computer::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('computer.index', compact('customer'));
    }
}
