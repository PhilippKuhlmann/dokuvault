<?php

namespace Database\Factories;

use App\Models\NetworkSwitch;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkSwitchFactory extends Factory
{
    protected $model = NetworkSwitch::class;

    public function definition(): array
    {
        [$m,$mo] = fake()->randomElement([
            ['Ubiquiti', 'USW-Pro-48-PoE'], ['Cisco', 'Catalyst 2960-X'],
            ['HPE Aruba', '2930F 48G'], ['Netgear', 'GS748T'], ['Zyxel', 'XGS1930-28'],
        ]);

        return [
            'name' => 'SW-'.fake()->randomElement(['Core', 'Access-01', 'Access-02', 'Server', 'Etage-1', 'Etage-2']),
            'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??####-######')),
            'username' => 'admin', 'password' => fake()->password(8, 14),
            'ip' => fake()->localIpv4(), 'port' => '443',
        ];
    }
}
