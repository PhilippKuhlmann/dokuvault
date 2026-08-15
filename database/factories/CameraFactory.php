<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CameraFactory extends Factory
{
    public function definition()
    {
        [$manufacturer, $model] = fake()->randomElement([
            ['Hikvision', 'DS-2CD2143G2-I'],
            ['Axis', 'M3057-PLVE'],
            ['Dahua', 'IPC-HDW3549'],
            ['Ubiquiti', 'G4 Pro'],
            ['Mobotix', 'M16B'],
        ]);

        return [
            'name' => 'CAM-'.fake()->randomElement(['Werkstatt', 'Hof', 'Parkplatz', 'Buero', 'Tor', 'Eingang', 'Flur', 'Lager']),
            'manufacturer' => $manufacturer,
            'model' => $model,
            'serialNumber' => strtoupper(fake()->bothify('??########')),
            'port' => '80',
            'username' => 'admin',
            'password' => fake()->password(8, 12),
        ];
    }
}
