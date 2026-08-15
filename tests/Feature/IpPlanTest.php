<?php

use App\Models\Computer;
use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Router;
use App\Models\Server;
use App\Models\Site;

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
        'username' => 'admin', 'password' => 'x',
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
