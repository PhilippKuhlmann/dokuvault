<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OtherClient;

class OtherClientController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', OtherClient::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('otherclient.index', compact('customer'));
    }
}
