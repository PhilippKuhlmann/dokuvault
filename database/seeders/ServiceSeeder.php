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
 * By name, ignoring case: a service someone already created ("docker",
 * "Fileserver") keeps its name, colour and text; only a missing
 * description is filled in.
 * Colours of the product where there is one (Docker blue, Proxmox orange,
 * VMware grey, SQL Server red, Veeam green, nginx green); the Windows roles
 * in the Microsoft logo and palette colours, so they are not all the same
 * blue.
 */
class ServiceSeeder extends Seeder
{
    /** name => [colour, description] */
    public const KATALOG = [
        'AD' => ['#0078D4', 'Active Directory: Anmeldung, Benutzer und Gruppenrichtlinien'],
        'DNS' => ['#7FBA00', 'Namensauflösung im Netz'],
        'DHCP' => ['#FFB900', 'Vergibt Adressen im Netz'],
        'FS' => ['#00A4EF', 'Fileserver: Dateifreigaben'],
        'DFS' => ['#0063B1', 'Verteiltes Dateisystem über mehrere Server oder Standorte'],
        'RDS' => ['#F25022', 'Remotedesktopdienste: Terminalserver für Remote-Arbeitsplätze'],
        'SQL' => ['#CC2927', 'Datenbankserver'],
        'Print' => ['#737373', 'Druckserver: Warteschlangen und Treiber'],
        'Backup' => ['#00B336', 'Sicherung – hier laufen die Aufträge'],
        'Monitoring' => ['#7E57C2', 'Überwachung von Systemen und Diensten'],
        'Docker' => ['#2496ED', 'Container-Laufzeitumgebung'],
        'Web' => ['#009639', 'Webserver (nginx, Apache)'],
        'IIS' => ['#D83B01', 'Microsoft-Webserver (Internet Information Services)'],
        'PKI' => ['#5C2D91', 'Zertifizierungsstelle: stellt Zertifikate aus'],
        'WSUS' => ['#00B294', 'Windows-Updates zentral verteilen'],
        'VPN' => ['#88171A', 'Zugang von außen ins Netz'],
        'Hyper-V' => ['#00BCF2', 'Virtualisierung mit Hyper-V – auf diesem Host laufen VMs'],
        'ESXi' => ['#607078', 'Virtualisierung mit VMware ESXi – auf diesem Host laufen VMs'],
        'PVE' => ['#E57000', 'Proxmox Virtual Environment – auf diesem Host laufen VMs und Container'],
        'PBS' => ['#F29400', 'Proxmox Backup Server – Sicherungsziel'],
        'PMG' => ['#C25400', 'Proxmox Mail Gateway – Spam- und Virenfilter für E-Mail'],
    ];

    public function run(): void
    {
        $vorhanden = Service::all()->keyBy(fn ($dienst) => mb_strtolower($dienst->name));

        foreach (self::KATALOG as $name => [$farbe, $beschreibung]) {
            $dienst = $vorhanden[mb_strtolower($name)] ?? null;

            if (! $dienst) {
                Service::create(['name' => $name, 'color' => $farbe, 'description' => $beschreibung]);

                continue;
            }

            // Created by hand before the catalog existed: colour and name
            // stay, only a missing description is filled in - the tile shows
            // it on hover, and an empty one explains nothing.
            if (blank($dienst->description)) {
                $dienst->update(['description' => $beschreibung]);
            }
        }
    }
}
