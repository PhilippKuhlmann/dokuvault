<?php

namespace Database\Factories;

use App\Models\OtherClient;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtherClientFactory extends Factory
{
    protected $model = OtherClient::class;

    public function definition(): array
    {
        [$name,$m,$mo] = fake()->randomElement([
            ['Kassensystem', 'Elo', 'E-Series 15"'],
            ['Digital Signage Empfang', 'Samsung', 'QB43R'],
            ['Thin Client Lager', 'IGEL', 'UD3'],
            ['Info-Terminal', 'Advantech', 'UTC-520'],
        ]);

        return [
            'name' => $name, 'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??####-######')),
            'username' => 'admin', 'password' => fake()->password(8, 14),
        ];
    }
}
