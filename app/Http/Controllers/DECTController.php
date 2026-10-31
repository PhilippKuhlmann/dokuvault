<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DECT;

class DECTController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', DECT::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('dect.index', compact('customer'));
    }
}
