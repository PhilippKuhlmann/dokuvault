<?php

namespace Database\Factories;

use App\Models\Scanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scanner>
 */
class ScannerFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => 'scn-'.fake()->numberBetween(1, 100),
            'manufacturer' => fake()->randomElement(['Fujitsu', 'Brother', 'Canon', 'Epson']),
            'model' => fake()->randomElement(['fi-8170', 'ADS-4700W', 'DR-C230', 'DS-790WN']),
            'serialNumber' => fake()->ean13(),
        ];
    }
}
