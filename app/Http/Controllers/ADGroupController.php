<?php

namespace App\Http\Controllers;

use App\Models\ADGroup;
use App\Models\Customer;

class ADGroupController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADGroup::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('adgroup.index', compact('customer'));
    }
}
