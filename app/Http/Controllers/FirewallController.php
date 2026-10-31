<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Firewall;

class FirewallController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Firewall::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('firewall.index', compact('customer'));
    }
}
