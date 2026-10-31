<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FTPServer;

class FTPServerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', FTPServer::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('ftpserver.index', compact('customer'));
    }
}
