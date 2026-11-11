<?php

namespace App\Http\Controllers\API\Agent\Concerns;

use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Service;
use App\Models\VM;

/**
 * What hypervisors, servers and clients have in common when they report:
 * hardware fields, the operating system from the catalog, and a host with
 * the guests running on it.
 *
 * Brings ReportsAddresses along - a machine is reported with its address.
 */
trait ReportsMachines
{
    use ReportsAddresses;

    /** What a host reports beyond its columns (agent_details). */
    protected const HOST_DETAILS = ['pve_version', 'kernel', 'cpu', 'cores', 'memory_gb', 'storages'];

    /** What a guest reports beyond its columns (agent_details). */
    protected const GAST_DETAILS = ['type', 'vmid', 'status', 'cores', 'memory_gb', 'cpu_pct', 'memory_used_gb', 'disk_gb', 'disk_used_gb'];

    /**
     * What /etc/os-release reports, mapped onto the operating system
     * catalog: 'debian' + '12' becomes "Debian 12".
     *
     * Spelled out and not guessed. What is not listed here stays without an
     * operating system - an empty field is better than a wrong one: "Debian
     * 12" and "Debian 13" have different ends of support, and that is exactly
     * what this field is read for.
     *
     * The number says how many parts of the version the catalog carries -
     * "Debian 12", but "Ubuntu Server 24.04 LTS".
     */
    protected const OS_RELEASE_KATALOG = [
        'debian' => ['Debian %s', 1],
        'ubuntu' => ['Ubuntu Server %s LTS', 2],
        'rocky' => ['Rocky Linux %s', 1],
        'almalinux' => ['AlmaLinux %s', 1],
        'opensuse-leap' => ['openSUSE Leap %s', 1],
    ];

    /**
     * Legt einen Host als Server an und seine Gäste als VMs bzw. aktualisiert
     * sie. Proxmox, Hyper-V, VMware und der Windows-Server-Agent tun genau
     * das; nur die Herkunft des Betriebssystemnamens unterscheidet sie.
     *
     * @return array{0: Server, 1: int} Server und Zahl der gemeldeten Gäste
     */
    protected function hostUndGaeste(array $host, array $gaeste, $customer, $site, string $hostOs, bool $fromOsRelease = false): array
    {
        // Hinweis: 'services' wird hier NICHT gesetzt - das Feld pflegt der
        // Nutzer manuell (Rollen wie AD, FS, DNS, DHCP ...). Allein
        // windowsServer() traegt etwas ein, und auch nur in ein leeres Feld.
        $attribute = [
            'site_id' => $site->id,
            'name' => $host['hostname'],
        ];

        // Only set on a match. A null would wipe, on every run, what someone
        // entered by hand.
        if ($osId = $this->catalogOsId($hostOs)) {
            $attribute['operating_system_id'] = $osId;
        }

        // Nur gemeldete Felder schreiben. vCenter gibt Hersteller, Modell und
        // Seriennummer nicht heraus - wuerde hier stur null eingetragen, loeschte
        // jeder Lauf, was jemand von Hand nachgetragen hat.
        $vorhanden = Server::where('customer_id', $customer->id)->where('site_id', $site->id)->where('agent_identifier', $host['identifier'])->first();
        $attribute += $this->hardwareFields($host, $vorhanden);

        if ($details = $this->agentDetails($host, self::HOST_DETAILS)) {
            $attribute['agent_details'] = $details;
            $attribute['agent_reported_at'] = now();
        }

        $server = Server::updateOrCreate(
            ['customer_id' => $customer->id, 'site_id' => $site->id, 'agent_identifier' => $host['identifier']],
            $attribute
        );

        // Die IP ist keine Spalte am Geraet mehr, sondern ein Eintrag im Block
        // "Weitere IP-Adressen".
        $this->reportUrl($server, $customer->id, $site->id, $host['ip'] ?? null);

        $anzahl = 0;
        foreach ($gaeste as $gast) {
            $vmAttribute = [
                'site_id' => $site->id,
                'server_id' => $server->id,
                'name' => $gast['name'] ?? ('VM '.($gast['vmid'] ?? '')),
                // 'services' bleibt manuell (Rollen der VM)
            ];

            // Proxmox reports what /etc/os-release says in the guest ('debian',
            // '12'); the others a finished name ("Windows Server 2022").
            $gastOsName = $fromOsRelease
                ? $this->osReleaseCatalogName($gast['os_id'] ?? null, $gast['os_version'] ?? null)
                : $this->osCatalogName($gast['os'] ?? null, 'Unbekannt');

            if ($gastOsId = $this->catalogOsId($gastOsName)) {
                $vmAttribute['operating_system_id'] = $gastOsId;
            }

            if ($details = $this->agentDetails($gast, self::GAST_DETAILS)) {
                $vmAttribute['agent_details'] = $details;
                $vmAttribute['agent_reported_at'] = now();
            }

            $vm = VM::updateOrCreate(
                ['customer_id' => $customer->id, 'site_id' => $site->id, 'agent_identifier' => $gast['identifier']],
                $vmAttribute
            );

            $this->reportUrl($vm, $customer->id, $site->id, $gast['ip'] ?? null);

            // Tags that name a service of the catalog (case-insensitive,
            // stored in the catalog's spelling) are added to the services -
            // added, never removed: what was entered by hand stays.
            if (! empty($gast['tags'])) {
                $katalog = Service::pluck('name')->keyBy(fn ($n) => mb_strtolower($n));
                $neu = collect($gast['tags'])->map(fn ($t) => $katalog[mb_strtolower(trim($t))] ?? null)->filter();
                $vorhanden = collect(explode(',', (string) $vm->getRawOriginal('services')))->map(fn ($d) => trim($d))->filter();
                $alle = $vorhanden->merge($neu)->unique(fn ($d) => mb_strtolower($d))->values();
                if ($alle->count() > $vorhanden->count()) {
                    $vm->update(['services' => $alle->implode(',')]);
                }
            }
            $anzahl++;
        }

        return [$server, $anzahl];
    }

    /**
     * The reported values that have no column of their own, for
     * agent_details: only the keys listed, only those sent. null when none
     * was sent - then the stored report stays as it is.
     *
     * @param  array<int, string>  $schluessel
     */
    protected function agentDetails(array $gemeldet, array $schluessel): ?array
    {
        $details = array_filter(
            array_intersect_key($gemeldet, array_flip($schluessel)),
            fn ($wert) => $wert !== null && $wert !== '' && $wert !== []
        );

        return $details === [] ? null : $details;
    }

    /**
     * Manufacturer, model and serial as reported - without BIOS placeholders.
     *
     * Desktop and whitebox boards leave "System manufacturer", "To Be Filled
     * By O.E.M." and the like in the DMI fields; the agent reads them
     * correctly, they just mean nothing. Such a value is not written - and if
     * an earlier run already stored one, it is cleared. A real value entered
     * by hand stays when the agent has nothing better.
     *
     * @return array<string, string|null>
     */
    private function hardwareFields(array $gemeldet, $vorhanden): array
    {
        $felder = [];

        foreach (['manufacturer' => 'manufacturer', 'model' => 'model', 'serial' => 'serialNumber'] as $quelle => $spalte) {
            if (! array_key_exists($quelle, $gemeldet)) {
                continue;
            }

            $wert = trim((string) $gemeldet[$quelle]);

            if ($wert !== '' && ! $this->istPlatzhalter($wert)) {
                $felder[$spalte] = $wert;
            } elseif ($vorhanden && $this->istPlatzhalter((string) $vorhanden->{$spalte})) {
                $felder[$spalte] = null;
            }
        }

        return $felder;
    }

    private function istPlatzhalter(string $wert): bool
    {
        $wert = strtolower(trim($wert));

        if ($wert === '') {
            return false;
        }

        // Only zeros, X, dots, dashes: "0000000000", "XXXXXXXX", "-".
        if (preg_match('/^[0x\s.\-_]+$/i', $wert)) {
            return true;
        }

        return in_array($wert, [
            'system manufacturer', 'system product name', 'system serial number', 'system version',
            'to be filled by o.e.m.', 'to be filled by oem', 'default string', 'default',
            'not specified', 'not applicable', 'not available', 'none', 'n/a', 'na', 'oem', 'o.e.m.',
            'unknown', 'undefined', 'invalid', '0123456789', '123456789', '1234567890',
            'base board serial number', 'chassis manufacturer', 'chassis serial number',
            'type1productconfigid', 'sku', 'all series',
        ], true);
    }

    /**
     * The catalog name for an operating system name an agent reports.
     *
     * Win32_OperatingSystem.Caption always says "Microsoft Windows ..."; the
     * catalog lists Windows without that prefix (see the seeder, e.g.
     * "Windows Server 2012 R2 Standard"). Without trimming it nothing would
     * match the existing "Windows 11 Pro".
     */
    protected function osCatalogName(?string $name, string $fallback): string
    {
        $clean = trim(preg_replace('/^Microsoft\s+/i', '', (string) $name));

        return $clean !== '' ? $clean : $fallback;
    }

    /**
     * The id of an existing catalog entry for an operating system name.
     *
     * Agents only pick from the catalog, they never add to it. It is shared
     * by all customers: what an agent - or someone holding a customer's agent
     * token - reported used to land there for everyone, with names nobody
     * curated and no support end. An unknown name gives null, and the device
     * keeps what was chosen by hand.
     */
    protected function catalogOsId(?string $name): ?int
    {
        $name = trim((string) $name);

        return $name === '' ? null : OperatingSystem::where('name', $name)->value('id');
    }

    /**
     * "debian" + "12" -> "Debian 12", anhand von OS_RELEASE_KATALOG.
     *
     * Was dort nicht steht, ergibt null - und damit kein Betriebssystem.
     */
    protected function osReleaseCatalogName(?string $id, ?string $version): ?string
    {
        $id = strtolower(trim((string) $id));
        $version = trim((string) $version);

        if ($version === '' || ! isset(self::OS_RELEASE_KATALOG[$id])) {
            return null;
        }

        [$vorlage, $stellen] = self::OS_RELEASE_KATALOG[$id];

        return sprintf($vorlage, implode('.', array_slice(explode('.', $version), 0, $stellen)));
    }
}
