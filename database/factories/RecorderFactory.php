<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RecorderFactory extends Factory
{
    public function definition()
    {
        [$m,$mo] = fake()->randomElement([
            ['Hikvision', 'DS-7608NI-K2'], ['Dahua', 'NVR4216'],
            ['Ubiquiti', 'UNVR-Pro'], ['Reolink', 'RLN36'],
        ]);

        return [
            'name' => 'NVR-'.fake()->randomElement(['Werkstatt', 'Hof', 'Zentrale']),
            'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??########')),
            'username' => 'admin', 'password' => fake()->password(8, 12),
        ];
    }
}
