<?php

namespace App\Http\Controllers;

use App\Models\ADDomain;
use App\Models\Customer;

class ADDomainController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADDomain::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('addomain.index', compact('customer'));
    }
}
