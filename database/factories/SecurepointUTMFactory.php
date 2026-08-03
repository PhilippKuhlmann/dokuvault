<?php

namespace Database\Factories;

use App\Models\SecurepointUTM;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurepointUTM>
 */
class SecurepointUTMFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => 'utm01.'.fake()->domainName(),
            'type' => 'VM',
            'username' => 'admin',
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            'cloudBackupPassword' => fake()->password($minLength = 6, $maxLength = 12),
            'uscpin' => fake()->numberBetween(1000, 9999),
            'ip' => '192.168.175.1',
            'urlAdmin' => 'https://192.168.175.1:11115',
            'urlUser' => 'https://192.168.175.1',
            'urlExternal' => 'https://meine.spdns.de:11115',
        ];
    }
}
