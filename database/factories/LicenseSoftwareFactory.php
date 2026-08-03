<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseSoftwareFactory extends Factory
{
    public function definition(): array
    {
        $software = fake()->randomElement([
            'Microsoft 365 Business Premium', 'Adobe Creative Cloud', 'TeamViewer Corporate',
            'DATEV Unternehmen online', 'Veeam Backup & Replication', 'Acronis Cyber Protect',
            'Sophos Intercept X', 'ESET PROTECT', 'AutoCAD LT', 'Lexware Buchhaltung',
        ]);

        return [
            'name' => $software,
            'key' => strtoupper(fake()->bothify('?????-?????-?????-?????-?????')),
            'username' => fake()->userName(),
            'password' => fake()->password(8, 14),
            'start_date' => now()->subYear()->toDateString(),
            // ein Teil läuft demnächst ab -> Dashboard-Warnung
            'end_date' => fake()->randomElement([
                now()->addDays(fake()->numberBetween(5, 45))->toDateString(),
                now()->addYear()->toDateString(),
                now()->addMonths(8)->toDateString(),
            ]),
            'abo' => fake()->randomElement(['jährlich', 'monatlich']),
        ];
    }
}
