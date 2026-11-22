<?php

use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;

/*
 * Design 1A: the cards of the object lists fold - only the head line shows
 * until a card is opened. Nine servers fully open were six screens of
 * scrolling to find one IP.
 */

function foldingCustomer(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('a server card folds and carries model and services in its head line', function () {
    [$customer, $site] = foldingCustomer();
    $os = OperatingSystem::factory()->create(['name' => 'Proxmox VE 9']);
    Server::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
        'name' => 'PVE-01', 'manufacturer' => 'Dell', 'model' => 'PowerEdge R750', 'form_factor' => 'rack', 'services' => 'Proxmox, Ceph',
    ]);
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $html = $this->get(route('server.index', $customer))->assertOk()
        ->assertSee('x-data="karte"', false)
        ->assertSee('Details ein- oder ausklappen')
        ->assertSee('Alle aufklappen')
        ->assertSee('kartenliste(', false)
        ->assertSee('karten-offen:server')
        ->getContent();

    // Model under the name, services before the body begins - the folded
    // line has to tell one server from the next.
    $kopf = substr($html, strpos($html, 'PVE-01'), strpos($html, 'data-karte-inhalt') - strpos($html, 'PVE-01'));
    expect($kopf)->toContain('Dell PowerEdge R750')
        ->and($kopf)->toContain('>Proxmox<')
        ->and($kopf)->toContain('>Ceph<');

    // The services stand only once now: no "Dienste" block in the body.
    expect(substr_count($html, '>Proxmox<'))->toBe(1);
});

test('a VM card folds and shows its services in the head line', function () {
    [$customer, $site] = foldingCustomer();
    VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'VM-DC01', 'services' => 'AD, DNS']);
    $this->actingAs(userWithPermissions(['vm_viewAny']));

    $html = $this->get(route('vm.index', $customer))->assertOk()
        ->assertSee('x-data="karte"', false)
        ->getContent();

    expect(strpos($html, '>AD<'))->toBeLessThan(strpos($html, 'data-karte-inhalt'))
        ->and(substr_count($html, '>DNS<'))->toBe(1);
});

test('the VLAN list folds as well', function () {
    [$customer, $site] = foldingCustomer();
    Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'vlanId' => 10, 'description' => 'Server']);
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $this->get(route('network.index', $customer))->assertOk()
        ->assertSee('x-data="karte"', false)
        ->assertSee('karten-offen:network')
        ->assertSee('Alle aufklappen');
});

test('plain cards arrange their content themselves and do not fold', function () {
    [$customer, $site] = foldingCustomer();
    Rack::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'MUC-01', 'height_units' => 24]);
    $this->actingAs(userWithPermissions(['rack_viewAny']));

    $this->get(route('rack.index', $customer))->assertOk()
        ->assertSee('MUC-01')
        ->assertDontSee('x-data="karte"', false);
});
