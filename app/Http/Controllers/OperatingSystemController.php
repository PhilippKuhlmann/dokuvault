<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperatingSystemRequest;
use App\Models\OperatingSystem;

class OperatingSystemController extends Controller
{
    public function index()
    {
        $operatingSystems = OperatingSystem::paginate(20);
        $operatingSystemsCount = OperatingSystem::all()->count();

        return view('admin.operatingsystem.index', compact('operatingSystems', 'operatingSystemsCount'));
    }

    public function create()
    {
        return view('admin.operatingsystem.create');
    }

    public function store(OperatingSystemRequest $request)
    {
        OperatingSystem::create($request->validated());

        return redirect(route('admin.operatingsystem.index'));
    }

    public function edit(OperatingSystem $operatingSystem)
    {
        return view('admin.operatingsystem.edit', compact('operatingSystem'));
    }

    public function update(OperatingSystem $operatingSystem, OperatingSystemRequest $request)
    {
        $operatingSystem->update($request->validated());

        return redirect(route('admin.operatingsystem.index', $operatingSystem));
    }
}
