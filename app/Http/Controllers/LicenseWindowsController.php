<?php

namespace App\Http\Controllers;

use App\Http\Requests\LicenseWindowsRequest;
use App\Models\Customer;
use App\Models\LicenseWindows;
use App\Models\OperatingSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LicenseWindowsController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseWindows::class);

        // Windows-Lizenzen haben keinen Standortbezug (keine site_id-Spalte), daher
        // bewusst ohne getFilteredQuery: der Standortfilter gilt hier nicht.
        $licensewindowsList = LicenseWindows::where('customer_id', $customer->id)
                        ->with('operatingSystem')
                        ->latest()->paginate(25);

        return view('licensewindows.index', compact('customer', 'licensewindowsList'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LicenseWindows::class);

        $operatingSystems = OperatingSystem::all();

        return view('licensewindows.create', compact('customer', 'operatingSystems'));
    }

    public function store(Customer $customer, LicenseWindowsRequest $request)
    {
        $this->authorize('create', LicenseWindows::class);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $request->file_name . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($customer->slug . '/licensewindows', $fileName, 'local');
        } else {
            $filePath = null;
        }

        $data = $request->validated();
        $data['file_path'] = $filePath;

        $customer->licensewindows()->create($data);

        return redirect(route('licensewindows.index', $customer));
    }

    public function edit(Customer $customer, LicenseWindows $licensewindows)
    {
        $this->authorize('update', LicenseWindows::class);

        $operatingSystems = OperatingSystem::all();

        return view('licensewindows.edit', compact('customer', 'operatingSystems', 'licensewindows'));
    }

    public function update(Customer $customer, LicenseWindows $licensewindows, LicenseWindowsRequest $request)
    {
        $this->authorize('update', LicenseWindows::class);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $request->file_name . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($customer->slug . '/licensewindows', $fileName, 'local');
            if ($licensewindows->file_path) {
                Storage::delete($licensewindows->file);
            }
            $data = $request->validated();
            $data['file_path'] = $filePath;
            $licensewindows->update($data);
        } else {
            $licensewindows->update($request->validated());
        }

        return redirect(route('licensewindows.index', $customer));
    }

    public function destroy(Customer $customer, LicenseWindows $licensewindows)
    {
        $this->authorize('delete', LicenseWindows::class);

        Storage::delete($licensewindows->file);
        $licensewindows->delete();

        return redirect(route('licensewindows.index', $customer));
    }

    public function download(Customer $customer, LicenseWindows $licensewindows)
    {
        $this->authorize('viewAny', LicenseWindows::class);

        return Storage::download($licensewindows->file_path);
    }
}
