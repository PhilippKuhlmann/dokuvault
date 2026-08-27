<?php

namespace App\Http\Controllers;

use App\Models\Accesspoint;
use App\Models\Customer;

class AccesspointController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Accesspoint::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('accesspoint.index', compact('customer'));
    }
}
