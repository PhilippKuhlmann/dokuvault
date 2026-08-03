<?php

namespace Database\Factories;

use App\Models\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => 'prt-'.fake()->numberBetween($min = 1, $max = 100),
            'manufacturer' => fake()->randomElement(['Brother', 'HP', 'Kyocera']),
            'model' => fake()->randomElement(['MFC-L8900CDW', 'LaserJet M480', 'ECOSYS M5526cdw', 'WorkForce Pro WF-C5790']),
            'serialNumber' => fake()->ean13(),
            'ip' => fake()->localIpv4(),
            'port' => fake()->randomElement(['443', '80', '8080']),
            'username' => fake()->userName(),
            'password' => fake()->password($minLength = 6, $maxLength = 12),
        ];
    }
}
