<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LicenseAccess;
use Illuminate\Support\Facades\Storage;

class LicenseAccessController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseAccess::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('licenseaccess.index', compact('customer'));
    }

    public function download(Customer $customer, LicenseAccess $licenseaccess)
    {
        $this->authorize('viewAny', LicenseAccess::class);

        return Storage::download($licenseaccess->file_path);
    }
}
