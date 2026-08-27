<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Customer;

class CertificateController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Certificate::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('certificate.index', compact('customer'));
    }
}
