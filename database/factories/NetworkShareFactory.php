<?php

namespace Database\Factories;

use App\Models\NetworkShare;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkShareFactory extends Factory
{
    protected $model = NetworkShare::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Gemeinsam', 'Projekte', 'Vorlagen', 'Buchhaltung', 'Vertrieb']);

        return [
            'name' => $name,
            'drive_letter' => strtoupper(substr($name, 0, 1)),
            'path' => '\\\\SRV-FS01\\'.$name,
        ];
    }
}
