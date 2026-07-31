<?php

namespace Database\Factories;

use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => 'Rack '.fake()->numberBetween(1, 20),
            'height_units' => fake()->randomElement([12, 24, 42]),
            'location' => fake()->randomElement(['Serverraum EG', 'Serverraum UG', 'Technikraum OG', null]),
        ];
    }
}
