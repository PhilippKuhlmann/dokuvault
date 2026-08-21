<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    private static int $laufend = 0;

    public function definition()
    {
        // roles.name traegt einen UNIQUE-Index, fake()->name() zieht aber aus
        // einem endlichen Vorrat: In einem Lauf mit vielen Rollen kam
        // irgendwann derselbe Name zweimal und die ganze Suite brach ab - auf
        // der CI genau so passiert ("Edgar Rudolph"), lokal nie, weil die
        // Zufallswerte dort andere waren.
        //
        // Ein Personenname ist fuer eine Rolle ohnehin die falsche Wahl.
        // fake()->unique() waere naheliegend, laeuft aber nach genug Aufrufen
        // selbst in eine Ausnahme; der Zaehler nicht.
        self::$laufend++;

        return [
            // Ids ab 100, damit keine Testrolle zufaellig die 1 bekommt.
            //
            // Die Rolle 1 ist der Admin und darf per Gate::before alles. In
            // einem frisch migrierten Testlauf ist die erste angelegte Rolle
            // genau die 1 - jeder Test, der "ohne Recht kein Zugriff" prueft,
            // haette damit stillschweigend einen Super-Admin am Werk und waere
            // wertlos. Genau so ist es beim Umbau aufgefallen: 33 Tests
            // meldeten 200 statt 403.
            //
            // Tests, die bewusst eine Systemrolle brauchen, geben die Id
            // weiterhin selbst an.
            'id' => 100 + self::$laufend,
            'name' => 'Rolle '.self::$laufend.' '.fake()->jobTitle(),
        ];
    }
}
