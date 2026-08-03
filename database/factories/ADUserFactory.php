<?php

namespace Database\Factories;

use App\Models\ADUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ADUser>
 */
class ADUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'firstName' => fake()->firstName($gender = 'male' | 'female'),
            'lastName' => fake()->lastName(),
            'username' => fake()->userName(),
            'password' => fake()->password($minLength = 6, $maxLength = 12),
        ];
    }
}
