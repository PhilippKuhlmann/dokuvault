<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\SystemWerte;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes the figures of the day for Admin -> Statistik: how many objects
 * per type and customer (Datenwachstum), and the size of database, files,
 * backups and disk (System). Run nightly; running it again on the same day
 * overwrites that day.
 */
class StatistikSchnappschuss extends Command
{
    protected $signature = 'statistik:schnappschuss';

    protected $description = 'Kennzahlen des Tages fuer die Statistik speichern';

    /** System values, stored with customer_id 0. */
    public const SYSTEM = ['db_bytes', 'dateien_bytes', 'backup_bytes', 'disk_frei_bytes', 'disk_gesamt_bytes'];

    public function handle(): int
    {
        $heute = now()->toDateString();
        $zeilen = [];

        // Objects per type and customer - the same list as the trash: every
        // type with customer_id. Soft-deleted ones do not count.
        foreach (config('custom.trashables') as $slug => [$klasse]) {
            $tabelle = (new $klasse)->getTable();
            if (! Schema::hasColumn($tabelle, 'customer_id')) {
                continue;
            }
            $jeKunde = $klasse::query()->select('customer_id', DB::raw('COUNT(*) AS n'))->groupBy('customer_id')->pluck('n', 'customer_id');
            foreach ($jeKunde as $kunde => $anzahl) {
                $zeilen[] = ['datum' => $heute, 'kennzahl' => $slug, 'customer_id' => (int) $kunde, 'wert' => (int) $anzahl];
            }
            $zeilen[] = ['datum' => $heute, 'kennzahl' => $slug, 'customer_id' => 0, 'wert' => (int) $jeKunde->sum()];
        }

        $system = [
            'db_bytes' => SystemWerte::datenbankBytes(),
            'dateien_bytes' => SystemWerte::dateienBytes(),
            'backup_bytes' => SystemWerte::backupBytes(),
            'disk_frei_bytes' => SystemWerte::platteFreiBytes(),
            'disk_gesamt_bytes' => SystemWerte::platteGesamtBytes(),
        ];
        foreach ($system as $kennzahl => $wert) {
            if ($wert !== null) {
                $zeilen[] = ['datum' => $heute, 'kennzahl' => $kennzahl, 'customer_id' => 0, 'wert' => $wert];
            }
        }

        foreach (array_chunk($zeilen, 500) as $teil) {
            DB::table('statistik_werte')->upsert($teil, ['datum', 'kennzahl', 'customer_id'], ['wert']);
        }

        // Two years are enough to see growth; older days go.
        DB::table('statistik_werte')->where('datum', '<', now()->subMonths(Setting::statistikMonate())->toDateString())->delete();

        $this->info(count($zeilen).' Kennzahlen fuer '.$heute.' gespeichert.');

        return self::SUCCESS;
    }
}
