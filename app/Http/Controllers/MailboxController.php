<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Mailbox;

class MailboxController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Mailbox::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('mailbox.index', compact('customer'));
    }
}
