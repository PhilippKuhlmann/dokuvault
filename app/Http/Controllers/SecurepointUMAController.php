<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SecurepointUMA;

class SecurepointUMAController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', SecurepointUMA::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('securepointuma.index', compact('customer'));
    }
}
