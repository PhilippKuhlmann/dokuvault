<?php

namespace Database\Factories;

use App\Models\ADUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ADUser>
 */
class ADUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $vorname = fake()->firstName();
        $nachname = fake()->lastName();

        // Benutzername und Adresse aus dem Namen, nicht unabhaengig gewuerfelt:
        // In einem echten Verzeichnis heisst "Anna Berger" auch anna.berger und
        // nicht xdecker. Vorher standen in einer Zeile drei Personen.
        // Str::slug nimmt Umlaute und Bindestriche mit heraus.
        $konto = Str::slug($vorname).'.'.Str::slug($nachname);

        return [
            'firstName' => $vorname,
            'lastName' => $nachname,
            'username' => $konto,
            // Nicht bei jedem: Dienst- und Sammelkonten haben keine.
            'email' => fake()->boolean(70) ? $konto.'@'.fake()->safeEmailDomain() : null,
            'password' => fake()->password($minLength = 6, $maxLength = 12),
            // Ohne das stand die Spalte "Status" bei jedem Demo-Benutzer auf
            // "—". Ueberwiegend aktiv mit ein paar gesperrten Konten - so
            // sieht ein gewachsenes Verzeichnis aus.
            'enabled' => fake()->boolean(85),
        ];
    }

    /**
     * Alle Adressen auf die Domain der Firma setzen.
     *
     * Ohne das hat jeder Benutzer eine eigene Domain - in einer Firma teilen
     * sich alle dieselbe, und genau daran erkennt man in der Liste, dass die
     * Daten zusammengehoeren.
     */
    public function beiFirma(string $domain): static
    {
        return $this->state(fn (array $vorhanden) => [
            'email' => $vorhanden['email'] === null
                ? null
                : Str::before($vorhanden['email'], '@').'@'.$domain,
        ]);
    }

    /** Ein ausgeschiedener Mitarbeiter: Konto gesperrt, Adresse bleibt stehen. */
    public function gesperrt(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    /**
     * Ein Dienstkonto - kein Mensch, also kein Vor- und Nachname und keine
     * Adresse. Genau der Fall, in dem eine leere Spalte richtig ist.
     */
    public function dienstkonto(string $name): static
    {
        return $this->state(fn () => [
            'firstName' => null,
            'lastName' => null,
            'username' => $name,
            'email' => null,
            'enabled' => true,
        ]);
    }

    /** Ein Konto, dessen Status nie dokumentiert wurde. */
    public function ohneStatus(): static
    {
        return $this->state(fn () => ['enabled' => null]);
    }
}
