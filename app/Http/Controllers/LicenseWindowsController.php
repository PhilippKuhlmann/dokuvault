<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LicenseWindows;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Storage;

class LicenseWindowsController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseWindows::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('licensewindows.index', compact('customer'));
    }

    public function download(Customer $customer, LicenseWindows $licensewindows)
    {
        $this->authorize('viewAny', LicenseWindows::class);

        // Never a path outside this customer's licence folder - see StoragePath.
        abort_unless(StoragePath::belongsTo($licensewindows->file_path, $customer, 'licensewindows'), 404);

        return Storage::download($licensewindows->file_path);
    }
}
