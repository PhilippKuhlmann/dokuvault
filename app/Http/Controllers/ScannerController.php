<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Scanner;

class ScannerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Scanner::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('scanner.index', compact('customer'));
    }
}
