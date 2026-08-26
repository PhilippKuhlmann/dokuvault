<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
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
            // unique(): username traegt einen UNIQUE-Index. Ohne das zieht die
            // Factory frueher oder spaeter denselben Namen zweimal - ein Test,
            // der in einer Schleife zwanzig Benutzer anlegt, bricht dann
            // scheinbar grundlos ab. Genau das ist im CI passiert.
            'username' => fake()->unique()->name(),
            'role_id' => 10,
            'password' => bcrypt('password'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
