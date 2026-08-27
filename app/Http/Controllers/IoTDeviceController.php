<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\IoTDevice;

class IoTDeviceController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', IoTDevice::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('iotdevice.index', compact('customer'));
    }
}
