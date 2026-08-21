<?php

namespace App\Console\Commands;

use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Vorherige Kennwoerter loeschen, sobald die eingestellte Frist abgelaufen ist.
 *
 * Ohne diesen Lauf waere die Historie ein Endlager: Nach zwei Jahren stuende
 * dort jedes je vergebene Kennwort, und alte Kennwoerter sind selten wirklich
 * alt - sie tauchen anderswo wieder auf.
 */
class KennwortHistorieAufraeumen extends Command
{
    protected $signature = 'kennwoerter:aufraeumen {--vorschau : Nur zeigen, was geloescht wuerde}';

    protected $description = 'Loescht vorherige Kennwoerter, die aelter als die eingestellte Frist sind';

    public function handle(): int
    {
        $tage = Setting::passwortHistorieTage();

        // Bei 0 ist die Historie abgeschaltet. Dann steht dort hoechstens noch
        // etwas aus der Zeit davor - und genau das soll weg.
        $abfrage = $tage < 1
            ? PasswordHistory::query()
            : PasswordHistory::where('created_at', '<', now()->subDays($tage));

        $anzahl = $abfrage->count();

        if ($anzahl === 0) {
            $this->info('Nichts aufzuräumen.');

            return self::SUCCESS;
        }

        if ($this->option('vorschau')) {
            $this->info($anzahl.' Einträge wären zu löschen.');

            return self::SUCCESS;
        }

        $abfrage->delete();
        $this->info($anzahl.' vorherige Kennwörter gelöscht.');

        return self::SUCCESS;
    }
}
