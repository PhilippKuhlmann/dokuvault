<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NetworkShare;

class NetworkShareController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', NetworkShare::class);

        // List and form are Livewire (see config/forms.php).
        return view('networkshare.index', compact('customer'));
    }
}
