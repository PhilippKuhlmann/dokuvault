<?php

namespace Database\Factories;

use App\Models\VM;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VM>
 */
class VMFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => 'VM-'.fake()->randomElement(['DC02', 'Exchange', 'RDS01', 'App-ERP', 'SQL02', 'WSUS', 'Webserver', 'Terminal']),
            'services' => 'docker,apache2,mariadb',
            'operating_system_id' => fake()->numberBetween($min = 1, $max = 10),
            'remoteID' => fake()->numberBetween($min = 100000000, $max = 999999999),
            'remotePassword' => fake()->password($minLength = 10, $maxLength = 14),
        ];
    }
}
