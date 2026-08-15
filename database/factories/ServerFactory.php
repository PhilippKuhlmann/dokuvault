<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    public function definition()
    {
        [$manufacturer, $model] = fake()->randomElement([
            ['Dell', 'PowerEdge R650'],
            ['Dell', 'PowerEdge R750'],
            ['HPE', 'ProLiant DL360 Gen10'],
            ['HPE', 'ProLiant DL380 Gen11'],
            ['Lenovo', 'ThinkSystem SR650'],
            ['Fujitsu', 'PRIMERGY RX2540 M6'],
        ]);

        // Meist 19 Zoll, ab und zu ein Standserver - so sieht man in der Demo
        // beide Faelle und den Filter im Rack-Editor.
        $bauform = fake()->randomElement(['rack', 'rack', 'rack', 'tower']);

        return [
            'form_factor' => $bauform,
            'full_depth' => $bauform === 'rack' ? fake()->boolean(80) : true,
            'height_units' => $bauform === 'rack' ? fake()->randomElement([1, 1, 1, 2, 2, 4]) : 1,
            'name' => 'SRV-'.fake()->randomElement(['DC01', 'FS01', 'HV01', 'APP01', 'SQL01', 'BAK01']),
            'manufacturer' => $manufacturer,
            'model' => $model,
            'serialNumber' => strtoupper(fake()->bothify('??######')),
            'bmcIp' => fake()->localIpv4(),
            'bmcUser' => 'root',
            'bmcPassword' => fake()->password(10, 14),
            'services' => fake()->randomElement(['Hyper-V,DNS,AD', 'Fileserver,DFS', 'SQL,Backup', 'RDS,Print']),
            'operating_system_id' => fake()->numberBetween(1, 10),
            'remoteID' => fake()->numberBetween(100000000, 999999999),
            'remotePassword' => fake()->password(10, 14),
        ];
    }
}
