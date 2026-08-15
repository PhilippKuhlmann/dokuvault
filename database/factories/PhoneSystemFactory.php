<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneSystemFactory extends Factory
{
    public function definition()
    {
        [$m,$mo] = fake()->randomElement([
            ['Auerswald', 'COMpact 5500'], ['3CX', 'v20 PRO'],
            ['Starface', 'Compact'], ['Panasonic', 'KX-NS700'],
        ]);

        return [
            'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??########')),
            'port' => '443',
            'username' => fake()->userName(),
            'password' => fake()->password(6, 12),
        ];
    }
}
