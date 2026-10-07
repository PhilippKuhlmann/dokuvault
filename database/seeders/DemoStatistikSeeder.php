<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * 90 days of history for Statistik -> System and Datenwachstum.
 *
 * The nightly snapshot (statistik:schnappschuss) only starts the history -
 * a fresh demo showed "the history starts now" under every chart. Here
 * today's snapshot is taken and the days before are derived from it: the
 * documentation grew to today's state, steadily with the odd jump, as it
 * does when a customer is taken on.
 *
 *   php artisan db:seed --class=DemoStatistikSeeder
 */
class DemoStatistikSeeder extends Seeder
{
    private const TAGE = 90;

    public function run(): void
    {
        Artisan::call('statistik:schnappschuss');

        $heute = now()->toDateString();
        $stand = DB::table('statistik_werte')->where('datum', $heute)->get();

        $zeilen = [];
        foreach ($stand as $wert) {
            for ($tag = 1; $tag < self::TAGE; $tag++) {
                $zeilen[] = [
                    'datum' => now()->subDays($tag)->toDateString(),
                    'kennzahl' => $wert->kennzahl,
                    'customer_id' => $wert->customer_id,
                    'wert' => $this->frueher($wert->kennzahl, (int) $wert->wert, $tag),
                ];
            }
        }

        foreach (array_chunk($zeilen, 1000) as $teil) {
            DB::table('statistik_werte')->upsert($teil, ['datum', 'kennzahl', 'customer_id'], ['wert']);
        }
    }

    /** The value $tag days ago, on the way to today's $jetzt. */
    private function frueher(string $kennzahl, int $jetzt, int $tag): int
    {
        // Disk size stays; free space was a little larger.
        if ($kennzahl === 'disk_gesamt_bytes') {
            return $jetzt;
        }
        if ($kennzahl === 'disk_frei_bytes') {
            return (int) round($jetzt * (1 + 0.04 * $tag / self::TAGE));
        }

        // 60 % of today's state three months ago; a step around day 45,
        // when the AD of a new customer came in through the agent.
        $anteil = 1 - 0.4 * $tag / self::TAGE - ($tag > 45 ? 0.08 : 0);

        return max(0, (int) floor($jetzt * $anteil));
    }
}
