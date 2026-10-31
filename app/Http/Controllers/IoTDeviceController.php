<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\IoTDevice;

class IoTDeviceController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', IoTDevice::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('iotdevice.index', compact('customer'));
    }
}
