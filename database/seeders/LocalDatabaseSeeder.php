<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class LocalDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);

        $this->call([
            PermissionRoleSeeder::class,
            UserSeeder::class,
            OperatingSystemsSeeder::class,
            MailboxProvidorsSeeder::class,
        ]);

        // Times in the demo as a German MSP sees them - the backup page and
        // every timestamp said UTC otherwise.
        Setting::setzen(Setting::APP_TIMEZONE, 'Europe/Berlin');

        // Dienste-Katalog: derselbe Standard-Katalog wie eine neue Produktion
        // (ServiceSeeder) - lokal, Demo und Produktion sehen gleich aus.
        $this->call(ServiceSeeder::class);

        // Drawings of four device models - the rack shows the devices of the
        // same make and model with them.
        $this->call(DeviceModelSeeder::class);

        // The demo customer, a whole company with fixed values.
        $this->call(DemoCompanySeeder::class);

        $this->call([
            CustomerSeeder::class,
            // Installed agents, backup runs, API statistics - so those pages
            // are not empty in the demo.
            DemoAgentSeeder::class,
            // 90 days of history for Statistik -> System and Datenwachstum.
            DemoStatistikSeeder::class,
            // Zum Schluss, damit die Historie auf fertigen Daten aufsetzt und
            // im Protokoll ganz oben steht.
            DemoProtokollSeeder::class,
        ]);
    }
}
