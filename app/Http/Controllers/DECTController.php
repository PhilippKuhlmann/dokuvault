<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DECT;

class DECTController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', DECT::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('dect.index', compact('customer'));
    }
}
