<?php

namespace Database\Factories;

use App\Models\FTPServer;
use Illuminate\Database\Eloquent\Factories\Factory;

class FTPServerFactory extends Factory
{
    protected $model = FTPServer::class;

    public function definition(): array
    {
        return [
            'host' => 'ftp.'.fake()->domainName(),
            // Benutzername und Kennwort haengen jetzt am Zugang, nicht am
            // Server - der Seeder legt sie ueber die Beziehung an.
            'description' => fake()->randomElement(['Datenaustausch Steuerberater', 'Backup extern', 'Lieferanten-Upload', 'Webseiten-Deploy']),
        ];
    }
}
