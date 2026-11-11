<?php

namespace App\Http\Controllers\API\Agent\Concerns;

use App\Models\Network;

/**
 * Where a reported device is reachable: its management address and whether
 * that address comes from DHCP.
 *
 * Shared by everything that reports a device with an address - hosts,
 * guests, clients, firewalls, UniFi devices.
 */
trait ReportsAddresses
{
    /**
     * Traegt die gemeldete Adresse im Block "Weitere IP-Adressen" ein.
     *
     * Kein updateOrCreate: Der Agent meldet denselben Host wieder und wieder -
     * bei einer schon vorhandenen Adresse bleibt die Netz-Zuordnung deshalb
     * unangetastet, sonst wuerfe ein zweiter Lauf eine von Hand korrigierte
     * Zuordnung wieder um (Ueberlappende Netze sind selten, aber moeglich).
     * Nur bei der Neuanlage wird ein passendes Netz gesucht und gesetzt.
     */
    protected function reportUrl($geraet, int $customerId, ?int $siteId, ?string $adresse, ?bool $dhcp = null): void
    {
        $adresse = trim((string) $adresse);

        if ($adresse === '') {
            return;
        }

        // Die gemeldete Adresse dient hier nur noch dazu, das Netz zu finden.
        $netz = Network::forUrl($customerId, $siteId, $adresse)?->id;

        if ($dhcp === true) {
            $this->meldeDhcp($geraet, $customerId, $netz);

            return;
        }

        $vorhanden = $geraet->ipAddresses()->where('address', $adresse)->first();

        if ($vorhanden) {
            $vorhanden->update(['customer_id' => $customerId]);

            // Die Bezeichnung bleibt in jedem Fall unberuehrt - sie gehoert
            // dem Nutzer.
            if ($dhcp === false && $vorhanden->istDhcp()) {
                $vorhanden->update(['dhcp' => false]);
            }

            return;
        }

        // The agent cannot tell (Proxmox, Hyper-V report a guest's address
        // without knowing where it came from), and someone set the device
        // to DHCP in this network: that stays. Before, every run added the
        // reported address again as a fixed one next to the DHCP entry - the
        // choice made by hand was undone within the hour.
        if ($dhcp === null && $geraet->ipAddresses()->where('dhcp', true)
            ->when($netz, fn ($q) => $q->where('network_id', $netz))->exists()) {
            return;
        }

        // Aus DHCP wurde eine feste Adresse: Die adresslose Zeile wird zur
        // festen, statt eine zweite danebenzustellen.
        if ($dhcp === false && $alt = $geraet->ipAddresses()->where('dhcp', true)->first()) {
            $alt->update([
                'customer_id' => $customerId,
                'network_id' => $netz,
                'address' => $adresse,
                'dhcp' => false,
            ]);

            return;
        }

        $geraet->ipAddresses()->create([
            'address' => $adresse,
            'customer_id' => $customerId,
            'network_id' => $netz,
            'dhcp' => false,
        ]);
    }

    /**
     * Ein per DHCP versorgtes Gerät: Netz ja, Adresse nein.
     *
     * Welche Adresse es gerade hat, ist morgen eine andere - sie zu speichern
     * hiesse, etwas festzuhalten, das nicht haelt. Was bleibt, ist das Netz.
     *
     * Erst die vorhandene DHCP-Zeile, dann die unter der gemeldeten Adresse:
     * So wird beim Wechsel von fest auf DHCP die alte Zeile umgewandelt, statt
     * dass Alt und Neu nebeneinander stehen bleiben.
     */
    protected function meldeDhcp($geraet, int $customerId, ?int $netz): void
    {
        $vorhanden = $geraet->ipAddresses()->where('dhcp', true)->first()
            ?: $geraet->ipAddresses()->whereNotNull('address')->first();

        if ($vorhanden) {
            $vorhanden->update([
                'customer_id' => $customerId,
                'network_id' => $netz,
                'address' => null,
                'dhcp' => true,
            ]);

            return;
        }

        $geraet->ipAddresses()->create([
            'address' => null,
            'customer_id' => $customerId,
            'network_id' => $netz,
            'dhcp' => true,
        ]);
    }
}
