<?php

use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;

function vlan(array $overrides = []): Network
{
    if (! isset($overrides['customer_id']) || ! isset($overrides['site_id'])) {
        $customer = Customer::factory()->create();
        $overrides['customer_id'] ??= $customer->id;
        $overrides['site_id'] ??= Site::factory()->create(['customer_id' => $customer->id])->id;
    }

    return Network::factory()->create(array_merge([
        'network' => '10.20.40.0',
        'subnetmask' => '255.255.255.0',
        'cidr' => '24',
    ], $overrides));
}

test('trifft eine Adresse innerhalb des Bereichs, inklusive Netz- und Broadcast-Adresse', function () {
    $netz = vlan();

    expect($netz->enthaeltAdresse('10.20.40.0'))->toBeTrue(); // Netzadresse
    expect($netz->enthaeltAdresse('10.20.40.1'))->toBeTrue(); // Gateway
    expect($netz->enthaeltAdresse('10.20.40.254'))->toBeTrue();
    expect($netz->enthaeltAdresse('10.20.40.255'))->toBeTrue(); // Broadcast
});

test('verfehlt Adressen ausserhalb des Bereichs knapp', function () {
    $netz = vlan();

    expect($netz->enthaeltAdresse('10.20.39.255'))->toBeFalse();
    expect($netz->enthaeltAdresse('10.20.41.0'))->toBeFalse();
});

test('leitet die Praefixlaenge auch aus der Subnetzmaske ab, ohne cidr-Feld', function () {
    $netz = vlan(['cidr' => null, 'subnetmask' => '255.255.255.0']);

    expect($netz->enthaeltAdresse('10.20.40.50'))->toBeTrue();
    expect($netz->enthaeltAdresse('10.20.41.50'))->toBeFalse();
});

test('ohne Netzadresse oder Praefixlaenge gibt es keinen Bereich', function () {
    expect(vlan(['network' => null])->bereich())->toBeNull();
    expect(vlan(['cidr' => null, 'subnetmask' => null])->bereich())->toBeNull();
});

test('fuerAdresse findet das passende Netz nur am richtigen Standort des Kunden', function () {
    $customer = Customer::factory()->create();
    $site1 = Site::factory()->create(['customer_id' => $customer->id]);
    $site2 = Site::factory()->create(['customer_id' => $customer->id]);
    $treffer = vlan(['customer_id' => $customer->id, 'site_id' => $site1->id]);
    vlan(['customer_id' => $customer->id, 'site_id' => $site2->id, 'network' => '10.99.0.0']);

    $gefunden = Network::fuerAdresse($customer->id, $site1->id, '10.20.40.50');

    expect($gefunden?->id)->toBe($treffer->id);
    expect(Network::fuerAdresse($customer->id, $site2->id, '10.20.40.50'))->toBeNull();
});

test('fuerAdresse liefert null bei einer ungueltigen Adresse oder ohne Treffer', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    vlan(['customer_id' => $customer->id, 'site_id' => $site->id]);

    expect(Network::fuerAdresse($customer->id, $site->id, 'keine-ip'))->toBeNull();
    expect(Network::fuerAdresse($customer->id, $site->id, '192.168.1.1'))->toBeNull();
});
