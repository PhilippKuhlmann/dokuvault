<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneFactory extends Factory
{
    public function definition()
    {
        [$m,$mo] = fake()->randomElement([
            ['Yealink', 'T54W'], ['Yealink', 'T46U'], ['Snom', 'D785'],
            ['Grandstream', 'GRP2614'], ['Fanvil', 'X5U'],
        ]);

        return [
            'extension' => fake()->numberBetween(10, 99),
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
