<?php

namespace App\Console\Commands;

use Database\Seeders\LocalDatabaseSeeder;
use Faker\Factory;
use Illuminate\Console\Command;

/**
 * Setzt die Demo-Instanz auf den Auslieferungsstand zurueck.
 *
 * Der Befehl loescht die komplette Datenbank. Er ist deshalb an DEMO_MODE
 * gebunden und verweigert den Dienst auf jeder anderen Installation - auf
 * einem echten Server waere ein versehentlicher Aufruf der Totalverlust der
 * Kundendokumentation.
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

        // Die Demo-Daten stammen aus dem LocalDatabaseSeeder und brauchen Faker.
        // Er wird direkt aufgerufen, weil DatabaseSeeder nach APP_ENV verzweigt
        // und auf der Demo (APP_ENV=production) sonst die Startdaten laufen.
        if (! class_exists(Factory::class)) {
            $this->error('Faker fehlt - die Demo-Instanz muss mit Dev-Abhaengigkeiten installiert sein.');
            $this->line('composer install (ohne --no-dev)');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', [
            '--class' => LocalDatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->info('Demo-Datenbank zurueckgesetzt.');

        return self::SUCCESS;
    }
}
