<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Wifi;

class WifiController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Wifi::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('wifi.index', compact('customer'));
    }
}
