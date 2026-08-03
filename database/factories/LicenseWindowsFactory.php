<?php

namespace Database\Factories;

use App\Models\LicenseWindows;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseWindows>
 */
class LicenseWindowsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->uuid(),
            'operating_system_id' => fake()->numberBetween(1, 14),
        ];
    }
}
