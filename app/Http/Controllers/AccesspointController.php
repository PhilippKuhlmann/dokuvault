<?php

namespace App\Http\Controllers;

use App\Models\Accesspoint;
use App\Models\Customer;

class AccesspointController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Accesspoint::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('accesspoint.index', compact('customer'));
    }
}
