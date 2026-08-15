<?php

namespace Database\Factories;

use App\Models\IoTDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

class IoTDeviceFactory extends Factory
{
    protected $model = IoTDevice::class;

    public function definition(): array
    {
        [$name,$m,$mo] = fake()->randomElement([
            ['Türsprechanlage', 'Doorbird', 'D1101V'],
            ['Zutrittskontrolle', 'PCS Systemtechnik', 'INTUS 5540'],
            ['Klimasensor', 'Shelly', 'Plus H&T'],
            ['Zeiterfassung', 'Reiner SCT', 'timeCard 6'],
            ['Smart-TV Besprechung', 'Samsung', 'QM85R'],
        ]);

        return [
            'name' => $name, 'manufacturer' => $m, 'model' => $mo,
            'serialNumber' => strtoupper(fake()->bothify('??####-######')),
            'url' => 'http://'.fake()->localIpv4(),
            'username' => 'admin', 'password' => fake()->password(8, 14),
        ];
    }
}
