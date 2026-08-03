<?php

namespace App\Http\Controllers;

use App\Http\Requests\FTPServerRequest;
use App\Models\Customer;
use App\Models\FTPServer;

class FTPServerController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', FTPServer::class);

        return view('ftpserver.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', FTPServer::class);

        return view('ftpserver.create', compact('customer'));
    }

    public function store(Customer $customer, FTPServerRequest $request)
    {
        $this->authorize('create', FTPServer::class);

        $customer->ftpservers()->create($request->validated());

        return redirect(route('ftpserver.index', $customer));
    }

    public function edit(Customer $customer, FTPServer $ftpserver)
    {
        $this->authorize('update', FTPServer::class);

        return view('ftpserver.edit', compact('customer', 'ftpserver'));
    }

    public function update(Customer $customer, FTPServer $ftpserver, FTPServerRequest $request)
    {
        $this->authorize('update', FTPServer::class);

        $ftpserver->update($request->validated());

        return redirect(route('ftpserver.index', $customer));
    }

    public function destroy(Customer $customer, FTPServer $ftpserver)
    {
        $this->authorize('delete', FTPServer::class);

        $ftpserver->delete();

        return redirect(route('ftpserver.index', $customer));
    }
}
