<?php

namespace Database\Factories;

use App\Models\PatchPanel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatchPanel>
 */
class PatchPanelFactory extends Factory
{
    public function definition()
    {
        $ports = fake()->randomElement([24, 24, 48]);

        return [
            'name' => 'PF-'.fake()->numberBetween(1, 20),
            'port_count' => $ports,
            // 48er-Felder sind ueblicherweise 2 HE hoch.
            'height_units' => $ports > 24 ? 2 : 1,
            'manufacturer' => fake()->randomElement(['Rutenbeck', 'Telegärtner', 'Digitus', 'Metz Connect']),
            'model' => fake()->randomElement(['Cat.6A', 'Cat.6', 'Cat.7 geschirmt']),
        ];
    }
}
