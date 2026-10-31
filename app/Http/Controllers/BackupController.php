<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Customer;

class BackupController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Backup::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('backup.index', compact('customer'));
    }
}
