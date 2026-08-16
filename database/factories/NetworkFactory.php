<?php

namespace Database\Factories;

use App\Models\Network;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Network>
 */
class NetworkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /**
     * Gaengige VLAN-Zwecke mit fester VLAN-ID. Ein Personenname als VLAN-Bezeichnung
     * (fake()->name()) sieht im IP-Plan wie ein Datenfehler aus - deshalb ein fester Pool.
     */
    private const VLANS = [
        ['VoIP', 40], ['WLAN-Gast', 50], ['Drucker', 60], ['Kameras', 70],
        ['Backup', 80], ['DMZ', 90], ['Gebäudetechnik', 100], ['Verwaltung', 110],
        ['Produktion', 120], ['Lager', 130],
    ];

    private static int $next = 0;

    public function definition()
    {
        // Reihum statt zufaellig: so bekommt ein Seed-Lauf verschiedene VLANs,
        // ohne dass fake()->unique() bei mehr als zehn Netzen ueberlaeuft.
        [$name, $vlanId] = self::VLANS[self::$next++ % count(self::VLANS)];
        $net = '10.'.fake()->numberBetween(20, 60).'.'.$vlanId;

        return [
            'description' => $name,
            'vlanId' => $vlanId,
            'network' => $net.'.0',
            'subnetmask' => '255.255.255.0',
            'cidr' => '24',
            'gateway' => $net.'.1',
            'dns1' => $net.'.10',
            'dns2' => '8.8.8.8',
            // Volle Adressen, keine blossen Oktette: Genau das verlangt auch
            // das VLAN-Formular (ipv4).
            'dhcpStart' => $net.'.100',
            'dhcpEnd' => $net.'.250',
        ];
    }
}
