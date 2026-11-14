<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsAddresses;
use App\Models\AgentInstallation;
use App\Models\Network;
use App\Models\NetworkScanFinding;
use Illuminate\Http\Request;

/**
 * What the network scan of the Windows agent found: printers, UPS,
 * switches, access points, NAS and cameras that answered SNMP in the
 * networks chosen on the agent page.
 *
 * Unlike the other agents nothing is created here. A device already tied
 * to the scan (agent_identifier) is updated - its technical facts only,
 * never what someone wrote by hand. Everything else becomes a finding the
 * technician takes over, links or ignores on the agent page.
 *
 * Customer-wide, not per site like the other endpoints: the networks are
 * picked on the page and may lie at other sites - the site of a taken-over
 * device comes from its network.
 */
class NetworkScanController extends AgentEndpoint
{
    use ReportsAddresses;

    public function store(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');

        $data = $request->validate([
            'machine_id' => ['required', 'string', 'max:100'],
            'devices' => ['present', 'array', 'max:2000'],
            'devices.*.type' => ['required', 'string', 'in:'.implode(',', array_keys(NetworkScanFinding::TYPES))],
            'devices.*.ip' => ['required', 'ip'],
            'devices.*.serial' => ['nullable', 'string', 'max:255'],
            'devices.*.mac' => ['nullable', 'string', 'max:50'],
            'devices.*.name' => ['nullable', 'string', 'max:255'],
            'devices.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'devices.*.model' => ['nullable', 'string', 'max:255'],
            'devices.*.firmware' => ['nullable', 'string', 'max:255'],
        ]);

        // Only a machine that checked in with this customer and has the scan
        // switched on - a token without the role must not create findings.
        $installation = AgentInstallation::where('customer_id', $customer->id)
            ->where('machine_id', strtolower($data['machine_id']))
            ->firstOrFail();
        abort_unless($installation->runsScan(), 403, __('Der Netzwerk-Scan ist für diesen Agenten nicht eingeschaltet.'));

        $aktualisiert = 0;
        $neu = 0;
        $ohneKennung = 0;
        $vonAnderen = 0;

        foreach ($data['devices'] as $geraet) {
            $kennung = static::identifier($geraet);
            if ($kennung === null) {
                $ohneKennung++;

                continue;
            }

            $klasse = NetworkScanFinding::TYPES[$geraet['type']][0];
            $vorhanden = $klasse::forCustomer($customer->id)->where('agent_identifier', $kennung)->first();

            if ($vorhanden) {
                static::updateDevice($vorhanden, $geraet);
                $this->reportUrl($vorhanden, $customer->id, $vorhanden->site_id, $geraet['ip']);
                $aktualisiert++;

                continue;
            }

            if (static::ownedByUnifi($klasse, $customer->id, $geraet)) {
                $vonAnderen++;

                continue;
            }

            $fund = NetworkScanFinding::firstOrNew([
                'customer_id' => $customer->id,
                'type' => $geraet['type'],
                'identifier' => $kennung,
            ]);

            if (! $fund->exists) {
                $neu++;
            }

            // An ignored device stays ignored; its details are kept current
            // all the same, for when someone brings it back.
            $fund->fill([
                'agent_installation_id' => $installation->id,
                'network_id' => static::networkFor($installation, $geraet['ip']),
                'name' => static::clean($geraet['name'] ?? null),
                'manufacturer' => static::clean($geraet['manufacturer'] ?? null),
                'model' => static::clean($geraet['model'] ?? null),
                'serial' => static::clean($geraet['serial'] ?? null),
                'firmware' => static::clean($geraet['firmware'] ?? null),
                'ip' => $geraet['ip'],
                'last_seen_at' => now(),
            ])->save();
        }

        return response()->json([
            'status' => 'ok',
            'devices_updated' => $aktualisiert,
            'findings_new' => $neu,
            'skipped_without_identifier' => $ohneKennung,
            'skipped_other_agent' => $vonAnderen,
        ]);
    }

    /**
     * What ties a device to the scan: its serial number - an address
     * changes, a serial does not. A device without one (some cheap
     * printers) by its MAC address. Neither: no way to recognise it next
     * time, so it is skipped.
     */
    public static function identifier(array $geraet): ?string
    {
        $serie = static::clean($geraet['serial'] ?? null);
        if ($serie !== null) {
            return mb_strtoupper($serie);
        }

        $mac = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', (string) ($geraet['mac'] ?? '')));
        if (strlen($mac) === 12 && $mac !== '000000000000') {
            return 'mac:'.implode(':', str_split($mac, 2));
        }

        return null;
    }

    /**
     * A switch or access point the UniFi agent already documents - same
     * MAC, serial or address. The scan would only offer a duplicate; the
     * UniFi agent keeps it current anyway. Its identifier is the bare MAC
     * address ("aa:bb:..."), the scan's never is - so a device the scan
     * itself took over and that was since replaced at the same address is
     * still offered.
     */
    protected static function ownedByUnifi(string $klasse, int $customerId, array $geraet): bool
    {
        if (! in_array($geraet['type'], ['switch', 'accesspoint'], true)) {
            return false;
        }

        $mac = static::identifier(['mac' => $geraet['mac'] ?? null]);
        $mac = $mac !== null ? substr($mac, 4) : null;
        $serie = static::clean($geraet['serial'] ?? null);

        return $klasse::forCustomer($customerId)
            ->whereNotNull('agent_identifier')
            ->where(function ($q) use ($mac, $serie, $geraet) {
                $q->whereHas('ipAddresses', fn ($a) => $a->where('address', $geraet['ip']));
                if ($mac !== null) {
                    $q->orWhere('agent_identifier', $mac);
                }
                if ($serie !== null) {
                    $q->orWhere('serialNumber', $serie);
                }
            })
            ->pluck('agent_identifier')
            ->contains(fn ($kennung) => preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/i', (string) $kennung) === 1);
    }

    /**
     * The technical facts of a device tied to the scan. Only what the scan
     * reported: a value it did not get stays as it is, and name, site,
     * notes and credentials belong to whoever documents the device.
     */
    public static function updateDevice($geraet, array $gemeldet): void
    {
        foreach (['manufacturer' => 'manufacturer', 'model' => 'model', 'serial' => 'serialNumber', 'firmware' => 'firmware'] as $von => $nach) {
            $wert = static::clean($gemeldet[$von] ?? null);
            if ($wert !== null) {
                $geraet->$nach = $wert;
            }
        }

        $geraet->save();
    }

    /** The scanned network the address lies in - for the site on takeover. */
    protected static function networkFor(AgentInstallation $installation, string $ip): ?int
    {
        $lang = ip2long($ip);
        if ($lang === false) {
            return null;
        }

        return $installation->scanNetworks()
            ->first(function (Network $netz) use ($lang) {
                [$adresse, $praefix] = explode('/', (string) AgentInstallation::cidrOf($netz));
                $maske = (-1 << (32 - (int) $praefix)) & 0xFFFFFFFF;

                return ($lang & $maske) === ip2long($adresse);
            })?->id;
    }

    /** Trimmed, and null for nothing - SNMP answers often pad with blanks. */
    protected static function clean(?string $wert): ?string
    {
        $wert = trim((string) $wert, " \t\n\r\0\x0B");

        return $wert === '' ? null : mb_substr($wert, 0, 255);
    }
}
