<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ScanTarget;

class ScanTargetController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ScanTarget::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('scantarget.index', compact('customer'));
    }
}
