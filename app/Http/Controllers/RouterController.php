<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Router;

class RouterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Router::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('router.index', compact('customer'));
    }
}
