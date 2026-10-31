<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Machine;

class MachineController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Machine::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('machine.index', compact('customer'));
    }
}
