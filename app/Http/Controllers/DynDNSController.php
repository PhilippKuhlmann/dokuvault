<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DynDNS;

class DynDNSController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', DynDNS::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('dyndns.index', compact('customer'));
    }
}
