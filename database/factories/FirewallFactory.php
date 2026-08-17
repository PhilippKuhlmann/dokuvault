<?php

namespace Database\Factories;

use App\Models\Firewall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Firewall>
 */
class FirewallFactory extends Factory
{
    public function definition(): array
    {
        // Hersteller, Modell und Firmware zusammenpassend - eine Fortigate mit
        // Sophos-Versionsnummer faellt in der Demo sofort als Unsinn auf.
        $hardware = fake()->randomElement([
            ['Sophos', 'XGS 2100', 'SFOS 20.0.2 MR-2'],
            ['Fortinet', 'FortiGate 60F', 'FortiOS 7.4.4'],
            ['OPNsense', 'DEC750', '24.7.6'],
            ['pfSense', 'Netgate 4100', '2.7.2-RELEASE'],
            ['WatchGuard', 'Firebox T45', 'Fireware 12.10.2'],
        ]);

        return [
            'name' => 'FW-'.fake()->randomElement(['HH', 'B', 'M', 'K']).'-'.fake()->numberBetween(1, 9),
            'manufacturer' => $hardware[0],
            'model' => $hardware[1],
            'firmware' => $hardware[2],
            'serialNumber' => strtoupper(fake()->bothify('??######')),
            'management_url' => 'https://192.168.'.fake()->numberBetween(1, 250).'.1:4444',
            'username' => 'admin',
            'password' => fake()->password(8, 14),
            'port' => '4444',
            // Die Subscription laeuft demnaechst ab - genau der Fall, den das
            // Dashboard zeigen soll.
            'subscription_until' => fake()->dateTimeBetween('-1 month', '+18 months')->format('Y-m-d'),
            'height_units' => 1,
        ];
    }
}
