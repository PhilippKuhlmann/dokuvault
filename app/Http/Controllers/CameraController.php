<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\Customer;

class CameraController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Camera::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('camera.index', compact('customer'));
    }
}
