<?php

namespace App\Http\Controllers;

use App\Http\Requests\LicenseSoftwareRequest;
use App\Models\Customer;
use App\Models\LicenseSoftware;
use Illuminate\Support\Facades\Storage;

class LicenseSoftwareController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', LicenseSoftware::class);

        return view('licensesoftware.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', LicenseSoftware::class);

        return view('licensesoftware.create', compact('customer'));
    }

    public function store(Customer $customer, LicenseSoftwareRequest $request)
    {
        $this->authorize('create', LicenseSoftware::class);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time().'_'.$request->file_name.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs($customer->slug.'/licensesoftware', $fileName, 'local');
        } else {
            $filePath = null;
        }

        $data = $request->validated();
        $data['file_path'] = $filePath;

        $customer->licensesoftware()->create($data);

        return redirect(route('licensesoftware.index', $customer));
    }

    public function edit(Customer $customer, LicenseSoftware $licensesoftware)
    {
        $this->authorize('update', LicenseSoftware::class);

        return view('licensesoftware.edit', compact('customer', 'licensesoftware'));
    }

    public function update(Customer $customer, LicenseSoftware $licensesoftware, LicenseSoftwareRequest $request)
    {
        $this->authorize('update', LicenseSoftware::class);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time().'_'.$request->file_name.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs($customer->slug.'/licensesoftware', $fileName, 'local');
            if ($licensesoftware->file_path) {
                Storage::delete($licensesoftware->file_path);
            }
            $data = $request->validated();
            $data['file_path'] = $filePath;
            $licensesoftware->update($data);
        } else {
            $licensesoftware->update($request->validated());
        }

        return redirect(route('licensesoftware.index', $customer));
    }

    public function destroy(Customer $customer, LicenseSoftware $licensesoftware)
    {
        $this->authorize('delete', LicenseSoftware::class);

        Storage::delete($licensesoftware->file_path);
        $licensesoftware->delete();

        return redirect(route('licensesoftware.index', $customer));
    }

    public function download(Customer $customer, LicenseSoftware $licensesoftware)
    {
        $this->authorize('viewAny', LicenseSoftware::class);

        return Storage::download($licensesoftware->file_path);
    }
}
