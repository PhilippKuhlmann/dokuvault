<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SecurepointUMA;

class SecurepointUMAController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', SecurepointUMA::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('securepointuma.index', compact('customer'));
    }
}
