<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoginWebsite;

class LoginWebsiteController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LoginWebsite::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('loginwebsite.index', compact('customer'));
    }
}
