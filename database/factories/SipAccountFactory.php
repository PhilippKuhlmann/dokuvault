<?php

namespace Database\Factories;

use App\Models\SipAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SipAccount>
 */
class SipAccountFactory extends Factory
{
    public function definition()
    {
        return [
            'provider' => fake()->randomElement(['Deutsche Telekom', 'sipgate', 'easybell', 'Placetel']),
            'account_type' => 'trunk',
            'main_number' => '040 '.fake()->numberBetween(100000, 999999),
            'number_range' => '0-99',
            'channels' => 4,
        ];
    }
}
