<?php

namespace App\Console\Commands;

use App\Support\Directory\DirectorySync;
use App\Support\Directory\DirectoryUnavailable;
use App\Support\Directory\LdapConfig;
use Illuminate\Console\Command;

/**
 * Hourly: directory accounts follow AD - role, name, mail; access ends for
 * whoever was disabled or left every mapped group. See DirectorySync.
 */
class SyncDirectory extends Command
{
    protected $signature = 'directory:sync';

    protected $description = 'Matches directory accounts against Active Directory and ends access where AD no longer grants it';

    public function handle(DirectorySync $abgleich): int
    {
        if (! LdapConfig::enabled()) {
            $this->line('AD-Anmeldung ist aus - nichts zu tun.');

            return self::SUCCESS;
        }

        try {
            $ergebnis = $abgleich->run();
        } catch (DirectoryUnavailable $fehler) {
            // Nobody is locked out over an outage; the next hour tries again.
            report($fehler);
            $this->warn('AD nicht erreichbar, nichts geändert: '.$fehler->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%d geprüft, %d aktualisiert, %d Zugang beendet.',
            $ergebnis['geprueft'], $ergebnis['aktualisiert'], $ergebnis['beendet']));

        return self::SUCCESS;
    }
}
