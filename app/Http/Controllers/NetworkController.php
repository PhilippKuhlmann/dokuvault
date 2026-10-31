<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Network;

class NetworkController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Network::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('network.index', compact('customer'));
    }
}
