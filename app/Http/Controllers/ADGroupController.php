<?php

namespace App\Http\Controllers;

use App\Models\ADGroup;
use App\Models\Customer;

class ADGroupController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADGroup::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('adgroup.index', compact('customer'));
    }
}
