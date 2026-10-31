<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\VM;

class VMController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', VM::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('vm.index', compact('customer'));
    }
}
