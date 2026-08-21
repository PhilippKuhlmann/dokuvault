<?php

namespace App\Console\Commands;

use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Protokolleintraege loeschen, sobald die eingestellte Frist abgelaufen ist.
 *
 * Ohne Frist passiert nichts - das ist die Vorgabe. Ein Protokoll, das sich
 * ungefragt selbst leert, waere kein Protokoll mehr.
 *
 * Die bisherigen Kennwoerter gehen mit: Sie sind das, was ein Eintrag ueber
 * eine Kennwortaenderung zu zeigen hat. Bliebe die Historie stehen, waeren die
 * alten Werte laenger da als der Eintrag, der auf sie verweist.
 */
class ProtokollAufraeumen extends Command
{
    protected $signature = 'protokoll:aufraeumen {--vorschau : Nur zeigen, was geloescht wuerde}';

    protected $description = 'Loescht Protokolleintraege und die daran haengenden Kennwoerter nach der eingestellten Frist';

    public function handle(): int
    {
        $tage = Setting::protokollTage();

        if ($tage < 1) {
            $this->info('Keine Frist eingestellt — das Protokoll bleibt unangetastet.');

            return self::SUCCESS;
        }

        $grenze = now()->subDays($tage);

        $eintraege = Activity::where('created_at', '<', $grenze);
        $kennwoerter = PasswordHistory::where('created_at', '<', $grenze);

        $anzahlEintraege = $eintraege->count();
        $anzahlKennwoerter = $kennwoerter->count();

        if ($this->option('vorschau')) {
            $this->info($anzahlEintraege.' Einträge und '.$anzahlKennwoerter.' bisherige Kennwörter wären älter als '.$tage.' Tage.');

            return self::SUCCESS;
        }

        // Die Kennwoerter zuerst: Braeche der Lauf dazwischen ab, waeren lieber
        // die Werte weg als die Eintraege, die von ihnen erzaehlen.
        $kennwoerter->delete();
        $eintraege->delete();

        $this->info($anzahlEintraege.' Protokolleinträge und '.$anzahlKennwoerter.' bisherige Kennwörter gelöscht (älter als '.$tage.' Tage).');

        return self::SUCCESS;
    }
}
