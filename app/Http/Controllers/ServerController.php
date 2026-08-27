<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Server;

class ServerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Server::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('server.index', compact('customer'));
    }
}
