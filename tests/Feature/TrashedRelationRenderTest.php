<?php

use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;
use App\Models\Wifi;

test('WLAN mit Netzwerk im Papierkorb: Liste und Bearbeiten laden ohne Fehler', function () {
    $this->actingAs(userWithPermissions(['wifi_viewAny', 'wifi_update']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $network = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);

    $wifi = Wifi::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'network_id' => $network->id,
    ]);

    // Netzwerk in den Papierkorb -> $wifi->network liefert null.
    // Vorher stürzten Liste (network->vlanId) und Bearbeiten (network->id) ab.
    $network->delete();

    $this->get("/{$customer->slug}/wifi")->assertStatus(200);
    expect(modalHtml('wifi', $customer, $wifi->id))->not->toBe('');
});
