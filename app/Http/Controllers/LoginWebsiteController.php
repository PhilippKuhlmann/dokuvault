<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoginWebsite;

class LoginWebsiteController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginWebsite::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('loginwebsite.index', compact('customer'));
    }
}
