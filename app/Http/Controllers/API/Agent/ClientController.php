<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsMachines;
use App\Models\Computer;
use Illuminate\Http\Request;

/** A Windows client reports itself. */
class ClientController extends AgentEndpoint
{
    use ReportsMachines;

    /**
     * Nimmt die von einem Windows-Arbeitsplatzrechner gemeldeten Daten
     * entgegen und legt ihn als Computer an bzw. aktualisiert ihn (Upsert
     * über agent_identifier = MachineGuid). Laeuft auf jedem Windows-PC,
     * anders als windowsAd() ohne RSAT/AD-Modul.
     */
    public function windowsClient(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate([
            'client.identifier' => ['required', 'string', 'max:255'],
            'client.hostname' => ['required', 'string', 'max:255'],
            'client.manufacturer' => ['nullable', 'string', 'max:255'],
            'client.model' => ['nullable', 'string', 'max:255'],
            'client.serial' => ['nullable', 'string', 'max:255'],
            'client.os' => ['nullable', 'string', 'max:255'],
            'client.ip' => ['nullable', 'string', 'max:255'],
        ]);

        $client = $data['client'];

        // Only an entry the catalog already has - an agent never creates one.
        // Without a match the field keeps whatever was chosen by hand.
        $osId = $this->catalogOsId($this->osCatalogName($client['os'] ?? null, 'Windows'));

        $vorhanden = Computer::where('customer_id', $customer->id)->where('site_id', $site->id)->where('agent_identifier', $client['identifier'])->first();

        $computer = Computer::updateOrCreate(
            ['customer_id' => $customer->id, 'site_id' => $site->id, 'agent_identifier' => $client['identifier']],
            array_filter([
                'site_id' => $site->id,
                'operating_system_id' => $osId,
                'name' => $client['hostname'],
            ], fn ($value) => $value !== null) + $this->hardwareFields($client, $vorhanden)
        );

        $this->reportUrl($computer, $customer->id, $site->id, $client['ip'] ?? null);

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'client' => $computer->name,
            'client_id' => $computer->id,
        ]);
    }
}
