<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Recorder;

class RecorderController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Recorder::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('recorder.index', compact('customer'));
    }
}
