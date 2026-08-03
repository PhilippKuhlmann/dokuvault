<?php

namespace Database\Factories;

use App\Models\Accesspoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccesspointFactory extends Factory
{
    protected $model = Accesspoint::class;

    public function definition(): array
    {
        [$m,$mo] = fake()->randomElement([
            ['Ubiquiti', 'U6-Pro'], ['Ubiquiti', 'U6-Lite'], ['HPE Aruba', 'AP-515'],
            ['Cisco Meraki', 'MR46'], ['TP-Link Omada', 'EAP670'],
        ]);

        return [
            'name' => 'AP-'.fake()->randomElement(['Empfang', 'Buero-1', 'Buero-2', 'Lager', 'Besprechung', 'Werkstatt']),
            'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??####-######')),
            'username' => 'admin', 'password' => fake()->password(8, 14),
            'ip' => fake()->localIpv4(), 'port' => '443',
        ];
    }
}
