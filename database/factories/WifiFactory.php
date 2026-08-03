<?php

namespace Database\Factories;

use App\Models\Network;
use App\Models\Wifi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wifi>
 */
class WifiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'ssid' => fake()->domainWord(),
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            'network_id' => Network::inRandomOrder()->first()->id,
            'encryption' => fake()->randomElement(['WPA2', 'WPA3', 'offen']),
        ];
    }
}
