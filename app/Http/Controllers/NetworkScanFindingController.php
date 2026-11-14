<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\Agent\Concerns\ReportsAddresses;
use App\Http\Controllers\API\Agent\NetworkScanController;
use App\Models\Customer;
use App\Models\NetworkScanFinding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * What a technician does with a printer or UPS the network scan found:
 * take it over as a new device, link it to one documented by hand, ignore
 * it - or bring an ignored one back.
 *
 * Taken over or linked, the device carries the identifier from then on and
 * the finding is gone: later scans update the device itself.
 */
class NetworkScanFindingController extends Controller
{
    use ReportsAddresses;

    public function accept(Customer $customer, NetworkScanFinding $networkScanFinding, Request $request)
    {
        $fund = $this->finding($customer, $networkScanFinding);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('customer_id', $customer->id)],
        ]);

        $geraet = DB::transaction(function () use ($fund, $data, $customer) {
            $klasse = $fund->modelClass();
            $geraet = new $klasse;
            $geraet->forceFill([
                'customer_id' => $customer->id,
                'site_id' => (int) $data['site_id'],
                'name' => $data['name'],
                'agent_identifier' => $fund->identifier,
            ]);
            NetworkScanController::updateDevice($geraet, $this->reported($fund));

            $this->reportUrl($geraet, $customer->id, (int) $data['site_id'], $fund->ip);
            $fund->delete();

            return $geraet;
        });

        return $this->back($customer)
            ->with('success', __(':art :name übernommen – der Scan hält ihn ab jetzt aktuell.', ['art' => $fund->typeLabel(), 'name' => $geraet->name]));
    }

    /**
     * Ties the finding to a device documented by hand. Its technical facts
     * take what the scan reported; name, site and notes stay.
     */
    public function link(Customer $customer, NetworkScanFinding $networkScanFinding, Request $request)
    {
        $fund = $this->finding($customer, $networkScanFinding);
        $klasse = $fund->modelClass();

        $data = $request->validate([
            'device_id' => ['required', 'integer'],
        ]);

        $geraet = $klasse::forCustomer($customer->id)->whereNull('agent_identifier')->findOrFail($data['device_id']);

        DB::transaction(function () use ($fund, $geraet, $customer) {
            $geraet->agent_identifier = $fund->identifier;
            NetworkScanController::updateDevice($geraet, $this->reported($fund));

            $this->reportUrl($geraet, $customer->id, $geraet->site_id, $fund->ip);
            $fund->delete();
        });

        return $this->back($customer)
            ->with('success', __(':art :name verknüpft – der Scan hält ihn ab jetzt aktuell.', ['art' => $fund->typeLabel(), 'name' => $geraet->name]));
    }

    public function ignore(Customer $customer, NetworkScanFinding $networkScanFinding)
    {
        $this->finding($customer, $networkScanFinding)->update(['status' => 'ignored']);

        return $this->back($customer);
    }

    public function restore(Customer $customer, NetworkScanFinding $networkScanFinding)
    {
        $this->finding($customer, $networkScanFinding)->update(['status' => 'new']);

        return $this->back($customer);
    }

    private function finding(Customer $customer, NetworkScanFinding $fund): NetworkScanFinding
    {
        Gate::authorize('agent_manage');
        abort_if($fund->customer_id !== $customer->id, 404);

        return $fund;
    }

    /** The finding in the shape the scan reports it. */
    private function reported(NetworkScanFinding $fund): array
    {
        return $fund->only(['manufacturer', 'model', 'serial', 'firmware']);
    }

    private function back(Customer $customer)
    {
        return redirect(route('agent.index', $customer).'#neu-gefunden');
    }
}
