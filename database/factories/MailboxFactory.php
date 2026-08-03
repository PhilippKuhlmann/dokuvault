<?php

namespace Database\Factories;

use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'mailAdress' => fake()->email(),
            'username' => fake()->userName(),
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            'mailbox_provider_id' => fake()->numberBetween($min = 1, $max = 4),
        ];
    }
}
