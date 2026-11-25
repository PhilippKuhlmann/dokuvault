<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Computer;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Printer;
use App\Models\Role;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Models\Wifi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        $standort = $kunde ? Site::where('customer_id', $kunde->id)->where('name', 'Zentrale Hamburg')->first() : null;

        if (! $kunde || ! $standort) {
            $this->command?->warn('DemoProtokollSeeder: Kunde "mustermann" oder Standort fehlt - uebersprungen.');

            return;
        }

        // The three technicians of the IT service provider. Created here if
        // missing - before, the seeder looked for them, found nobody and the
        // log stayed empty on the demo. They cannot sign in: random password.
        [$jonas, $malte, $rita] = collect(['Jonas Wieck' => 'jwieck', 'Malte Ruhnau' => 'mruhnau', 'Rita Sander' => 'rsander'])
            ->map(fn ($username, $name) => User::firstOrCreate(['username' => $username], [
                'name' => $name, 'email' => $username.'@systemhaus.example', 'password' => bcrypt(Str::random(40)),
                'role_id' => Role::IS_TECHNIKER,
            ]))->values()->all();

        // Jonas documents a new label printer for the warehouse and adds the
        // supplier at the Hyper-V host.
        $this->als($jonas, function () use ($kunde, $standort) {
            $drucker = Printer::forceCreate([
                'customer_id' => $kunde->id, 'site_id' => $standort->id, 'name' => 'PRN-Versand',
                'manufacturer' => 'Kyocera', 'model' => 'ECOSYS PA4500x', 'serialNumber' => 'VCN4Y02187', 'port' => 443,
                'purchase_date' => now()->subDays(3)->toDateString(), 'warranty_until' => now()->addYears(3)->toDateString(),
                'supplier' => 'Kyocera Partner Nord',
            ]);
            $drucker->ipAddresses()->create([
                'customer_id' => $kunde->id, 'address' => '10.10.60.24', 'label' => 'Primär',
                'network_id' => Network::where('customer_id', $kunde->id)->where('description', 'Drucker')->value('id'),
            ]);

            Server::where('customer_id', $kunde->id)->where('name', 'SRV-HV01')->first()
                ?->update(['supplier' => 'Dell Technologies (über Bechtle)']);
        });

        // Malte fixes the second DNS of the Clients VLAN, changes the guest
        // Wi-Fi password (the old one goes to the password history) and
        // documents the VPN certificate.
        $this->als($malte, function () use ($kunde) {
            Network::where('customer_id', $kunde->id)->where('site_id', Site::where('customer_id', $kunde->id)->where('name', 'Zentrale Hamburg')->value('id'))
                ->where('description', 'Clients')->first()
                ?->update(['dns2' => '10.10.30.20']);

            Wifi::where('customer_id', $kunde->id)->where('ssid', 'Mustermann-Gast')->first()
                ?->update(['password' => 'Gast-Hamburg-2026-Q4']);

            Certificate::forceCreate([
                'customer_id' => $kunde->id, 'name' => 'vpn.mustermann.de', 'common_name' => 'vpn.mustermann.de',
                'issuer' => "Let's Encrypt", 'type' => 'SSL/TLS', 'issued_date' => now()->subDays(20)->toDateString(),
                'expiry_date' => now()->addDays(70)->toDateString(), 'auto_check' => false,
                'notes' => 'SSL-VPN und Benutzerportal der FW-HH',
            ]);
        });

        // Rita tidies up: a new contact; an old PC goes to the trash and comes
        // back (both steps in the log); an old printer stays in the trash.
        $this->als($rita, function () use ($kunde, $standort) {
            ContactPerson::forceCreate([
                'customer_id' => $kunde->id, 'first_name' => 'Sabine', 'last_name' => 'Roth', 'role' => 'Einkauf, bestellt Hardware',
                'phone' => '040 7541 3215', 'mail' => 'sabine.roth@mustermann.de',
            ]);

            $alt = Computer::forceCreate([
                'customer_id' => $kunde->id, 'site_id' => $standort->id, 'name' => 'PC-ALT-LAGER',
                'manufacturer' => 'Dell', 'model' => 'OptiPlex 7050', 'serialNumber' => '4KQ7XH2',
            ]);
            $alt->delete();
            $alt->restore();

            Printer::forceCreate([
                'customer_id' => $kunde->id, 'site_id' => $standort->id, 'name' => 'PRN-Lager-alt',
                'manufacturer' => 'Kyocera', 'model' => 'FS-2100DN', 'serialNumber' => 'LJA4Z01733',
            ])->delete();
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
