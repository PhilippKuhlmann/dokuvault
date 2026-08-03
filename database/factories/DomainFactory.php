<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'name' => fake()->domainName(),
            'registrar' => fake()->randomElement(['United Domains', 'IONOS', 'Cloudflare', 'GoDaddy']),
            'expiry_date' => fake()->dateTimeBetween('-1 month', '+2 years')->format('Y-m-d'),
            'nameserver1' => 'ns1.'.fake()->domainName(),
            'nameserver2' => 'ns2.'.fake()->domainName(),
        ];
    }
}
