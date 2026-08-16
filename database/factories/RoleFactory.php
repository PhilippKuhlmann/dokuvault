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
        return [
            'name' => 'Rolle '.(++self::$laufend).' '.fake()->jobTitle(),
        ];
    }
}
