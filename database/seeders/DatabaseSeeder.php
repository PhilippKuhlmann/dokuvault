<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (app()->environment() === 'local') {
            $this->call(LocalDatabaseSeeder::class);
        } elseif (app()->environment() === 'production') {
            $this->call(ProductionDatabaseSeeder::class);
        }

    }
}
