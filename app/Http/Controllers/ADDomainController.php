<?php

namespace App\Http\Controllers;

use App\Models\ADDomain;
use App\Models\Customer;

class ADDomainController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADDomain::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('addomain.index', compact('customer'));
    }
}
