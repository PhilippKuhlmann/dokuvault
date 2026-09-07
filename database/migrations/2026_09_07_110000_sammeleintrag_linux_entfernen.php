<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Raeumt den Katalogeintrag "Linux" weg.
 *
 * Er stammt nicht aus dem Seeder, sondern vom Proxmox-Agenten: der meldete
 * den ostype ('l26'), daraus wurde per firstOrCreate "Linux". Ein Eintrag
 * ohne Support-Ende und ohne Aussage - und genau danach wird das Feld
 * gelesen. Der Agent legt inzwischen nichts mehr an; was er hinterlassen
 * hat, gehoert mit weg.
 *
 * Nur, wenn nichts darauf zeigt. Wer den Eintrag von Hand vergeben hat,
 * hat sich etwas dabei gedacht - dann bleibt er stehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('operating_systems')->where('name', 'Linux')->value('id');

        if (! $id) {
            return;
        }

        foreach (['servers', 'vms', 'computers', 'license_windows'] as $tabelle) {
            if (DB::table($tabelle)->where('operating_system_id', $id)->exists()) {
                return;
            }
        }

        DB::table('operating_systems')->where('id', $id)->delete();
    }

    public function down(): void
    {
        DB::table('operating_systems')->insertOrIgnore([
            'name' => 'Linux',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
