<?php

use App\Models\OperatingSystem;
use Illuminate\Database\Migrations\Migration;

/**
 * Ergaenzt fehlende Support-Enden im Betriebssystem-Katalog bestehender
 * Installationen (frische Installationen bekommen sie schon vom Seeder),
 * legt Proxmox VE und Proxmox Backup Server einzeln je Hauptversion an
 * (statt einem Sammel-Eintrag - die Versionen haben unterschiedliche
 * Support-Enden) und ergaenzt die fehlenden Katalogeintraege fuer
 * Debian 13 "Trixie" und VMware ESXi 6.
 *
 * Die alten Sammel-Eintraege "Proxmox Virtual Environment" und "Proxmox
 * Backup Server" werden weich geloescht (Papierkorb) - Geraete, die noch
 * darauf zeigen, verlieren dadurch nicht die Zuordnung, siehe die
 * "Betriebssystem im Papierkorb"-Faelle, die die Anwendung schon anderswo
 * abfaengt (z. B. VmWithoutOsTest).
 *
 * Die Ubuntu-Zeilen ("Ubuntu Server 20.04 LTS" etc.) waren nie befuellt: Der
 * Seeder verglich mit dem Praefix "Ubuntu 20.04" statt "Ubuntu Server 20.04" -
 * str_starts_with() traf deshalb nie.
 *
 * Nur NULL-Werte werden befuellt - ein von Hand eingetragenes Datum bleibt
 * unangetastet. Quellen: Microsoft-Lifecycle, endoflife.date, Broadcom/
 * VMware- und Distributions-Lifecycle-Seiten (Stand 2026-08-22).
 */
return new class extends Migration
{
    public function up(): void
    {
        $eol = [
            'Windows Server 2008' => '2020-01-14',
            'Windows 8.1' => '2023-01-10',
            'Windows 7' => '2020-01-14',
            'Windows XP' => '2014-04-08',
            'Debian 9' => '2022-07-01',
            'Debian 10' => '2024-06-30',
            'Ubuntu Server 20.04' => '2025-05-31',
            'Ubuntu Server 22.04' => '2027-04-01',
            'Ubuntu Server 24.04' => '2029-05-31',
            'Rocky Linux 9' => '2032-05-31',
            'AlmaLinux 9' => '2032-05-31',
            'openSUSE Leap 15' => '2026-04-30',
            'VMware ESXi 6' => '2022-10-15',
            'VMware ESXi 7' => '2025-10-02',
            'VMware ESXi 8' => '2027-10-11',
            'macOS Ventura' => '2025-09-15',
            'macOS Sonoma' => '2026-09-15',
        ];

        foreach ($eol as $produkt => $ende) {
            OperatingSystem::where('name', 'like', $produkt.'%')
                ->whereNull('eol_date')
                ->update(['eol_date' => $ende]);
        }

        // Neu, gab es unter den alten Sammel-Eintraegen noch nicht.
        OperatingSystem::firstOrCreate(['name' => 'Proxmox VE 9']);
        OperatingSystem::firstOrCreate(['name' => 'Proxmox VE 8'], ['eol_date' => '2026-08-31']);
        OperatingSystem::firstOrCreate(['name' => 'Proxmox VE 7'], ['eol_date' => '2024-07-31']);
        OperatingSystem::firstOrCreate(['name' => 'Proxmox Backup Server 4']);
        OperatingSystem::firstOrCreate(['name' => 'Proxmox Backup Server 3'], ['eol_date' => '2026-08-31']);
        OperatingSystem::firstOrCreate(['name' => 'Proxmox Backup Server 2'], ['eol_date' => '2024-07-31']);
        OperatingSystem::firstOrCreate(['name' => 'Proxmox Backup Server 1'], ['eol_date' => '2022-09-30']);

        // Fehlte im Katalog komplett, nicht nur das Datum.
        OperatingSystem::firstOrCreate(['name' => 'Debian 13'], ['eol_date' => '2030-06-30']);

        // Durch die versionierten Eintraege oben ersetzt - abgeloest statt
        // hart geloescht, damit ein Geraet, das noch darauf zeigt, seine
        // Zuordnung behaelt.
        OperatingSystem::where('name', 'Proxmox Virtual Environment')->delete();
        OperatingSystem::where('name', 'Proxmox Backup Server')->delete();
    }

    public function down(): void
    {
        // Absichtlich kein Rollback: Welche Zeilen vorher NULL waren, laesst
        // sich nachtraeglich nicht mehr von echten Werten unterscheiden.
    }
};
