<?php

namespace App\Http\Controllers;

use App\Models\ContactPerson;
use App\Models\Customer;

class ContactPersonController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ContactPerson::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('contactperson.index', compact('customer'));
    }
}
