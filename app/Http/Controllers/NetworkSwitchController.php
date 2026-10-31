<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NetworkSwitch;

class NetworkSwitchController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', NetworkSwitch::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('networkswitch.index', compact('customer'));
    }
}
