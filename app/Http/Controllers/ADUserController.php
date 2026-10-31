<?php

namespace App\Http\Controllers;

use App\Models\ADUser;
use App\Models\Customer;

class ADUserController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ADUser::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('aduser.index', compact('customer'));
    }
}
