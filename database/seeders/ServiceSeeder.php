<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * The standard service catalog of a new installation.
 *
 * Without it a fresh production had no services at all - the agents only
 * assign services that exist in the catalog (roles of a Windows server,
 * systemd units of a Linux server, Proxmox tags), so nothing was assigned.
 *
 * firstOrCreate by name, ignoring case: a service someone already created
 * ("docker", "Fileserver") is kept as it is, with its colour and text.
 * Colour by role, as in the local example data: what fails, fails with
 * different weight.
 */
class ServiceSeeder extends Seeder
{
    /** name => [colour, description] */
    public const KATALOG = [
        'AD' => ['#b91c1c', 'Active Directory: Anmeldung, Benutzer und Gruppenrichtlinien'],
        'DNS' => ['#dc2626', 'Namensauflösung im Netz'],
        'DHCP' => ['#ef4444', 'Vergibt Adressen im Netz'],
        'FS' => ['#3391f0', 'Fileserver: Dateifreigaben'],
        'DFS' => ['#8ecdff', 'Verteiltes Dateisystem über mehrere Server oder Standorte'],
        'RDS' => ['#b45309', 'Remotedesktopdienste: Terminalserver für Remote-Arbeitsplätze'],
        'SQL' => ['#1f73d6', 'Datenbankserver'],
        'Print' => ['#f59e0b', 'Druckserver: Warteschlangen und Treiber'],
        'Backup' => ['#15803d', 'Sicherung – hier laufen die Aufträge'],
        'Monitoring' => ['#0891b2', 'Überwachung von Systemen und Diensten'],
        'Docker' => ['#0f766e', 'Container-Laufzeitumgebung'],
        'Web' => ['#14b8a6', 'Webserver (nginx, Apache)'],
        'IIS' => ['#0d9488', 'Microsoft-Webserver (Internet Information Services)'],
        'PKI' => ['#be185d', 'Zertifizierungsstelle: stellt Zertifikate aus'],
        'WSUS' => ['#2563eb', 'Windows-Updates zentral verteilen'],
        'VPN' => ['#4f46e5', 'Zugang von außen ins Netz'],
        'Hyper-V' => ['#7c3aed', 'Virtualisierung mit Hyper-V – auf diesem Host laufen VMs'],
        'ESXi' => ['#6d28d9', 'Virtualisierung mit VMware ESXi – auf diesem Host laufen VMs'],
        'PVE' => ['#ea580c', 'Proxmox Virtual Environment – auf diesem Host laufen VMs und Container'],
        'PBS' => ['#c2410c', 'Proxmox Backup Server – Sicherungsziel'],
        'PMG' => ['#9a3412', 'Proxmox Mail Gateway – Spam- und Virenfilter für E-Mail'],
    ];

    public function run(): void
    {
        $vorhanden = Service::pluck('name')->map(fn ($name) => mb_strtolower($name))->all();

        foreach (self::KATALOG as $name => [$farbe, $beschreibung]) {
            if (in_array(mb_strtolower($name), $vorhanden, true)) {
                continue;
            }
            Service::create(['name' => $name, 'color' => $farbe, 'description' => $beschreibung]);
        }
    }
}
