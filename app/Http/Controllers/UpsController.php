<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ups;

class UpsController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Ups::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('ups.index', compact('customer'));
    }
}
