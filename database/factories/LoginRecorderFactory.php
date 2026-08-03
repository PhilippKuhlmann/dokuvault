<?php

namespace Database\Factories;

use App\Models\LoginRecorder;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginRecorderFactory extends Factory
{
    protected $model = LoginRecorder::class;

    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'password' => fake()->password(8, 14),
        ];
    }
}
