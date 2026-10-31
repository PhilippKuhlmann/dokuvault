<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LicenseAccess;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Storage;

class LicenseAccessController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseAccess::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('licenseaccess.index', compact('customer'));
    }

    public function download(Customer $customer, LicenseAccess $licenseaccess)
    {
        $this->authorize('viewAny', LicenseAccess::class);

        // Never a path outside this customer's licence folder - see StoragePath.
        abort_unless(StoragePath::belongsTo($licenseaccess->file_path, $customer, 'licenseaccess'), 404);

        return Storage::download($licenseaccess->file_path);
    }
}
