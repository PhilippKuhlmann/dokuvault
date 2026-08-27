<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoginGeneral;

class LoginGeneralController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginGeneral::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('logingeneral.index', compact('customer'));
    }
}
