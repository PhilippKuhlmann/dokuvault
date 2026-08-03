<?php

namespace Database\Factories;

use App\Models\Ups;
use Illuminate\Database\Eloquent\Factories\Factory;

class UpsFactory extends Factory
{
    protected $model = Ups::class;

    public function definition(): array
    {
        return [
            'name' => 'USV-'.fake()->numberBetween(1, 100),
            'manufacturer' => fake()->randomElement(['APC', 'Eaton', 'CyberPower']),
            'model' => fake()->bothify('??-####'),
            'serialNumber' => fake()->ean13(),
            'ip' => fake()->localIpv4(),
            'capacity' => fake()->randomElement(['750VA', '1500VA', '3000VA']),
            'runtime' => fake()->numberBetween(5, 60).' Min',
        ];
    }
}
