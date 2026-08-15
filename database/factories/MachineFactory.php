<?php

namespace Database\Factories;

use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

class MachineFactory extends Factory
{
    protected $model = Machine::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['CNC-Fräse Halle 1', 'Laserschneider', 'Verpackungsanlage', 'Etikettendrucker Produktion', 'Waage Warenausgang']),
        ];
    }
}
