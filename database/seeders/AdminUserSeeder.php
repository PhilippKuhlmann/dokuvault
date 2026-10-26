<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * The documented admin/password login of the demo and local development.
     * Only LocalDatabaseSeeder calls this - a production install creates its
     * first administrator under /setup instead.
     */
    public function run(): void
    {
        User::forceCreate([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role_id' => Role::IS_ADMIN,
        ]);
    }
}
