<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use App\Models\Setting;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('name')->paginate(Setting::seiteAdmin());
        $servicesCount = Service::count();

        return view('admin.service.index', compact('services', 'servicesCount'));
    }

    public function create()
    {
        return view('admin.service.create');
    }

    public function store(ServiceRequest $request)
    {
        Service::create($request->validated());

        return redirect(route('admin.service.index'));
    }

    public function edit(Service $service)
    {
        return view('admin.service.edit', compact('service'));
    }

    public function update(Service $service, ServiceRequest $request)
    {
        $service->update($request->validated());

        return redirect(route('admin.service.index'));
    }

    /**
     * Löscht nur den Katalogeintrag. Die Dienste an den Geräten stehen als
     * Freitext in deren `services`-Spalte und bleiben erhalten - sie werden
     * danach nur wieder neutral gezeichnet.
     */
    public function destroy(Service $service)
    {
        $service->delete();

        return redirect(route('admin.service.index'));
    }
}
