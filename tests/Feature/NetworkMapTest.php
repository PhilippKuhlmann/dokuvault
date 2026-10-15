<?php

use App\Models\Computer;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\InternetConnection;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Router;
use App\Models\Site;
use App\Support\NetworkMap;

/**
 * The network map is assembled from documented data only: a VLAN hangs below
 * the device holding its gateway address, a device below the VLAN its address
 * lies in, an internet connection above the site's only firewall.
 */
function networkMapFixture(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale']);

    $firewall = Firewall::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'FW-Zentrale',
    ]);
    $firewall->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.10.1']);

    $network = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Clients', 'vlanId' => 10,
        'network' => '10.0.10.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.0.10.1',
    ]);

    $computer = Computer::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PC-Empfang',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows 11'])->id,
    ]);
    $computer->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.10.50']);

    InternetConnection::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'provider' => 'Telekom',
    ]);

    return [$customer, $site, $firewall, $network, $computer];
}

test('VLAN haengt unter dem Geraet mit der Gateway-Adresse, Geraete im VLAN', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = networkMapFixture();

    $map = app(NetworkMap::class)->build($customer, $site->id);
    $column = $map[0]['columns'][0];

    expect($column['gateway']['name'])->toBe('FW-Zentrale')
        ->and($column['uplinks'][0]['name'])->toBe('Telekom')
        ->and($column['networks'][0]['network']->description)->toBe('Clients')
        ->and($column['networks'][0]['groups'][0]['label'])->toBe('Computer')
        ->and($column['networks'][0]['groups'][0]['devices'][0]['name'])->toBe('PC-Empfang')
        ->and($column['networks'][0]['groups'][0]['devices'][0]['address'])->toBe('10.0.10.50');

    // The gateway stands above the VLAN, not a second time inside it.
    expect(collect($column['networks'][0]['groups'])->pluck('label'))->not->toContain('Firewall');
});

test('Netzplan-Seite zeigt Standort, Firewall, VLAN und Geraet', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer] = networkMapFixture();

    $this->withSession(['site' => 'all'])
        ->get("/{$customer->slug}/network-map")
        ->assertOk()
        ->assertSee('Zentrale')
        ->assertSee('FW-Zentrale')
        ->assertSee('Telekom')
        ->assertSee('Clients')
        ->assertSee('PC-Empfang');
});

test('Gateway ohne dokumentiertes Geraet erscheint als Luecke', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = networkMapFixture();

    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Gaeste', 'vlanId' => 99,
        'network' => '192.168.99.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '192.168.99.254',
    ]);

    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Kameras', 'vlanId' => 98,
        'network' => '192.168.98.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '192.168.98.254',
    ]);

    $columns = app(NetworkMap::class)->build($customer, $site->id)[0]['columns'];

    // Documented devices come first, all unknown gateways share one column.
    expect($columns)->toHaveCount(2)
        ->and($columns[0]['gateway']['name'])->toBe('FW-Zentrale')
        ->and($columns[1]['gateway'])->toBeNull()
        ->and(collect($columns[1]['networks'])->pluck('network.description')->all())->toBe(['Kameras', 'Gaeste']);
});

test('Firewall ohne VLAN und Anschluss steht in der Liste statt als leere Spalte', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = networkMapFixture();

    Firewall::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'FW-Reserve']);

    $entry = app(NetworkMap::class)->build($customer, $site->id)[0];

    expect($entry['columns'])->toHaveCount(1)
        ->and($entry['standalone'][0]['name'])->toBe('FW-Reserve');
});

test('Geraet ausserhalb jedes VLANs steht unter ohne Zuordnung', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, , , $computer] = networkMapFixture();

    $computer->ipAddresses()->first()->update(['address' => '172.16.0.5']);

    $entry = app(NetworkMap::class)->build($customer, $site->id)[0];

    expect($entry['unassigned'][0]['name'])->toBe('PC-Empfang')
        ->and($entry['columns'][0]['networks'][0]['groups'])->toBe([]);
});

test('Internetanschluss bei mehreren Firewalls nur ueber die WAN-IP zugeordnet', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = networkMapFixture();

    $router = Router::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'Fritzbox',
    ]);

    // Two edge devices, no address match: not drawn to a guess.
    $entry = app(NetworkMap::class)->build($customer, $site->id)[0];
    expect($entry['unlinkedUplinks'][0]['name'])->toBe('Telekom');

    $router->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '203.0.113.10']);
    InternetConnection::where('customer_id', $customer->id)->update(['wan_ip' => '203.0.113.10']);

    $entry = app(NetworkMap::class)->build($customer, $site->id)[0];
    $routerColumn = collect($entry['columns'])->firstWhere('gateway.name', 'Fritzbox');

    expect($entry['unlinkedUplinks'])->toBe([])
        ->and($routerColumn['uplinks'][0]['name'])->toBe('Telekom');
});

test('Standortfilter blendet andere Standorte aus', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = networkMapFixture();
    Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Filiale Nord']);

    $this->withSession(['site' => $site->id])
        ->get("/{$customer->slug}/network-map")
        ->assertOk()
        // Not assertDontSee: the sidebar's site picker names every site.
        ->assertViewHas('sites', fn (array $sites) => count($sites) === 1 && $sites[0]['site']->is($site));
});

test('Netzplan braucht das Recht VLAN ansehen', function () {
    $this->actingAs(userWithPermissions([]));
    [$customer] = networkMapFixture();

    $this->get("/{$customer->slug}/network-map")->assertForbidden();
});
