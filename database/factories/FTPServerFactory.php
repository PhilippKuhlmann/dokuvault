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
            // Benutzername und Kennwort haengen nicht am Server, sondern an
            // verknuepften Eintraegen aus "Logins Allgemein" (credential_links).
            'description' => fake()->randomElement(['Datenaustausch Steuerberater', 'Backup extern', 'Lieferanten-Upload', 'Webseiten-Deploy']),
        ];
    }
}
