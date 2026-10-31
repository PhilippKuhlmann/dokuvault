<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\Customer;

class ComputerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Computer::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('computer.index', compact('customer'));
    }
}
