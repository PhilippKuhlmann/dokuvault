<?php

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use App\Support\ServerMap;

/**
 * The server map: cluster -> hosts -> guests, each with what it is made of.
 * Built from documented data and the agents' reports; configured values
 * only, no load.
 */
function serverMapFixture(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale']);
    $cluster = Cluster::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Cluster', 'type' => 'ceph',
    ]);

    $host = Server::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'cluster_id' => $cluster->id,
        'name' => 'PVE-01', 'manufacturer' => 'Dell', 'model' => 'PowerEdge R750',
        'agent_details' => ['cpu' => 'Intel Xeon Silver 4314', 'cores' => 32, 'memory_gb' => 256, 'pve_version' => '9.2.3'],
        'agent_reported_at' => now(),
    ]);
    $host->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.30.13']);

    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022 Standard']);
    $dc = VM::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'cluster_id' => $cluster->id, 'server_id' => $host->id,
        'name' => 'VM-DC01', 'operating_system_id' => $os->id,
        'agent_details' => ['type' => 'qemu', 'status' => 'running', 'cores' => 4, 'memory_gb' => 8, 'cpu_pct' => 63.5, 'memory_used_gb' => 7.9],
        'agent_reported_at' => now(),
    ]);
    $dc->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.30.20']);

    VM::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'cluster_id' => $cluster->id, 'server_id' => $host->id,
        'name' => 'CT-Alt',
        'agent_details' => ['type' => 'lxc', 'status' => 'stopped', 'cores' => 1, 'memory_gb' => 0.5, 'disk_gb' => 8],
        'agent_reported_at' => now(),
    ]);

    return [$customer, $site, $cluster, $host];
}

test('the server map shows cluster, host and guests with what they are made of', function () {
    [$customer] = serverMapFixture();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny', 'cluster_viewAny']));

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSeeInOrder(['PVE-Cluster', '1 Host · 32 Kerne · 256 GB RAM', 'PVE-01', 'Dell PowerEdge R750', 'Intel Xeon Silver 4314', '32 Kerne · 256 GB RAM'])
        ->assertSee('10.0.30.13')
        ->assertSee('8 GB laufend · 8,5 GB vergeben')
        // VMs and containers each on a branch of their own below the host.
        ->assertSeeInOrder(['network-map-branches', '>VMs<', '· 1', 'VM-DC01', '10.0.30.20', 'Windows Server 2022 Standard', '4 Kerne', '8 GB RAM'], false)
        ->assertSeeInOrder(['network-map-branch', '>Container<', '· 1', 'CT-Alt', 'aus', '1 Kern', '0,5 GB RAM', '8 GB Disk'], false);
});

test('load values the agents report stay off the map', function () {
    [$customer] = serverMapFixture();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    // cpu_pct 63.5 and memory_used_gb 7.9 of VM-DC01: documentation, not monitoring.
    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertDontSee('63,5')
        ->assertDontSee('63.5')
        ->assertDontSee('7,9 GB');
});

test('servers without guests and guests without a host are listed, not dropped', function () {
    [$customer, $site] = serverMapFixture();
    Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-FS01']);
    VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'VM-Waise']);
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSeeInOrder(['Server ohne Gäste', 'SRV-FS01'])
        ->assertSeeInOrder(['VMs ohne Host', 'VM-Waise']);
});

test('a guest of the cluster without a known host hangs below the cluster', function () {
    [$customer, $site, $cluster] = serverMapFixture();
    VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'cluster_id' => $cluster->id, 'name' => 'VM-Wandert']);
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSeeInOrder(['Host unbekannt', 'VM-Wandert'])
        ->assertDontSee('VMs ohne Host');
});

test('a host with many guests folds the rest away', function () {
    [$customer, $site, $cluster, $host] = serverMapFixture();
    foreach (range(1, 12) as $i) {
        VM::forceCreate([
            'customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id,
            'name' => sprintf('VM-%02d', $i), 'agent_details' => ['type' => 'qemu', 'status' => 'running'],
        ]);
    }
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    // 13 VMs: 8 open, 5 in the fold - all of them on the page.
    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSee('+ 5 weitere')
        ->assertSee('VM-12');
});

test('without the right to see VMs only the number of guests shows', function () {
    [$customer] = serverMapFixture();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSee('PVE-01')
        ->assertSeeInOrder(['>VMs<', '· 1'], false)
        ->assertDontSee('VM-DC01')
        ->assertDontSee('CT-Alt');
});

test('without the right to see servers the map is closed', function () {
    [$customer] = serverMapFixture();
    $this->actingAs(userWithPermissions(['vm_viewAny']));

    $this->get(route('servermap.index', $customer))->assertForbidden();
});

test('the site filter of the sidebar applies', function () {
    [$customer] = serverMapFixture();
    $filiale = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Filiale']);
    Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $filiale->id, 'name' => 'SRV-FILIALE']);
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $this->withSession(['site' => $filiale->id])->get(route('servermap.index', $customer))->assertOk()
        ->assertSee('SRV-FILIALE')
        ->assertDontSee('PVE-01');
});

test('stopped guests do not make a host look overbooked', function () {
    [$customer, $site, , $host] = serverMapFixture();
    $host->update(['agent_details' => ['cores' => 8, 'memory_gb' => 16]]);
    // 8 GB running (VM-DC01), 0.5 GB stopped (CT-Alt), plus 40 GB more stopped.
    $archiv = VM::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id,
        'name' => 'VM-Archiv', 'agent_details' => ['type' => 'qemu', 'status' => 'stopped', 'memory_gb' => 40],
    ]);
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $assigned = app(ServerMap::class)->build($customer)[0]['columns'][0]['hosts'][0]['assigned'];

    expect($assigned['over'])->toBeFalse()
        ->and($assigned['pct'])->toBe(50.0)
        ->and($assigned['dazu'])->toBe(50.0)
        ->and($assigned['text'])->toBe('8 GB laufend · 48,5 GB vergeben');

    // Running guests alone beyond what is built in: amber.
    $archiv->update(['agent_details' => ['type' => 'qemu', 'status' => 'running', 'memory_gb' => 40]]);
    $assigned = app(ServerMap::class)->build($customer)[0]['columns'][0]['hosts'][0]['assigned'];

    expect($assigned['over'])->toBeTrue();
});

test('an operating system out of support carries the EOL badge of the lists', function () {
    [$customer, $site, , $host] = serverMapFixture();
    $alt = OperatingSystem::factory()->create(['name' => 'Windows Server 2012 R2', 'eol_date' => '2023-10-10']);
    VM::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id,
        'name' => 'VM-Altlast', 'operating_system_id' => $alt->id,
    ]);
    $host->update(['operating_system_id' => OperatingSystem::factory()->create(['name' => 'Proxmox VE 7', 'eol_date' => '2024-07-31'])->id]);
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    // Host first, then the guest - each with its badge; VM-DC01 has none.
    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSeeInOrder(['PVE-01', 'Proxmox VE 7', 'Support endete am 31.07.2024'])
        ->assertSeeInOrder(['VM-Altlast', 'Windows Server 2012 R2', 'Support endete am 10.10.2023']);
});

test('the services of hosts and guests stand on the map', function () {
    [$customer, , , $host] = serverMapFixture();
    $host->update(['services' => 'Proxmox,Ceph']);
    VM::where('name', 'VM-DC01')->first()->update(['services' => 'DC, DNS,']);
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $map = app(ServerMap::class)->build($customer);
    $pve = $map[0]['columns'][0]['hosts'][0];

    // Trimmed, empty entries gone.
    expect($pve['services'])->toBe(['Proxmox', 'Ceph'])
        ->and($pve['guests']['groups'][0]['guests'][0]['services'])->toBe(['DC', 'DNS']);

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSeeInOrder(['PVE-01', '>Proxmox<', '>Ceph<'], false)
        ->assertSeeInOrder(['VM-DC01', '>DC<', '>DNS<'], false);
});

test('the sidebar links to the server map', function () {
    [$customer] = serverMapFixture();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('server.index', $customer))->assertOk()
        ->assertSee(route('servermap.index', $customer), false);
});
