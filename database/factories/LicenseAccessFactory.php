<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseAccessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Windows Server 2022 User CAL (25)',
                'Windows Server 2022 Device CAL (50)',
                'RDS User CAL (20)',
                'Exchange Server Standard CAL (25)',
                'SQL Server 2022 CAL (10)',
            ]),
            'key' => strtoupper(fake()->bothify('?????-?????-?????-?????-?????')),
        ];
    }
}
