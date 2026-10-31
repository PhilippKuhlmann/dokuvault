<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Mailbox;

class MailboxController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Mailbox::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('mailbox.index', compact('customer'));
    }
}
