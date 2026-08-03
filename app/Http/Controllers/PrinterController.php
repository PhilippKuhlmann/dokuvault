<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrinterRequest;
use App\Models\Customer;
use App\Models\Printer;

class PrinterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Printer::class);

        $printers = $this->getFilteredQuery(Printer::class, $customer)
            ->latest()->paginate(25);

        return view('printer.index', compact('customer', 'printers'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Printer::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('printer.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, PrinterRequest $request)
    {
        $this->authorize('create', Printer::class);

        $customer->printers()->create($request->validated());

        return redirect(route('printer.index', $customer));
    }

    public function edit(Customer $customer, Printer $printer)
    {
        $this->authorize('update', Printer::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('printer.edit', compact('customer', 'printer', 'sites'));
    }

    public function update(Customer $customer, Printer $printer, PrinterRequest $request)
    {
        $this->authorize('update', Printer::class);

        $printer->update($request->validated());

        return redirect(route('printer.index', $customer));
    }

    public function destroy(Customer $customer, Printer $printer)
    {
        $this->authorize('delete', Printer::class);

        $printer->delete();

        return redirect(route('printer.index', $customer));
    }
}
