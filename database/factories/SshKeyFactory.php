<?php

namespace Database\Factories;

use App\Models\SshKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SshKeyFactory extends Factory
{
    protected $model = SshKey::class;

    public function definition(): array
    {
        $verfahren = fake()->randomElement(array_keys(config('custom.ssh_key_types')));
        $benutzer = fake()->randomElement(['root', 'deploy', 'backup', 'admin']);

        return [
            'name' => ucfirst($benutzer).' '.$verfahren,
            'description' => fake()->randomElement(['Wartungszugang', 'Auslagerung', 'Ausrollen', 'Notzugang']),
            'username' => $benutzer,
            'key_type' => $verfahren,
            // Kein echtes Schluesselpaar: Die Demo soll aussehen wie einer,
            // aber keiner sein, der irgendwo tatsaechlich passt.
            'public_key' => 'ssh-'.$verfahren.' AAAA'.Str::random(48).' '.$benutzer.'@'.fake()->domainWord(),
            'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\n".Str::random(64)."\n-----END OPENSSH PRIVATE KEY-----",
            'password' => fake()->boolean(60) ? fake()->password(10, 16) : null,
        ];
    }
}
