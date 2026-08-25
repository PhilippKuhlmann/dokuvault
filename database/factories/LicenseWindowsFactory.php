<?php

namespace Database\Factories;

use App\Models\LicenseWindows;
use App\Models\OperatingSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseWindows>
 */
class LicenseWindowsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->uuid(),
            // Ein echtes Windows-System aus dem Katalog, keine gewuerfelte Id
            // zwischen 1 und 14: Welches System hinter einer Nummer steckt,
            // haengt an der Reihenfolge im Seeder - im Demo-Datensatz standen
            // dadurch Windows-Lizenzen fuer "Debian 13" und "Proxmox VE 7".
            'operating_system_id' => fn () => OperatingSystem::where('name', 'like', 'Windows%')
                ->inRandomOrder()->value('id')
                ?? OperatingSystem::factory()->create(['name' => 'Windows Server 2025 Standard'])->id,
        ];
    }
}
