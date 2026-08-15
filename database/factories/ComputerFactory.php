<?php

namespace Database\Factories;

use App\Models\Computer;
use App\Models\OperatingSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Computer>
 */
class ComputerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /** Modell zum Hersteller - sonst steht ein ThinkCentre unter "HP". */
    private const HARDWARE = [
        'Wortmann' => ['TERRA PC-Business 5000', 'TERRA All-In-One-PC 2400'],
        'HP' => ['EliteDesk 800 G9', 'ProDesk 400 G7'],
        'Lenovo' => ['ThinkCentre M70q', 'ThinkCentre M90t'],
        'Dell' => ['OptiPlex 7010', 'OptiPlex Micro 7020'],
    ];

    public function definition()
    {
        $manufacturer = fake()->randomElement(array_keys(self::HARDWARE));

        return [
            'name' => 'PC-'.fake()->numberBetween($min = 1, $max = 100),
            'manufacturer' => $manufacturer,
            'model' => fake()->randomElement(self::HARDWARE[$manufacturer]),
            'serialNumber' => fake()->ean13(),
            // Client-Betriebssystem statt einer festen ID aus dem Windows-Server-Block
            'operating_system_id' => OperatingSystem::where('name', 'like', 'Windows 1%')
                ->inRandomOrder()->value('id') ?? 1,
        ];
    }
}
