<?php

namespace App\Console\Commands;

use Database\Seeders\LocalDatabaseSeeder;
use Faker\Factory;
use Illuminate\Console\Command;

/**
 * Resets the demo instance to its as-delivered state.
 *
 * The command deletes the entire database. It is therefore tied to
 * DEMO_MODE and refuses service on every other installation - on a real
 * server an accidental call would be the total loss of the customer
 * documentation.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Setzt die Demo-Datenbank zurueck (nur bei DEMO_MODE=true)';

    public function handle(): int
    {
        if (! config('app.demo')) {
            $this->error('demo:reset ist gesperrt: DEMO_MODE ist nicht aktiv.');
            $this->line('Der Befehl loescht die komplette Datenbank und laeuft nur auf einer Demo-Instanz.');

            return self::FAILURE;
        }

        // The demo data comes from the LocalDatabaseSeeder and needs Faker. It
        // is called directly because DatabaseSeeder branches on APP_ENV and the
        // production seed would otherwise run on the demo (APP_ENV=production).
        if (! class_exists(Factory::class)) {
            $this->error('Faker fehlt - die Demo-Instanz muss mit Dev-Abhaengigkeiten installiert sein.');
            $this->line('composer install (ohne --no-dev)');

            return self::FAILURE;
        }

        // Maintenance mode while the schema is rebuilt. migrate:fresh drops
        // the tables and creates them again; a request falling exactly into
        // those seconds used to meet a half-built schema and ended in a 500 in
        // the log - on 29 October, for one, a failed sign-in whose activity
        // entry tried to write into an activity_log without customer_id. The
        // visitor now sees the same 503 page as during a deploy and is back
        // after a few seconds.
        //
        $this->call('down', ['--retry' => 15]);

        try {
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', [
                '--class' => LocalDatabaseSeeder::class,
                '--force' => true,
            ]);
        } finally {
            // Even if migrate or the seeder break off: the demo must not stay
            // in maintenance mode, or it would be a 503 page until the next
            // run.
            $this->call('up');
        }

        $this->info('Demo-Datenbank zurueckgesetzt.');

        return self::SUCCESS;
    }
}
