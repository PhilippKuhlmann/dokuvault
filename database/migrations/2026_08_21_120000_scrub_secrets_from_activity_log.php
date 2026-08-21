<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kennwörter aus bestehenden Protokolleinträgen entfernen.
 *
 * Die Ausschlussliste nannte die Accessor-Methoden
 * ("uscpin", "cloudBackupPassword"), die Spalten heißen aber "usc_pin" und
 * "cloud_backup_password"; pppoe_password stand gar nicht darin. Damit hat
 * spatie/activitylog diese drei Felder mitgeschrieben - im Klartext, alten und
 * neuen Wert. Gemessen in der Entwicklungsdatenbank: 16 betroffene Einträge.
 *
 * Der Eintrag selbst bleibt stehen: Dass jemand etwas geändert hat, ist die
 * Information, um die es geht. Nur der Wert verschwindet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        $geheim = config('custom.secret_columns');
        $bereinigt = 0;

        DB::table('activity_log')
            ->whereNotNull('properties')
            ->orderBy('id')
            ->chunkById(500, function ($eintraege) use ($geheim, &$bereinigt) {
                foreach ($eintraege as $eintrag) {
                    $eigenschaften = json_decode($eintrag->properties ?? '', true);

                    if (! is_array($eigenschaften)) {
                        continue;
                    }

                    $vorher = $eigenschaften;

                    foreach (['attributes', 'old'] as $block) {
                        if (! isset($eigenschaften[$block]) || ! is_array($eigenschaften[$block])) {
                            continue;
                        }

                        foreach ($geheim as $spalte) {
                            unset($eigenschaften[$block][$spalte]);
                        }
                    }

                    if ($eigenschaften === $vorher) {
                        continue;
                    }

                    DB::table('activity_log')->where('id', $eintrag->id)->update([
                        'properties' => json_encode($eigenschaften, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);

                    $bereinigt++;
                }
            });

        if ($bereinigt > 0) {
            info("Protokoll bereinigt: {$bereinigt} Einträge enthielten Kennwörter im Klartext.");
        }
    }

    /**
     * Kein Weg zurück - und das ist der Sinn der Sache. Die Werte sind fort.
     */
    public function down(): void {}
};
