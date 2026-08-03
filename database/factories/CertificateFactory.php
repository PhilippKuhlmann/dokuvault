<?php

namespace Database\Factories;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        $issued = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'name' => 'SSL '.fake()->domainName(),
            'common_name' => fake()->domainName(),
            'issuer' => fake()->randomElement(["Let's Encrypt", 'DigiCert', 'Sectigo', 'GlobalSign', 'GeoTrust']),
            'type' => fake()->randomElement(['SSL/TLS', 'Wildcard', 'Code Signing', 'S/MIME']),
            'issued_date' => $issued->format('Y-m-d'),
            'expiry_date' => fake()->dateTimeBetween('-1 month', '+1 year')->format('Y-m-d'),
        ];
    }
}
