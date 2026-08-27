<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\Customer;

class CameraController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Camera::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('camera.index', compact('customer'));
    }
}
