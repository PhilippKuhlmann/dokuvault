<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Server;

class ServerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Server::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('server.index', compact('customer'));
    }
}
