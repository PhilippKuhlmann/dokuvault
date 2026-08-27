<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Customer;

class BackupController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Backup::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('backup.index', compact('customer'));
    }
}
