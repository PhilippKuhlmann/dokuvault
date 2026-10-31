<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Customer;

class CertificateController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Certificate::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('certificate.index', compact('customer'));
    }
}
