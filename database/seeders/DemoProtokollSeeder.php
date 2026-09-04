<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Computer;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Printer;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Eine plausible Historie im Aktivitaetsprotokoll.
 *
 * Ohne sie steht dort nur, was der Seeder selbst angelegt hat - Hunderte Male
 * "Erstellt", alle in derselben Sekunde, alle ohne Verursacher. Wer die Demo
 * oeffnet, sieht ein Protokoll und lernt nichts daraus.
 *
 * Hier arbeiten stattdessen drei Techniker: Es wird angelegt, geaendert,
 * geloescht und wiederhergestellt. Und zwar wirklich - die Eintraege entstehen
 * aus echten Vorgaengen, nicht aus von Hand geschriebenen Protokollzeilen.
 * Deshalb Auth::login(): Das Protokoll traegt ein, wer gerade angemeldet ist.
 *
 * Eigener Seeder, damit er sich auch auf eine bestehende Datenbank anwenden
 * laesst:
 *
 *   php artisan db:seed --class=DemoProtokollSeeder
 */
class DemoProtokollSeeder extends Seeder
{
    public function run(): void
    {
        $kunde = Customer::where('slug', 'mustermann')->first();
        $standort = $kunde ? Site::where('customer_id', $kunde->id)->first() : null;

        if (! $kunde || ! $standort) {
            $this->command?->warn('DemoProtokollSeeder: Kunde "mustermann" oder Standort fehlt - uebersprungen.');

            return;
        }

        $jonas = User::where('name', 'Jonas Wieck')->first();
        $malte = User::where('name', 'Malte Ruhnau')->first();
        $rita = User::where('name', 'Rita Sander')->first();

        if (! $jonas || ! $malte || ! $rita) {
            $this->command?->warn('DemoProtokollSeeder: Demo-Benutzer fehlen - uebersprungen.');

            return;
        }

        // Jonas dokumentiert einen neuen Drucker und traegt am Server nach,
        // was ihm aufgefallen ist.
        $this->als($jonas, function () use ($kunde, $standort) {
            Printer::factory()->create([
                'customer_id' => $kunde->id,
                'site_id' => $standort->id,
                'name' => 'DRU-Empfang',
                'manufacturer' => 'Kyocera',
                'model' => 'ECOSYS MA4000',
            ]);

            // Lieferant und Garantie nachgetragen - die Arbeit, die nach dem
            // Kauf liegen bleibt und ohne die das Dashboard nicht warnen kann.
            Server::where('customer_id', $kunde->id)->where('name', 'SRV-APP01')->first()
                ?->update(['supplier' => 'Bechtle', 'warranty_until' => now()->addMonths(8)]);
        });

        // Malte korrigiert den zweiten DNS im Clients-VLAN.
        $this->als($malte, function () use ($kunde) {
            Network::where('customer_id', $kunde->id)->where('description', 'Clients')->first()
                ?->update(['dns2' => '1.1.1.1']);

            Certificate::factory()->create([
                'customer_id' => $kunde->id,
                'name' => 'Wildcard *.mustermann.de',
                'expiry_date' => now()->addMonths(4),
            ]);
        });

        // Rita raeumt auf: ein alter Rechner geht in den Papierkorb - und wird
        // gleich wieder zurueckgeholt. Genau der Fall, fuer den es den
        // Papierkorb gibt, und im Protokoll sind beide Schritte zu sehen.
        $this->als($rita, function () use ($kunde, $standort) {
            ContactPerson::factory()->create([
                'customer_id' => $kunde->id,
                'first_name' => 'Sabine',
                'last_name' => 'Roth',
                'role' => 'Einkauf',
            ]);

            $alt = Computer::factory()->create([
                'customer_id' => $kunde->id,
                'site_id' => $standort->id,
                'name' => 'PC-Alt-19',
            ]);

            $alt->delete();
            $alt->restore();
        });
    }

    /**
     * Einen Vorgang im Namen eines Benutzers ausfuehren.
     *
     * Das Protokoll liest den Verursacher aus der Anmeldung. Ohne sie stuende
     * bei jedem Eintrag "System", und der Sinn der Uebung waere weg.
     */
    private function als(User $nutzer, callable $arbeit): void
    {
        Auth::login($nutzer);

        try {
            $arbeit();
        } finally {
            Auth::logout();
        }
    }
}
