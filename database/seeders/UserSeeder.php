<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        User::factory()->create([
            'name' => 'Techniker',
            'username' => 'techniker',
            'email' => 'techniker@example.com',
            'password' => bcrypt('password'),
            'role_id' => Role::IS_TECHNIKER,
        ]);
    }
}
