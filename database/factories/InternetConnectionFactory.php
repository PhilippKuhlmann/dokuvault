<?php

namespace Database\Factories;

use App\Models\InternetConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

class InternetConnectionFactory extends Factory
{
    protected $model = InternetConnection::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['Telekom', 'Vodafone', '1&1', 'Deutsche Glasfaser']),
            'product' => fake()->randomElement(['DSL 250', 'Fiber 1000', 'Cable 500']),
            'contract_number' => fake()->numerify('VTR-#######'),
            'connection_type' => fake()->randomElement(['DSL', 'Glasfaser', 'Kabel']),
            'bandwidth_down' => fake()->randomElement(['100', '250', '1000']),
            'bandwidth_up' => fake()->randomElement(['40', '100', '1000']),
            'wan_ip' => fake()->ipv4(),
            'hotline' => fake()->phoneNumber(),
        ];
    }
}
