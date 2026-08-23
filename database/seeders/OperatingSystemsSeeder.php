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
            'Debian 13',
            'Debian 12',
            'Debian 11',
            'Debian 10',
            'Debian 9',
            'Rocky Linux 9',
            'AlmaLinux 9',
            'openSUSE Leap 15',

            // Virtualisierung
            // Proxmox VE einzeln je Hauptversion, nicht als ein Sammel-Eintrag:
            // Version 7/8/9 haben unterschiedliche Support-Enden - ein Datum
            // fuer "Proxmox Virtual Environment" waere fuer zwei der drei
            // Versionen falsch.
            'Proxmox VE 9',
            'Proxmox VE 8',
            'Proxmox VE 7',
            // Eigene Versionsnummern, nicht die von Proxmox VE: Backup Server
            // 2/3 fallen zwar mit VE 7/8 zusammen (dasselbe Debian darunter),
            // Backup Server 1 hat aber kein VE-Gegenstueck im Katalog.
            'Proxmox Backup Server 4',
            'Proxmox Backup Server 3',
            'Proxmox Backup Server 2',
            'Proxmox Backup Server 1',
            'Proxmox Mail Gateway',
            'VMware ESXi 8',
            'VMware ESXi 7',
            'VMware ESXi 6',

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
            'Windows Server 2008' => '2020-01-14',
            'Windows Server 2012 R2' => '2023-10-10',
            'Windows Server 2012' => '2023-10-10',
            'Windows Server 2016' => '2027-01-12',
            'Windows Server 2019' => '2029-01-09',
            'Windows Server 2022' => '2031-10-14',
            'Windows Server 2025' => '2034-10-10',
            'Windows 10' => '2025-10-14',
            'Windows 8.1' => '2023-01-10',
            'Windows 7' => '2020-01-14',
            'Windows XP' => '2014-04-08',
            'Debian 9' => '2022-07-01',
            'Debian 10' => '2024-06-30',
            'Debian 11' => '2026-08-31',
            'Debian 12' => '2028-06-30',
            'Debian 13' => '2030-06-30',
            // "Ubuntu 20.04" statt "Ubuntu Server 20.04" traf nie - der
            // Katalogname faengt mit "Ubuntu Server" an, str_starts_with()
            // verlangt eine exakte Uebereinstimmung von Anfang an.
            'Ubuntu Server 20.04' => '2025-05-31',
            'Ubuntu Server 22.04' => '2027-04-01',
            'Ubuntu Server 24.04' => '2029-05-31',
            'Rocky Linux 9' => '2032-05-31',
            'AlmaLinux 9' => '2032-05-31',
            'openSUSE Leap 15' => '2026-04-30',
            // ESXi 6.5 und 6.7 endeten beide am selben Tag, 6.0 schon frueher
            // (2020-03-12) - "VMware ESXi 6" ohne Unterversion nimmt das
            // spaetere, haeufigere Datum der 6.5er/6.7er-Linie.
            'VMware ESXi 6' => '2022-10-15',
            'VMware ESXi 7' => '2025-10-02',
            'VMware ESXi 8' => '2027-10-11',
            'Proxmox VE 7' => '2024-07-31',
            'Proxmox VE 8' => '2026-08-31',
            'Proxmox Backup Server 1' => '2022-09-30',
            'Proxmox Backup Server 2' => '2024-07-31',
            'Proxmox Backup Server 3' => '2026-08-31',
            'macOS Ventura' => '2025-09-15',
            'macOS Sonoma' => '2026-09-15',
            'CentOS 7' => '2024-06-30',
        ];

        // Kein Datum fuer: Windows 11 (laesst sich nicht einer Version
        // zuordnen - der Katalog fuehrt keine Build-Nummer wie "24H2", und
        // ohne die ist "Windows 11 endet am ..." schlicht falsch, solange
        // Microsoft laufend neue Versionen nachschiebt), Proxmox VE 9 und
        // Backup Server 4 (Termine von Proxmox noch nicht angekuendigt),
        // Proxmox Mail Gateway/TrueNAS (kein fester, produktweiter Termin,
        // laufend aktualisiert), Synology/QNAP (haengt vom NAS-Modell ab,
        // nicht vom Betriebssystem) und Rangee OS (kein oeffentlicher
        // Support-Zeitplan bekannt).

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
