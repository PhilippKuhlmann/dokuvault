<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Storage;

class LicenseSoftwareController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseSoftware::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('licensesoftware.index', compact('customer'));
    }

    public function download(Customer $customer, LicenseSoftware $licensesoftware)
    {
        $this->authorize('viewAny', LicenseSoftware::class);

        // Never a path outside this customer's licence folder - see StoragePath.
        abort_unless(StoragePath::belongsTo($licensesoftware->file_path, $customer, 'licensesoftware'), 404);

        return Storage::download($licensesoftware->file_path);
    }
}
