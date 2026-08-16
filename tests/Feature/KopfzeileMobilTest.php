<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

/**
 * Die Kopfzeile einer Geraetekarte auf schmalen Bildschirmen.
 *
 * In text-2xl brach ein Name wie srv-hyperv-01.mustermann.local mitten
 * durch, und das Betriebssystem dahinter lag unter dem Bearbeiten-Knopf.
 */
test('der Name der Kopfzeile ist auf schmalen Bildschirmen kleiner gesetzt', function () {
    $this->actingAs(userWithPermissions(['server_viewAny', 'server_update']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Server::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'srv-hyperv-01.mustermann.local',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id,
    ]);

    $inhalt = $this->get(route('server.index', $customer))->assertOk()->getContent();

    // Zwei Groessen statt einer festen: klein am Telefon, unveraendert ab 640 px.
    expect($inhalt)->toContain('text-base')->toContain('sm:text-2xl');

    // Ohne min-w-0 schiebt ein Flex-Kind seine Nachbarn aus der Karte, statt
    // umzubrechen - dann liegt das Betriebssystem unter dem Stift.
    expect($inhalt)->toContain('min-w-0 flex-wrap items-center');

    // Und die alte feste Groesse steht nicht mehr im Kopf.
    expect($inhalt)->not->toContain('items-center text-2xl');
});
