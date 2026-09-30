<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Scanner;

class ScannerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Scanner::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('scanner.index', compact('customer'));
    }
}
