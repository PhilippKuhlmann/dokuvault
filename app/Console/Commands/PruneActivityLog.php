<?php

namespace App\Console\Commands;

use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Delete activity entries once the configured deadline has passed.
 *
 * Without a deadline nothing happens - that is the default. A log that
 * empties itself unasked would be no log any more.
 *
 * The previous passwords go with them: they are what an entry about a
 * password change has to show. If the history stayed, the old values would
 * be around longer than the entry pointing at them.
 */
class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune {--preview : Nur zeigen, was geloescht wuerde}';

    protected $description = 'Loescht Protokolleintraege und die daran haengenden Kennwoerter nach der eingestellten Frist';

    public function handle(): int
    {
        $tage = Setting::activityDays();

        if ($tage < 1) {
            $this->info('Keine Frist eingestellt — das Protokoll bleibt unangetastet.');

            return self::SUCCESS;
        }

        $grenze = now()->subDays($tage);

        $eintraege = Activity::where('created_at', '<', $grenze);
        $kennwoerter = PasswordHistory::where('created_at', '<', $grenze);

        $anzahlEintraege = $eintraege->count();
        $anzahlKennwoerter = $kennwoerter->count();

        if ($this->option('preview')) {
            $this->info($anzahlEintraege.' Einträge und '.$anzahlKennwoerter.' bisherige Kennwörter wären älter als '.$tage.' Tage.');

            return self::SUCCESS;
        }

        // The passwords first: if the run broke off in between, better the
        // values gone than the entries telling of them.
        $kennwoerter->delete();
        $eintraege->delete();

        $this->info($anzahlEintraege.' Protokolleinträge und '.$anzahlKennwoerter.' bisherige Kennwörter gelöscht (älter als '.$tage.' Tage).');

        return self::SUCCESS;
    }
}
