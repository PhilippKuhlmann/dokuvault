<?php

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Hersteller/Modell zusammenpassend - ein Domainname als Routername
        // (fake()->domainName) sah in Listen und im Rack wie ein Datenfehler aus.
        $hardware = fake()->randomElement([
            ['Lancom', '1900EF'],
            ['MikroTik', 'RB5009UG'],
            ['TP-Link', 'ER8411'],
            ['Ubiquiti', 'EdgeRouter 6P'],
        ]);

        return [
            'name' => 'RTR-'.fake()->numberBetween(1, 99),
            'manufacturer' => $hardware[0],
            'model' => $hardware[1],
            'username' => 'admin',
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            'ip' => '192.168.'.fake()->numberBetween(1, 254).'.1',
            'port' => '443',
        ];
    }
}
