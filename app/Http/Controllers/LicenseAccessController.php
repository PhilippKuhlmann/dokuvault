<?php

namespace App\Http\Controllers;

use App\Http\Requests\LicenseAccessRequest;
use App\Models\Customer;
use App\Models\LicenseAccess;
use Illuminate\Support\Facades\Storage;

class LicenseAccessController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseAccess::class);

        return view('licenseaccess.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LicenseAccess::class);

        return view('licenseaccess.create', compact('customer'));
    }

    public function store(Customer $customer, LicenseAccessRequest $request)
    {
        $this->authorize('create', LicenseAccess::class);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time().'_'.$request->file_name.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs($customer->slug.'/licenseaccess', $fileName, 'local');
        } else {
            $filePath = null;
        }

        $data = $request->validated();
        $data['file_path'] = $filePath;

        $customer->licenseaccesses()->create($data);

        return redirect(route('licenseaccess.index', $customer));
    }

    public function edit(Customer $customer, LicenseAccess $licenseaccess)
    {
        $this->authorize('update', LicenseAccess::class);

        return view('licenseaccess.edit', compact('customer', 'licenseaccess'));
    }

    public function update(Customer $customer, LicenseAccess $licenseaccess, LicenseAccessRequest $request)
    {
        $this->authorize('update', LicenseAccess::class);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time().'_'.$request->file_name.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs($customer->slug.'/licenseaccess', $fileName, 'local');
            if ($licenseaccess->file_path) {
                Storage::delete($licenseaccess->file_path);
            }
            $data = $request->validated();
            $data['file_path'] = $filePath;
            $licenseaccess->update($data);
        } else {
            $licenseaccess->update($request->validated());
        }

        return redirect(route('licenseaccess.index', $customer));
    }

    public function destroy(Customer $customer, LicenseAccess $licenseaccess)
    {
        $this->authorize('delete', LicenseAccess::class);

        Storage::delete($licenseaccess->file_path);
        $licenseaccess->delete();

        return redirect(route('licenseaccess.index', $customer));
    }

    public function download(Customer $customer, LicenseAccess $licenseaccess)
    {
        $this->authorize('viewAny', LicenseAccess::class);

        return Storage::download($licenseaccess->file_path);
    }
}
