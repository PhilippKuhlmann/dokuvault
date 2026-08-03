<?php

namespace Database\Factories;

use App\Models\LoginNAS;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginNASFactory extends Factory
{
    protected $model = LoginNAS::class;

    public function definition(): array
    {
        return [
            'description' => fake()->randomElement(['Backup-Konto', 'Kamera-Aufzeichnung', 'Abteilungslaufwerk', 'Admin']),
            'username' => fake()->userName(),
            'password' => fake()->password(8, 14),
        ];
    }
}
