<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\VM;

class VMController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', VM::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('vm.index', compact('customer'));
    }
}
