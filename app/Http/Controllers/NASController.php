<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NAS;

class NASController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', NAS::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('nas.index', compact('customer'));
    }
}
