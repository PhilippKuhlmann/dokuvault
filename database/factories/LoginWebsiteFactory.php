<?php

namespace Database\Factories;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class LoginWebsiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->domainWord(),
            'username' => fake()->userName(),
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            'url' => 'https://example.com',
        ];
    }
}
