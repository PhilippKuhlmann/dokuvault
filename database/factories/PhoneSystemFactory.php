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
            'name' => 'TK-'.fake()->numberBetween(1, 20),
            'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??########')),
            'port' => '443',
        ];
    }
}
