<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Domain;

class DomainController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Domain::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('domain.index', compact('customer'));
    }
}
