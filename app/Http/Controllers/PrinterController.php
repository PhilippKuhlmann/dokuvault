<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Printer;

class PrinterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Printer::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('printer.index', compact('customer'));
    }
}
