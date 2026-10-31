<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetConnection;

class InternetConnectionController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', InternetConnection::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('internetconnection.index', compact('customer'));
    }
}
