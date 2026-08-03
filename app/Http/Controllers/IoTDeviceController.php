<?php

namespace App\Http\Controllers;

use App\Http\Requests\IoTDeviceRequest;
use App\Models\Customer;
use App\Models\IoTDevice;
use App\Models\Site;

class IoTDeviceController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', IoTDevice::class);

        $iotdevices = $this->getFilteredQuery(IoTDevice::class, $customer)->latest()->paginate(25);

        return view('iotdevice.index', compact('customer', 'iotdevices'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', IoTDevice::class);

        $sites = Site::where('customer_id', $customer->id)->get();

        return view('iotdevice.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, IoTDeviceRequest $request)
    {
        $this->authorize('create', IoTDevice::class);

        $customer->iotdevices()->create($request->validated());

        return redirect(route('iotdevice.index', $customer));
    }

    public function edit(Customer $customer, IoTDevice $iotdevice)
    {
        $this->authorize('update', IoTDevice::class);

        $sites = Site::where('customer_id', $customer->id)->get();

        return view('iotdevice.edit', compact('customer', 'sites', 'iotdevice'));
    }

    public function update(Customer $customer, IoTDevice $iotdevice, IoTDeviceRequest $request)
    {
        $this->authorize('update', IoTDevice::class);

        $iotdevice->update($request->validated());

        return redirect(route('iotdevice.index', $customer));
    }

    public function destroy(Customer $customer, IoTDevice $iotdevice)
    {
        $this->authorize('delete', IoTDevice::class);

        $iotdevice->delete();

        return redirect(route('iotdevice.index', $customer));
    }
}
