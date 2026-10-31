<?php

namespace App\Http\Controllers;

use App\Models\ContactPerson;
use App\Models\Customer;

class ContactPersonController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', ContactPerson::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('contactperson.index', compact('customer'));
    }
}
