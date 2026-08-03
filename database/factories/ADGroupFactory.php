<?php

namespace Database\Factories;

use App\Models\ADGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ADGroup>
 */
class ADGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->slug(),
            'description' => fake()->text($maxNbChars = 50),
        ];
    }
}
