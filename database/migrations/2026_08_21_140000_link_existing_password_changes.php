<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bestehende "Kennwort geaendert"-Eintraege mit ihrer Historie verbinden.
 *
 * Die Historie-Tabelle und der Protokolleintrag sind in derselben Sitzung
 * entstanden, aber nicht in derselben Stunde: Zwischen beiden gab es Eintraege,
 * zu denen ein alter Wert existiert, auf den der Protokolleintrag nicht
 * verweist. Im Protokoll fehlte dort der Knopf, obwohl der Wert dalag.
 *
 * Zugeordnet wird ueber Objekt, Feld und Zeitnaehe - der Historie-Eintrag
 * entsteht unmittelbar vor dem Protokolleintrag.
 */
return new class extends Migration
{
    /** Wie weit die beiden Zeitstempel auseinanderliegen duerfen. */
    private const SEKUNDEN = 10;

    public function up(): void
    {
        if (! Schema::hasTable('activity_log') || ! Schema::hasTable('password_histories')) {
            return;
        }

        $verbunden = 0;

        DB::table('activity_log')
            ->where('event', 'password_changed')
            ->orderBy('id')
            ->chunkById(200, function ($eintraege) use (&$verbunden) {
                foreach ($eintraege as $eintrag) {
                    $eigenschaften = json_decode($eintrag->properties ?? '', true);

                    if (! is_array($eigenschaften) || ! empty($eigenschaften['verlauf_ids'])) {
                        continue;
                    }

                    $zeitpunkt = $eintrag->created_at;

                    $ids = DB::table('password_histories')
                        ->where('subject_type', $eintrag->subject_type)
                        ->where('subject_id', $eintrag->subject_id)
                        ->whereIn('field', $eigenschaften['felder'] ?? [])
                        ->whereBetween('created_at', [
                            date('Y-m-d H:i:s', strtotime($zeitpunkt) - self::SEKUNDEN),
                            date('Y-m-d H:i:s', strtotime($zeitpunkt) + self::SEKUNDEN),
                        ])
                        ->pluck('id')
                        ->all();

                    if ($ids === []) {
                        continue;
                    }

                    $eigenschaften['verlauf_ids'] = $ids;

                    DB::table('activity_log')->where('id', $eintrag->id)->update([
                        'properties' => json_encode($eigenschaften, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);

                    $verbunden++;
                }
            });

        if ($verbunden > 0) {
            info("Protokoll: {$verbunden} Kennwortänderungen mit ihrer Historie verbunden.");
        }
    }

    /**
     * Der Verweis ist kein Wert - ihn zu entfernen brächte niemandem etwas.
     */
    public function down(): void {}
};
