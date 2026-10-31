<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Printer;

class PrinterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Printer::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('printer.index', compact('customer'));
    }
}
