<?php

namespace Database\Factories;

use App\Models\FTPUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class FTPUserFactory extends Factory
{
    protected $model = FTPUser::class;

    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'password' => fake()->password(8, 14),
            'note' => fake()->randomElement([null, 'Nur Lesen', 'Upload Rechnungen', 'Deploy-Zugang']),
        ];
    }
}
