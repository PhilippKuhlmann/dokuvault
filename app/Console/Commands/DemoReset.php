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

        // Wartungsmodus, solange das Schema neu entsteht. migrate:fresh
        // loescht und legt die Tabellen wieder an; eine Anfrage, die genau in
        // diese Sekunden faellt, traf bisher auf ein halbfertiges Schema und
        // endete mit einem Fehler 500 im Log - am 29.10. etwa eine
        // gescheiterte Anmeldung, deren Protokolleintrag in ein
        // activity_log ohne customer_id schreiben wollte. Jetzt sieht der
        // Besucher dieselbe 503-Seite wie beim Deploy und ist nach wenigen
        // Sekunden wieder da.
        $this->call('down', ['--retry' => 15]);

        try {
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', [
                '--class' => LocalDatabaseSeeder::class,
                '--force' => true,
            ]);
        } finally {
            // Auch wenn migrate oder der Seeder abbrechen: Die Demo darf nicht
            // im Wartungsmodus stehenbleiben, sonst waere sie bis zum
            // naechsten Lauf eine 503-Seite.
            $this->call('up');
        }

        $this->info('Demo-Datenbank zurueckgesetzt.');

        return self::SUCCESS;
    }
}
