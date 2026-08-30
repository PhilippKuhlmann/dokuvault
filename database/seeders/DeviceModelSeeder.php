<?php

namespace Database\Seeders;

use App\Models\DeviceModel;
use Illuminate\Database\Seeder;

/**
 * Vier Geraetemodelle als Beispiel - jedes mit einer eigenen Zeichnung.
 *
 * Der Zweck ist zu zeigen, was der Modellkatalog kann: Ein Geraet, dessen
 * Hersteller und Modell zu einem Eintrag passen, bekommt dessen Blende - bei
 * jedem Kunden, ohne dass jemand etwas verknuepft.
 *
 * Vier reichen dafuer. Ein vollstaendiger Herstellerkatalog waere Pflegearbeit
 * ohne Ende, und die gezeichnete Blende des Geraetetyps deckt
 * alles Uebrige ab.
 */
class DeviceModelSeeder extends Seeder
{
    /** [Hersteller, Modell, Geraetetyp, Hoeheneinheiten, Zeichnung] */
    private const MODELLE = [
        ['Ubiquiti', 'USW-Pro-24-PoE', 'networkswitch', 1, 'unifi-usw-pro-24-poe'],
        ['Ubiquiti', 'USW-Pro-48-PoE', 'networkswitch', 1, 'unifi-usw-pro-48-poe'],
        ['APC', 'Smart-UPS 1500 RM', 'ups', 2, 'apc-smart-ups-rack'],
        ['Dell', 'PowerEdge R650', 'server', 1, 'dell-poweredge-1he'],
    ];

    public function run(): void
    {
        foreach (self::MODELLE as [$hersteller, $modell, $typ, $he, $zeichnung]) {
            DeviceModel::firstOrCreate(
                [
                    'device_type' => $typ,
                    'manufacturer_key' => DeviceModel::schluessel($hersteller),
                    'model_key' => DeviceModel::schluessel($modell),
                ],
                [
                    'manufacturer' => $hersteller,
                    'model' => $modell,
                    'height_units' => $he,
                    // Alle vier sind halbtief - hinter ihnen bleibt Platz.
                    'full_depth' => false,
                    'drawing' => $zeichnung,
                ]
            );
        }
    }
}
