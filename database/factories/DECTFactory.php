<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DECTFactory extends Factory
{
    public function definition()
    {
        [$m,$mo] = fake()->randomElement([
            ['Yealink', 'W60B'], ['Yealink', 'W80B'], ['Snom', 'M700'],
            ['Gigaset', 'N870 IP'], ['Grandstream', 'DP720'],
        ]);

        return [
            'role' => fake()->randomElement(['Master', 'Slave']),
            'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??########')),
            'ip' => fake()->localIpv4(),
            'mac' => fake()->macAddress(),
            'port' => '443',
            'username' => fake()->userName(),
            'password' => fake()->password(6, 12),
        ];
    }
}
