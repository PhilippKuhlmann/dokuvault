<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Phone;

class PhoneController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Phone::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('phone.index', compact('customer'));
    }
}
