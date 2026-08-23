<?php

namespace Database\Factories;

use App\Models\Cluster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cluster>
 */
class ClusterFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->randomElement(['PVE-Cluster', 'Hyper-V-Cluster', 'DB-Cluster']).'-'.fake()->numberBetween(1, 9),
            'type' => fake()->randomElement(array_keys(config('custom.cluster_types'))),
        ];
    }
}
