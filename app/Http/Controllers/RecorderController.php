<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Recorder;

class RecorderController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Recorder::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('recorder.index', compact('customer'));
    }
}
