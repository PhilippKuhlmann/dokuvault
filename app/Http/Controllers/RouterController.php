<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Router;

class RouterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Router::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('router.index', compact('customer'));
    }
}
