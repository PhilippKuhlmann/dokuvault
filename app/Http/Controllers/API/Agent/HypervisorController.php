<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsBackups;
use App\Http\Controllers\API\Agent\Concerns\ReportsMachines;
use App\Models\Backup;
use App\Models\Cluster;
use App\Models\Server;
use App\Models\Service;
use App\Models\VM;
use Illuminate\Http\Request;

/**
 * Hypervisors report themselves and their guests: Proxmox, Hyper-V, VMware.
 *
 * The host becomes a server, its VMs and containers become VM entries
 * (upsert by agent_identifier). Nothing is deleted.
 */
class HypervisorController extends AgentEndpoint
{
    use ReportsBackups;
    use ReportsMachines;

    /**
     * Nimmt die von einem Proxmox-Host gemeldeten Daten entgegen und legt
     * den Host als Server sowie seine VMs/LXC-Container als VM-Einträge an
     * bzw. aktualisiert sie (Upsert über agent_identifier). Es wird nichts
     * gelöscht.
     */
    public function proxmox(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate(array_merge($this->hostRegeln(), $this->gastRegeln(), [
            'host.pve_version' => ['nullable', 'string', 'max:255'],
            'host.kernel' => ['nullable', 'string', 'max:255'],
            'host.cpu' => ['nullable', 'string', 'max:255'],
            'host.cores' => ['nullable', 'integer'],
            'host.memory_gb' => ['nullable', 'numeric'],
            'host.storages' => ['nullable', 'array'],
            'host.storages.*.name' => ['nullable', 'string', 'max:255'],
            'host.storages.*.type' => ['nullable', 'string', 'max:255'],
            'host.storages.*.total_gb' => ['nullable', 'numeric'],
            'host.storages.*.used_gb' => ['nullable', 'numeric'],
            // Name from corosync.conf, empty on a single node (since 26.10.12).
            'host.cluster' => ['nullable', 'string', 'max:255'],
            'host.replication' => ['nullable', 'boolean'],
        ], $this->backupRegeln('backups')));

        // Version-specific ("Proxmox VE 8" rather than just "Proxmox VE"):
        // 7/8/9 have different support ends, one catch-all entry could not
        // show that.
        //
        // fromOsRelease: the guests report building blocks ('debian', '12')
        // from /etc/os-release rather than finished names.
        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->mapPveVersion($data['host']['pve_version'] ?? null),
            fromOsRelease: true
        );

        $cluster = $this->proxmoxCluster($server, $data['guests'] ?? [], $data['host'], $customer, $site);

        // vzdump jobs of the cluster (since 26.10.03; older scripts send none).
        $backupCount = $this->backupsDokumentieren($customer->id, $data['backups'] ?? []);

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'cluster' => $cluster?->name,
            'guests_documented' => $guestCount,
            'backups_documented' => $backupCount,
        ]);
    }

    /**
     * Nimmt die von einem Hyper-V-Host gemeldeten Daten entgegen. Bis auf das
     * Betriebssystem des Hosts - Windows statt Proxmox VE - ist es dieselbe
     * Aufgabe wie bei proxmox(): ein Host, darunter seine Gäste.
     */
    public function hyperv(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate(array_merge($this->hostRegeln(), $this->gastRegeln(), [
            'host.os' => ['nullable', 'string', 'max:255'],
            'host.cpu' => ['nullable', 'string', 'max:255'],
            'host.memory_gb' => ['nullable', 'numeric'],
        ]));

        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->osCatalogName($data['host']['os'] ?? null, 'Windows')
        );

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'guests_documented' => $guestCount,
        ]);
    }

    /**
     * Nimmt die aus vCenter gemeldeten Daten entgegen - je Aufruf ein
     * ESXi-Host mit seinen VMs. Das Script meldet jeden Host einzeln, weil die
     * Zuordnung "welche VM läuft auf welchem Host" sonst verloren ginge.
     *
     * Anders als Proxmox und Hyper-V meldet vCenter weder Hersteller noch
     * Seriennummer des Hosts: die Schnittstelle gibt sie nicht heraus. Genau
     * dafür lässt hostUndGaeste() nicht gemeldete Felder unangetastet, statt
     * sie mit null zu überschreiben.
     */
    public function vmware(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate(array_merge($this->hostRegeln(), $this->gastRegeln(), [
            'host.os' => ['nullable', 'string', 'max:255'],
        ]));

        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->osCatalogName($data['host']['os'] ?? null, 'VMware ESXi')
        );

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'guests_documented' => $guestCount,
        ]);
    }

    /**
     * Die Regeln, die jeder Host-Meldung gemeinsam sind.
     *
     * @return array<string, array<int, string>>
     */
    protected function hostRegeln(): array
    {
        return [
            'host.identifier' => ['required', 'string', 'max:255'],
            'host.hostname' => ['required', 'string', 'max:255'],
            'host.manufacturer' => ['nullable', 'string', 'max:255'],
            'host.model' => ['nullable', 'string', 'max:255'],
            'host.serial' => ['nullable', 'string', 'max:255'],
            'host.ip' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Die Regeln, die jeder Gast-Meldung gemeinsam sind.
     *
     * @return array<string, array<int, string>>
     */
    protected function gastRegeln(): array
    {
        return [
            'guests' => ['nullable', 'array'],
            'guests.*.identifier' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.vmid' => ['nullable', 'integer'],
            'guests.*.type' => ['nullable', 'string', 'max:32'],
            'guests.*.os_id' => ['nullable', 'string', 'max:64'],
            'guests.*.os_version' => ['nullable', 'string', 'max:32'],
            'guests.*.os' => ['nullable', 'string', 'max:255'],
            'guests.*.ip' => ['nullable', 'string', 'max:255'],
            'guests.*.status' => ['nullable', 'string', 'max:32'],
            'guests.*.cores' => ['nullable', 'integer'],
            'guests.*.memory_gb' => ['nullable', 'numeric'],
            // Load of a running guest when the agent ran (since 26.10.12).
            'guests.*.cpu_pct' => ['nullable', 'numeric'],
            'guests.*.memory_used_gb' => ['nullable', 'numeric'],
            // Root disk of a container; a VM's is not visible from outside.
            'guests.*.disk_gb' => ['nullable', 'numeric'],
            'guests.*.disk_used_gb' => ['nullable', 'numeric'],
            // Proxmox tags; only those matching a service of the catalog count.
            'guests.*.tags' => ['nullable', 'array', 'max:50'],
            'guests.*.tags.*' => ['string', 'max:100'],
        ];
    }

    /**
     * The Proxmox cluster a node belongs to (name from corosync.conf).
     *
     * Every node reports the same name and finds the same entry by its
     * agent_identifier. A cluster of that name someone created by hand is
     * taken over instead of creating a second one. The type is only set when
     * creating - after that it is what someone chose.
     *
     * Node and guests get the cluster only while they have none: an
     * assignment made by hand stays.
     */
    protected function proxmoxCluster(Server $server, array $gaeste, array $host, $customer, $site): ?Cluster
    {
        $name = trim((string) ($host['cluster'] ?? ''));
        if ($name === '') {
            return null;
        }

        $kennung = 'proxmox/'.$name;
        $cluster = Cluster::where('customer_id', $customer->id)->where('site_id', $site->id)->where('agent_identifier', $kennung)->first()
            ?? Cluster::where('customer_id', $customer->id)->where('site_id', $site->id)->whereNull('agent_identifier')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orderBy('id')->first()
            ?? new Cluster([
                'customer_id' => $customer->id,
                'site_id' => $site->id,
                'name' => $name,
                'type' => $this->clusterType($host),
            ]);

        $cluster->agent_identifier = $kennung;
        $cluster->save();

        if (blank($server->cluster_id)) {
            $server->update(['cluster_id' => $cluster->id]);
        }

        $kennungen = collect($gaeste)->pluck('identifier')->filter()->all();
        if ($kennungen !== []) {
            VM::where('customer_id', $customer->id)->where('site_id', $site->id)->whereIn('agent_identifier', $kennungen)
                ->whereNull('cluster_id')->update(['cluster_id' => $cluster->id]);
        }

        return $cluster;
    }

    /**
     * The cluster type from what the node reports: Ceph storage, then
     * replication jobs, then shared storage, else local only. A PBS storage
     * is a backup target, not where guests run - it does not count.
     */
    protected function clusterType(array $host): string
    {
        $typen = collect($host['storages'] ?? [])->pluck('type')->filter()->map(fn ($t) => mb_strtolower($t));

        return match (true) {
            $typen->intersect(['rbd', 'cephfs'])->isNotEmpty() => 'ceph',
            (bool) ($host['replication'] ?? false) => 'replication',
            $typen->intersect(['nfs', 'cifs', 'iscsi', 'iscsidirect', 'glusterfs', 'zfs'])->isNotEmpty() => 'shared_storage',
            default => 'local',
        };
    }

    /**
     * "8.2.4" -> "Proxmox VE 8". Ohne auswertbare Hauptversion (Script zu alt,
     * pveversion nicht verfuegbar) bleibt "Proxmox VE" uebrig - und weil der
     * Katalog nur die Versionen 7/8/9 fuehrt, findet sich dazu nichts und der
     * Server bleibt ohne Betriebssystem. Besser als eine geratene Version:
     * daran haengt das Support-Ende.
     */
    protected function mapPveVersion(?string $pveVersion): string
    {
        if ($pveVersion && preg_match('/^(\d+)/', $pveVersion, $treffer)) {
            return 'Proxmox VE '.$treffer[1];
        }

        return 'Proxmox VE';
    }
}
