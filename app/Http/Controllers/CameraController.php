<?php

namespace App\Http\Controllers;

use App\Http\Requests\CameraRequest;
use App\Models\Camera;
use App\Models\Customer;

class CameraController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Camera::class);

        $cameras = $this->getFilteredQuery(Camera::class, $customer)
            ->latest()->paginate(25);

        return view('camera.index', compact('customer', 'cameras'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Camera::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('camera.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, CameraRequest $request)
    {
        $this->authorize('create', Camera::class);

        $customer->cameras()->create($request->validated());

        return redirect(route('camera.index', $customer))->withSuccess('Gespeichert!');
    }

    public function edit(Customer $customer, Camera $camera)
    {
        $this->authorize('update', Camera::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('camera.edit', compact('customer', 'camera', 'sites'));
    }

    public function update(Customer $customer, Camera $camera, CameraRequest $request)
    {
        $this->authorize('update', Camera::class);

        $camera->update($request->validated());

        return redirect(route('camera.index', $customer))->withSuccess('Gespeichert!');
    }

    public function destroy(Customer $customer, Camera $camera)
    {
        $this->authorize('delete', Camera::class);

        $camera->delete();

        return redirect(route('camera.index', $customer))->withSuccess('Gelöscht!');
    }
}
