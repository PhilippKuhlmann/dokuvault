<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatchPanelRequest;
use App\Models\Customer;
use App\Models\PatchPanel;
use App\Models\Setting;

class PatchPanelController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', PatchPanel::class);

        $patchpanels = $this->getFilteredQuery(PatchPanel::class, $customer)
            ->with(['ports.networkSwitch', 'rackItem.rack'])
            ->latest()->paginate(Setting::seiteListe());

        return view('patchpanel.index', compact('customer', 'patchpanels'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', PatchPanel::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('patchpanel.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, PatchPanelRequest $request)
    {
        $this->authorize('create', PatchPanel::class);

        $patchpanel = $customer->patchpanels()->create($request->validated());
        $patchpanel->syncPorts();

        // Direkt ins Formular: ein frisches Patchfeld will beschriftet werden.
        return redirect(route('patchpanel.edit', [$customer, $patchpanel]));
    }

    public function edit(Customer $customer, PatchPanel $patchpanel)
    {
        $this->authorize('update', PatchPanel::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('patchpanel.edit', compact('customer', 'patchpanel', 'sites'));
    }

    public function update(Customer $customer, PatchPanel $patchpanel, PatchPanelRequest $request)
    {
        $this->authorize('update', PatchPanel::class);

        $data = $request->validated();

        // Portanzahl verkleinern wuerde die oberen Portzeilen loeschen. Solange
        // dort etwas dokumentiert ist, lieber ablehnen als still verwerfen.
        $betroffen = $patchpanel->documentedPortsAbove((int) $data['port_count']);
        if ($betroffen) {
            // "Port 5 ist" vs. "Ports 5, 6 und 12 sind"
            $liste = count($betroffen) === 1
                ? 'Port '.$betroffen[0].' ist'
                : 'Ports '.implode(', ', array_slice($betroffen, 0, -1)).' und '.end($betroffen).' sind';

            return back()->withErrors([
                'port_count' => 'Die Portanzahl kann nicht auf '.$data['port_count'].' verkleinert werden – '
                    .$liste.' noch dokumentiert.',
            ])->withInput();
        }

        $patchpanel->update($data);
        $patchpanel->syncPorts();

        return redirect(route('patchpanel.index', $customer));
    }

    public function destroy(Customer $customer, PatchPanel $patchpanel)
    {
        $this->authorize('delete', PatchPanel::class);

        $patchpanel->delete();

        return redirect(route('patchpanel.index', $customer));
    }
}
