<?php

namespace App\Http\Controllers;

use App\Models\ADUser;
use App\Models\Customer;

class ADUserController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADUser::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('aduser.index', compact('customer'));
    }
}
