<?php

namespace Database\Seeders;

use App\Models\OperatingSystem;
use Illuminate\Database\Seeder;

class OperatingSystemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $array = [
            // Windows Server
            'Windows Server 2025 Standard',
            'Windows Server 2025 Datacenter',
            'Windows Server 2022 Standard',
            'Windows Server 2022 Datacenter',
            'Windows Server 2019 Standard',
            'Windows Server 2019 Datacenter',
            'Windows Server 2016 Standard',
            'Windows Server 2016 Datacenter',
            'Windows Server 2012 R2 Standard',
            'Windows Server 2012 R2 Datacenter',
            'Windows Server 2012 Standard',
            'Windows Server 2012 Datacenter',
            'Windows Server 2008 R2 Standard',
            'Windows Server 2008 R2 Datacenter',
            'Windows Server 2008 Standard',
            'Windows Server 2008 Datacenter',

            // Windows Client
            'Windows 11 Pro',
            'Windows 11 Home',
            'Windows 11 Enterprise',
            'Windows 10 Pro',
            'Windows 10 Home',
            'Windows 10 Enterprise',
            'Windows 10 Enterprise LTSC',
            'Windows 8.1 Pro',
            'Windows 7 Pro',
            'Windows 7 Home',
            'Windows XP Pro',
            'Windows XP Home',

            // Linux
            'Ubuntu Server 24.04 LTS',
            'Ubuntu Server 22.04 LTS',
            'Ubuntu Server 20.04 LTS',
            'Debian 12',
            'Debian 11',
            'Debian 10',
            'Debian 9',
            'Rocky Linux 9',
            'AlmaLinux 9',
            'openSUSE Leap 15',

            // Virtualisierung
            'Proxmox Virtual Environment',
            'Proxmox Backup Server',
            'Proxmox Mail Gateway',
            'VMware ESXi 8',
            'VMware ESXi 7',

            // NAS / Appliance
            'Synology DSM 7',
            'TrueNAS',
            'QNAP QTS',

            // Sonstige
            'Rangee OS',
            'macOS Sonoma',
            'macOS Ventura',
        ];

        // Offizielle Support-Enden von Microsoft und den Distributionen. Das
        // Datum haengt am Produkt, nicht an der Edition - Standard und
        // Datacenter enden am selben Tag.
        $eol = [
            'Windows Server 2008 R2' => '2020-01-14',
            'Windows Server 2012 R2' => '2023-10-10',
            'Windows Server 2012' => '2023-10-10',
            'Windows Server 2016' => '2027-01-12',
            'Windows Server 2019' => '2029-01-09',
            'Windows Server 2022' => '2031-10-14',
            'Windows Server 2025' => '2034-10-10',
            'Windows 10' => '2025-10-14',
            'Debian 11' => '2026-08-31',
            'Debian 12' => '2028-06-30',
            'Ubuntu 20.04' => '2025-05-31',
            'Ubuntu 22.04' => '2027-06-01',
            'CentOS 7' => '2024-06-30',
        ];

        foreach ($array as $a) {
            $datum = null;
            foreach ($eol as $produkt => $ende) {
                if (str_starts_with($a, $produkt)) {
                    $datum = $ende;
                    break;
                }
            }

            OperatingSystem::create([
                'name' => $a,
                'eol_date' => $datum,
            ]);
        }

    }
}
