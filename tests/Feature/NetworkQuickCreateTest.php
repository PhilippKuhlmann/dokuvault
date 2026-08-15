<?php

use App\Livewire\NetworkQuickCreate;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Ein VLAN anlegen, ohne die Seite zu wechseln. Dieselbe Komponente an zwei
 * Stellen: am Geraet (Standort ist vorgegeben) und ueber der VLAN-Liste
 * (Standort wird gewaehlt).
 */
function netzUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('mit vorgegebenem Standort entfaellt die Auswahl und das Netz erbt ihn', function () {
    $this->actingAs(userWithPermissions(['network_create']));
    [$customer, $site] = netzUmgebung();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer, 'siteId' => $site->id])
        ->set('description', 'DMZ')
        ->set('vlanId', 90)
        ->set('network', '10.10.90.0')
        ->set('cidr', 24)
        ->set('gateway', '10.10.90.1')
        ->set('dns1', '10.10.30.10')
        ->set('dhcpStart', '10.10.90.100')
        ->set('dhcpEnd', '10.10.90.200')
        ->call('speichern')
        ->assertHasNoErrors()
        // Der IP-Block haengt daran und waehlt das neue Netz aus.
        ->assertDispatched('vlan-angelegt');

    $netz = Network::where('description', 'DMZ')->firstOrFail();
    expect($netz->customer_id)->toBe($customer->id);
    expect($netz->site_id)->toBe($site->id);
    expect($netz->vlanId)->toBe(90);
    expect($netz->cidr)->toBe('24');
    expect($netz->gateway)->toBe('10.10.90.1');
    expect($netz->dhcpEnd)->toBe('10.10.90.200');
});

test('ohne vorgegebenen Standort ist die Auswahl Pflicht', function () {
    $this->actingAs(userWithPermissions(['network_create']));
    [$customer] = netzUmgebung();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->set('description', 'Ohne Standort')
        ->set('network', '10.10.91.0')
        ->call('speichern')
        ->assertHasErrors('site_id');

    expect(Network::count())->toBe(0);
});

test('ein Standort eines fremden Kunden wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['network_create']));
    [$customer] = netzUmgebung();

    $fremd = Customer::factory()->create();
    $fremdeSite = Site::factory()->create(['customer_id' => $fremd->id]);

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->set('site_id', $fremdeSite->id)
        ->set('description', 'Geklaut')
        ->set('network', '10.10.92.0')
        ->call('speichern')
        ->assertHasErrors('site_id');

    expect(Network::count())->toBe(0);
});

test('ohne network_create wird nichts angelegt', function () {
    $this->actingAs(userWithPermissions([]));
    [$customer, $site] = netzUmgebung();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer, 'siteId' => $site->id])
        ->set('description', 'Heimlich')
        ->set('network', '10.10.99.0')
        ->call('speichern')
        ->assertForbidden();

    expect(Network::count())->toBe(0);
});

test('die VLAN-Liste bindet das Modal ein', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_create']));
    [$customer] = netzUmgebung();

    // In der Liste traegt der Knopf die Beschriftung des bisherigen "Neu" -
    // gleiche Stelle, gleiche Optik, nur ohne Seitenwechsel.
    $inhalt = $this->get("/{$customer->slug}/network")->assertOk()->getContent();

    expect($inhalt)->toContain("wire:click=\"\$set('offen', true)\"");
    expect($inhalt)->toContain('bg-cerulean-600');
    // Der alte Weg ist nicht mehr verlinkt.
    expect($inhalt)->not->toContain('/network/create');
});
