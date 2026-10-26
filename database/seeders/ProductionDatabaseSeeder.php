<?php

namespace Database\Seeders;

use App\Console\Commands\SetupCode;
use App\Support\InitialSetup;
use Illuminate\Database\Seeder;

class ProductionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // No admin/password here: the first administrator is created under
        // /setup with a one-time code - see App\Support\InitialSetup.
        $this->call([
            PermissionRoleSeeder::class,
            OperatingSystemsSeeder::class,
            MailboxProvidorsSeeder::class,
            // Standard service catalog - without it the agents assign nothing.
            ServiceSeeder::class,
        ]);

        if (InitialSetup::hasAdmin()) {
            return;
        }

        $code = filled(config('custom.setup_token')) ? null : InitialSetup::newCode();

        if ($this->command) {
            SetupCode::announce($this->command, $code);
        }
    }
}
