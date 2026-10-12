<?php

namespace Database\Seeders;

use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\VM;
use Illuminate\Database\Seeder;

/**
 * What the Proxmox agent reports about the demo cluster (PVE-01 to PVE-03 of
 * LocalDatabaseSeeder): CPU, memory and storages of the nodes, and the
 * guests running on them - so the server and VM cards show their system
 * blocks, the memory share of the guests and the storage bars.
 *
 * One storage per state the card tells apart: one at 85 % (amber), one at
 * 95 % (red), one that cannot be reached ("nicht verfügbar").
 *
 * Runs again without duplicates (guests by agent_identifier), and on its own
 * against existing data:
 *
 *   php artisan db:seed --class=DemoProxmoxSeeder
 */
class DemoProxmoxSeeder extends Seeder
{
    public function run(): void
    {
        $knoten = [
            'PVE-01' => [
                'cpu' => 'Intel Xeon Silver 4314 @ 2.40GHz (32 Kerne)', 'cores' => 32, 'memory_gb' => 256,
                'storages' => [
                    ['name' => 'local', 'type' => 'dir', 'total_gb' => 94, 'used_gb' => 38],
                    ['name' => 'local-lvm', 'type' => 'lvmthin', 'total_gb' => 832, 'used_gb' => 210],
                    ['name' => 'ceph-vm', 'type' => 'rbd', 'total_gb' => 11500, 'used_gb' => 4120],
                    ['name' => 'pbs-backup', 'type' => 'pbs', 'total_gb' => 9200, 'used_gb' => 7820],
                ],
                'gaeste' => [
                    [101, 'VM-DC02', 'qemu', 'running', 4, 8, 'Windows Server 2022 Standard'],
                    [102, 'VM-Exchange', 'qemu', 'running', 8, 32, 'Windows Server 2022 Standard'],
                    [201, 'CT-Proxy', 'lxc', 'running', 2, 2, 'Debian 13'],
                    [202, 'CT-Test', 'lxc', 'stopped', 1, 0.5, 'Debian 12'],
                ],
            ],
            'PVE-02' => [
                'cpu' => 'Intel Xeon Silver 4314 @ 2.40GHz (32 Kerne)', 'cores' => 32, 'memory_gb' => 256,
                'storages' => [
                    ['name' => 'local', 'type' => 'dir', 'total_gb' => 94, 'used_gb' => 41],
                    ['name' => 'local-lvm', 'type' => 'lvmthin', 'total_gb' => 832, 'used_gb' => 305],
                    ['name' => 'ceph-vm', 'type' => 'rbd', 'total_gb' => 11500, 'used_gb' => 4120],
                    ['name' => 'nas-iso', 'type' => 'nfs', 'total_gb' => 0, 'used_gb' => 0],
                ],
                'gaeste' => [
                    [111, 'VM-SQL01', 'qemu', 'running', 8, 64, 'Windows Server 2025 Standard'],
                    [112, 'VM-App01', 'qemu', 'running', 4, 16, 'Windows Server 2025 Standard'],
                    // No core limit: a container may use all of the host's.
                    [211, 'CT-Grafana', 'lxc', 'running', null, 4, 'Debian 13'],
                ],
            ],
            'PVE-03' => [
                'cpu' => 'Intel Xeon Silver 4310 @ 2.10GHz (24 Kerne)', 'cores' => 24, 'memory_gb' => 128,
                'storages' => [
                    ['name' => 'local', 'type' => 'dir', 'total_gb' => 94, 'used_gb' => 22],
                    ['name' => 'local-lvm', 'type' => 'lvmthin', 'total_gb' => 832, 'used_gb' => 790],
                    ['name' => 'ceph-vm', 'type' => 'rbd', 'total_gb' => 11500, 'used_gb' => 4120],
                ],
                'gaeste' => [
                    [121, 'VM-Fileserver', 'qemu', 'running', 4, 16, 'Windows Server 2022 Standard'],
                    [122, 'VM-Backup', 'qemu', 'stopped', 4, 16, 'Windows Server 2022 Standard'],
                    [221, 'CT-Pihole', 'lxc', 'running', 1, 0.5, 'Debian 13'],
                ],
            ],
        ];

        // Load of the running guests when the agent ran: CPU in percent of
        // their own cores, memory in use. One amber (VM-Exchange, 86 % of its
        // memory) and one red (VM-SQL01, 91 %).
        $last = [
            101 => [6.5, 6.1], 102 => [38.2, 27.4], 201 => [3.2, 0.6],
            111 => [71.8, 58.3], 112 => [22.4, 11.8], 211 => [9.8, 3.1],
            121 => [4.1, 9.7], 221 => [0.8, 0.2],
        ];

        // Root disk of the running containers (used, size) - Proxmox sees
        // inside a container, not inside a VM. CT-Grafana nearly full.
        $platte = [201 => [3.1, 8], 211 => [18.6, 20], 221 => [1.4, 4]];

        foreach ($knoten as $name => $k) {
            $server = Server::where('name', $name)->whereNotNull('cluster_id')->first();
            if (! $server) {
                continue;
            }

            $server->update([
                'agent_details' => [
                    'pve_version' => '9.2.3',
                    'kernel' => '7.0.6-2-pve',
                    'cpu' => $k['cpu'],
                    'cores' => $k['cores'],
                    'memory_gb' => $k['memory_gb'],
                    'storages' => $k['storages'],
                ],
                'agent_reported_at' => now()->subMinutes(12),
            ]);

            foreach ($k['gaeste'] as [$vmid, $gast, $typ, $status, $kerne, $ram, $os]) {
                VM::updateOrCreate(
                    ['customer_id' => $server->customer_id, 'agent_identifier' => mb_strtolower($name).'/'.$typ.'/'.$vmid],
                    [
                        'site_id' => $server->site_id,
                        'server_id' => $server->id,
                        'name' => $gast,
                        'operating_system_id' => OperatingSystem::where('name', $os)->value('id'),
                        'agent_details' => array_filter([
                            'vmid' => $vmid, 'type' => $typ, 'status' => $status,
                            'cores' => $kerne, 'memory_gb' => $ram,
                            'cpu_pct' => $last[$vmid][0] ?? null,
                            'memory_used_gb' => $last[$vmid][1] ?? null,
                            'disk_used_gb' => $platte[$vmid][0] ?? null,
                            'disk_gb' => $platte[$vmid][1] ?? null,
                        ], fn ($wert) => $wert !== null),
                        'agent_reported_at' => now()->subMinutes(12),
                    ]
                );
            }
        }
    }
}
