<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FTPServer;

class FTPServerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', FTPServer::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('ftpserver.index', compact('customer'));
    }
}
