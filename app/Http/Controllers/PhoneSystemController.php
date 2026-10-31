<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PhoneSystem;

class PhoneSystemController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', PhoneSystem::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('phonesystem.index', compact('customer'));
    }
}
