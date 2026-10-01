<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SipAccount;

class SipAccountController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', SipAccount::class);

        // List and form are Livewire (see config/forms.php).
        return view('sipaccount.index', compact('customer'));
    }
}
