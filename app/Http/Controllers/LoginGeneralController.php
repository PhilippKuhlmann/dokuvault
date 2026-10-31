<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoginGeneral;

class LoginGeneralController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginGeneral::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('logingeneral.index', compact('customer'));
    }
}
