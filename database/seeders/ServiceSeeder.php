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
    /** name => [colour, description]; the description is the long form of the name. */
    public const KATALOG = [
        'AD' => ['#0078D4', 'Active Directory'],
        'DNS' => ['#7FBA00', 'Domain Name System'],
        'DHCP' => ['#FFB900', 'Dynamic Host Configuration Protocol'],
        'FS' => ['#00A4EF', 'Fileserver'],
        'DFS' => ['#0063B1', 'Distributed File System'],
        'RDS' => ['#F25022', 'Remote Desktop Services'],
        'SQL' => ['#CC2927', 'Structured Query Language'],
        'Print' => ['#737373', 'Druckserver'],
        'Backup' => ['#00B336', 'Datensicherung'],
        'Monitoring' => ['#7E57C2', 'Überwachung'],
        'Docker' => ['#2496ED', 'Docker'],
        'Web' => ['#009639', 'Webserver'],
        'IIS' => ['#D83B01', 'Internet Information Services'],
        'PKI' => ['#5C2D91', 'Public Key Infrastructure'],
        'WSUS' => ['#00B294', 'Windows Server Update Services'],
        'VPN' => ['#88171A', 'Virtual Private Network'],
        'Hyper-V' => ['#00BCF2', 'Microsoft Hyper-V'],
        'ESXi' => ['#607078', 'VMware ESXi'],
        'PVE' => ['#E57000', 'Proxmox Virtual Environment'],
        'PBS' => ['#F29400', 'Proxmox Backup Server'],
        'PMG' => ['#C25400', 'Proxmox Mail Gateway'],
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
