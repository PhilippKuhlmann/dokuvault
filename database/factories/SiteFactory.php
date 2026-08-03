<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Lohne', 'Brägel', 'Vechta']),
            'street' => fake()->streetName(),
            'house_number' => fake()->numberBetween(1, 145),
            'zip' => fake()->postcode(),
            'city' => fake()->city(),
        ];
    }
}
