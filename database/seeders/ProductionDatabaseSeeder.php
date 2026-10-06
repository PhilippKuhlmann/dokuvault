<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            PermissionRoleSeeder::class,
            OperatingSystemsSeeder::class,
            MailboxProvidorsSeeder::class,
            // Standard service catalog - without it the agents assign nothing.
            ServiceSeeder::class,
        ]);
    }
}
