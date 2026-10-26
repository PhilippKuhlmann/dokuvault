<?php

namespace App\Console\Commands;

use App\Support\InitialSetup;
use Illuminate\Console\Command;

/**
 * A new setup code for /setup - when the one from the first start is lost,
 * or the database was set up without the production seeder.
 */
class SetupCode extends Command
{
    protected $signature = 'setup:code';

    protected $description = 'Erzeugt einen neuen Einrichtungscode für den ersten Administrator';

    public function handle(): int
    {
        if (InitialSetup::hasAdmin()) {
            $this->error('DokuVault ist bereits eingerichtet - es gibt einen Administrator.');

            return self::FAILURE;
        }

        self::announce($this, InitialSetup::newCode());

        return self::SUCCESS;
    }

    /** Shared with the production seeder, so both print the same. */
    public static function announce(Command $command, ?string $code): void
    {
        $command->newLine();
        $command->warn('  Ersteinrichtung: Den ersten Administrator unter '.url('/setup').' anlegen.');
        $command->warn($code !== null
            ? '  Einrichtungscode: '.$code
            : '  Einrichtungscode: der Wert von SETUP_TOKEN aus der .env');
        $command->newLine();
    }
}
