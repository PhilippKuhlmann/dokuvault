<?php

namespace Database\Factories;

use App\Models\ADDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ADDomain>
 */
class ADDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * domain, netbios und dsrmpassword sind in der Tabelle NOT NULL. Die
     * Vorgabe war leer, jedes ADDomain::factory()->create() ohne vollstaendige
     * Angaben lief deshalb in eine Datenbank-Verletzung - im Seeder faellt das
     * nicht auf, weil der alle drei Felder mitgibt.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // Interne AD-Domaenen heissen nach der Firma, nicht nach einer
        // beliebigen Internet-Domain: ad.mustermann.de mit MUSTERMANN davor.
        $firma = fake()->unique()->domainWord();

        return [
            'domain' => 'ad.'.$firma.'.de',
            // NetBIOS-Namen sind grossgeschrieben und hoechstens 15 Zeichen
            // lang - laengere schneidet Windows selbst ab.
            'netbios' => Str::upper(Str::substr($firma, 0, 15)),
            'dsrmpassword' => fake()->password(12, 16),
            'hidden' => false,
        ];
    }
}
