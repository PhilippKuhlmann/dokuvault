<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OtherClient;

class OtherClientController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', OtherClient::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('otherclient.index', compact('customer'));
    }
}
