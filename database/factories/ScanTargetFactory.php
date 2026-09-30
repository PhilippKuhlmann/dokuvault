<?php

namespace Database\Factories;

use App\Models\ScanTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanTarget>
 */
class ScanTargetFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->randomElement(['Buchhaltung', 'Empfang', 'Archiv', 'Personal']),
            'kind' => 'smb',
            'target' => '\\\\srv-file01\\scans\\'.fake()->word(),
        ];
    }
}
