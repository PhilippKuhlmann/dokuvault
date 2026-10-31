<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Domain;

class DomainController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Domain::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('domain.index', compact('customer'));
    }
}
