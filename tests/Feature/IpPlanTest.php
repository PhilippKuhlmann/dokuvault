<?php

use App\Models\Computer;
use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Router;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;

test('IP-Plan listet belegte Adressen und fasst freie Bereiche + DHCP zusammen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    Network::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'description' => 'Server-VLAN',
        'vlanId' => 30,
        'network' => '192.168.1.0',
        'cidr' => '24',
        'subnetmask' => '255.255.255.0',
        'gateway' => '192.168.1.1',
        'dhcpStart' => '100',
        'dhcpEnd' => '200',
    ]);

    // Adressen stehen nur noch im Block "Weitere IP-Adressen" - der IP-Plan
    // liest sie von dort.
    $rtr = Router::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'RTR-Core', 'port' => '443',
    ]);
    $rtr->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '192.168.1.1']);

    $srv = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-DC01', 'operating_system_id' => $os->id,
    ]);
    $srv->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '192.168.1.10']);

    $pc = Computer::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'PC-42', 'operating_system_id' => $os->id,
    ]);
    $pc->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '192.168.1.50']);

    $response = $this->get("/{$customer->slug}/ip-plan");
    $response->assertStatus(200);

    // Belegte Adressen mit Gerätenamen
    $response->assertSee('192.168.1.10');
    $response->assertSee('SRV-DC01');
    $response->assertSee('PC-42');
    $response->assertSee('Gateway');

    // Freier Bereich zwischen Gateway (.1) und Server (.10) zusammengefasst
    $response->assertSeeInOrder(['192.168.1.2', '192.168.1.9', 'frei'], false);

    // DHCP-Bereich zusammengefasst (.100 - .200)
    $response->assertSee('DHCP-Bereich');
    $response->assertSeeInOrder(['192.168.1.100', '192.168.1.200'], false);

    // Freier Bereich am Ende (.201 - .254)
    $response->assertSeeInOrder(['192.168.1.201', '192.168.1.254'], false);
});

/**
 * Ein Netz mit einem Server auf .10 und einem per DHCP versorgten Computer.
 */
function ipPlanMitGeraeten(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $netz = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.20.0.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.20.0.1', 'dhcpStart' => '100', 'dhcpEnd' => '200',
    ]);

    $srv = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-SPRUNG', 'operating_system_id' => $os->id,
    ]);
    $srv->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.20.0.10']);

    $pc = Computer::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'PC-DHCP', 'operating_system_id' => $os->id,
    ]);
    $pc->ipAddresses()->create(['customer_id' => $customer->id, 'dhcp' => true, 'network_id' => $netz->id]);

    return [$customer, $srv, $pc];
}

test('im IP-Plan führt ein Gerät per Klick auf seine Karte in der Liste', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'server_viewAny', 'computer_viewAny']));
    [$customer, $srv, $pc] = ipPlanMitGeraeten();

    $this->get("/{$customer->slug}/ip-plan")
        ->assertOk()
        // Feste Adresse und DHCP-Pool - beide springen auf die Karte.
        ->assertSee(route('server.index', [$customer, 'highlight' => $srv->id]), false)
        ->assertSee(route('computer.index', [$customer, 'highlight' => $pc->id]), false);
});

test('ohne Recht auf die Liste steht das Gerät im IP-Plan ohne Link', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $srv] = ipPlanMitGeraeten();

    $this->get("/{$customer->slug}/ip-plan")
        ->assertOk()
        ->assertSee('SRV-SPRUNG')
        ->assertDontSee(route('server.index', [$customer, 'highlight' => $srv->id]), false);
});

test('a device with an address inside the DHCP range does not split the range', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'description' => 'Server-VLAN',
        'network' => '10.10.250.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.10.250.254', 'dhcpStart' => '150', 'dhcpEnd' => '199',
    ]);
    // As the Proxmox agent reports a VM: an address, no DHCP flag.
    $vm = VM::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'test']);
    $vm->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.10.250.166']);

    $antwort = $this->get("/{$customer->slug}/ip-plan")->assertOk();

    $zeilen = collect($antwort->viewData('plans')->first()['plan']['rows']);
    $pool = $zeilen->where('kind', 'dhcp');
    expect($pool)->toHaveCount(1)
        ->and($pool->first()['from'])->toBe('10.10.250.150')
        ->and($pool->first()['to'])->toBe('10.10.250.199')
        ->and(collect($pool->first()['geraete'])->pluck('name')->all())->toBe(['test (10.10.250.166)'])
        ->and($zeilen->where('kind', 'device')->pluck('from')->all())->toBe(['10.10.250.254']);
});
