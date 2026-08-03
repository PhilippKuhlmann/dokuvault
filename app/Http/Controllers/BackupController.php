<?php

namespace App\Http\Controllers;

use App\Http\Requests\BackupRequest;
use App\Models\Backup;
use App\Models\Customer;

class BackupController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Backup::class);
        $backups = Backup::where('customer_id', $customer->id)->latest()->paginate(25);

        return view('backup.index', compact('customer', 'backups'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Backup::class);

        return view('backup.create', compact('customer'));
    }

    public function store(Customer $customer, BackupRequest $request)
    {
        $this->authorize('create', Backup::class);
        $customer->backups()->create($request->validated());

        return redirect(route('backup.index', $customer));
    }

    public function edit(Customer $customer, Backup $backup)
    {
        $this->authorize('update', Backup::class);

        return view('backup.edit', compact('customer', 'backup'));
    }

    public function update(Customer $customer, Backup $backup, BackupRequest $request)
    {
        $this->authorize('update', Backup::class);
        $backup->update($request->validated());

        return redirect(route('backup.index', $customer));
    }

    public function destroy(Customer $customer, Backup $backup)
    {
        $this->authorize('delete', Backup::class);
        $backup->delete();

        return redirect(route('backup.index', $customer));
    }
}
