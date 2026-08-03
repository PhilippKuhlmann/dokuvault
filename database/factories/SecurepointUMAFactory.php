<?php

namespace Database\Factories;

use App\Models\SecurepointUMA;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurepointUMA>
 */
class SecurepointUMAFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => 'mailsec.'.fake()->domainName(),
            'manufacturer' => 'Reddoxx',
            'type' => 'Appliance',
            'username' => 'admin',
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            'encryptionkey' => fake()->password($minLength = 10, $maxLength = 20),
            'ip' => '192.168.175.254',
            'urlAdmin' => 'https://192.168.175.254:11115',
            'urlUser' => 'https://192.168.175.254',
        ];
    }
}
