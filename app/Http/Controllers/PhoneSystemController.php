<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PhoneSystem;

class PhoneSystemController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', PhoneSystem::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('phonesystem.index', compact('customer'));
    }
}
