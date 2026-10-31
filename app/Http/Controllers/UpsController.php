<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ups;

class UpsController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Ups::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('ups.index', compact('customer'));
    }
}
