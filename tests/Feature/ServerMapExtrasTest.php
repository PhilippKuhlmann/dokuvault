<?php

use App\Models\Backup;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use App\Support\ServerMap;
use Illuminate\Support\Facades\View;

/*
 * The additions to the server map: CPU and memory by hand where no agent
 * reports, the storages of hosts and clusters, a switch for stopped
 * guests, a hint at guests no vzdump job takes along, and the map in the
 * PDF.
 */

function extrasCustomer(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale']);

    return [$customer, $site];
}

/** A Proxmox guest as the agent reports it: "<host>/<type>/<vmid>". */
function extrasGuest(Customer $customer, Site $site, array $attributes, int $vmid, string $status = 'running'): VM
{
    return VM::forceCreate(array_merge([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'agent_identifier' => 'pve01.example.local/qemu/'.$vmid,
        'agent_details' => ['type' => 'qemu', 'vmid' => $vmid, 'status' => $status, 'cores' => 2, 'memory_gb' => 4],
        'agent_reported_at' => now(),
    ], $attributes));
}

function extrasGuestOf(array $sites, string $name): array
{
    foreach ($sites as $entry) {
        $lists = [$entry['unhosted']];
        foreach ($entry['columns'] as $column) {
            $lists[] = $column['loose'];
            foreach ($column['hosts'] as $host) {
                $lists[] = $host['guests'];
            }
        }
        foreach (array_filter($lists) as $list) {
            foreach ($list['groups'] as $group) {
                foreach ($group['guests'] as $guest) {
                    if ($guest['name'] === $name) {
                        return $guest;
                    }
                }
            }
        }
    }

    throw new RuntimeException("Gast $name nicht auf dem Plan");
}

test('the server form saves CPU, cores and memory by hand', function () {
    [$customer, $site] = extrasCustomer();
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['server_viewAny', 'server_create']));

    imModal('server', $customer, [
        'site_id' => $site->id, 'name' => 'NAS-Box', 'form_factor' => 'tower', 'operating_system_id' => $os->id,
        'cpu' => 'Intel Xeon E-2334', 'cores' => '4', 'memory_gb' => '32',
    ])->assertHasNoErrors();

    $server = Server::where('name', 'NAS-Box')->sole();
    expect($server->cpu)->toBe('Intel Xeon E-2334')
        ->and((int) $server->cores)->toBe(4)
        ->and((float) $server->memory_gb)->toBe(32.0);

    imModal('server', $customer, [
        'site_id' => $site->id, 'name' => 'X', 'form_factor' => 'tower', 'operating_system_id' => $os->id,
        'cores' => 'viele', 'memory_gb' => '-1',
    ])->assertHasErrors(['form.cores', 'form.memory_gb']);
});

test('the VM form saves cores, memory and disk by hand, empty stays empty', function () {
    [$customer, $site] = extrasCustomer();
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['vm_viewAny', 'vm_create']));

    imModal('vm', $customer, [
        'site_id' => $site->id, 'name' => 'vServer', 'kind' => 'vm', 'operating_system_id' => $os->id,
        'cores' => '2', 'memory_gb' => '0.5', 'disk_gb' => '',
    ])->assertHasNoErrors();

    $vm = VM::where('name', 'vServer')->sole();
    expect((int) $vm->cores)->toBe(2)
        ->and((float) $vm->memory_gb)->toBe(0.5)
        ->and($vm->disk_gb)->toBeNull();
});

test('values entered by hand stand on the map where no agent reports, the agent wins otherwise', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $host = Server::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'HV-01',
        'cpu' => 'AMD EPYC 7313', 'cores' => 16, 'memory_gb' => 128,
    ]);
    VM::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id,
        'name' => 'VM-Hand', 'cores' => 4, 'memory_gb' => 16, 'disk_gb' => 200,
    ]);
    // Typed in once, reported since: the report is what is true now.
    VM::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id,
        'name' => 'VM-Agent', 'cores' => 1, 'memory_gb' => 1,
        'agent_details' => ['status' => 'running', 'cores' => 8, 'memory_gb' => 32], 'agent_reported_at' => now(),
    ]);

    $sites = app(ServerMap::class)->build($customer);
    $hv = $sites[0]['columns'][0]['hosts'][0];

    expect($hv['cpu'])->toBe('AMD EPYC 7313')
        ->and($hv['facts'])->toBe(['16 Kerne', '128 GB RAM'])
        ->and($hv['assigned']['text'])->toBe('48 GB von 128 GB an Gäste vergeben')
        ->and(extrasGuestOf($sites, 'VM-Hand')['specs'])->toBe(['4 Kerne', '16 GB RAM', '200 GB Disk'])
        ->and(extrasGuestOf($sites, 'VM-Agent')['specs'])->toBe(['8 Kerne', '32 GB RAM']);

    // The cards show what was typed in.
    $this->get(route('server.index', $customer))->assertOk()->assertSee('AMD EPYC 7313')->assertSee('128 GB');
    $this->get(route('vm.index', $customer))->assertOk()->assertSee('Ressourcen')->assertSee('200 GB');
});

test('a host lists its storages by size, a cluster the shared ones once', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny', 'cluster_viewAny']));

    $cluster = Cluster::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Cluster']);
    $storages = fn (int $local) => [
        ['name' => 'local-lvm', 'type' => 'lvmthin', 'total_gb' => $local, 'used_gb' => 10],
        ['name' => 'ceph-vm', 'type' => 'rbd', 'total_gb' => 11500, 'used_gb' => 4000],
        // Not reachable: pvesm reports 0 - left out, not "0 GB".
        ['name' => 'nas-iso', 'type' => 'nfs', 'total_gb' => 0, 'used_gb' => 0],
    ];
    foreach (['PVE-01' => 832, 'PVE-02' => 900] as $name => $local) {
        $host = Server::forceCreate([
            'customer_id' => $customer->id, 'site_id' => $site->id, 'cluster_id' => $cluster->id, 'name' => $name,
            'agent_details' => ['storages' => $storages($local)], 'agent_reported_at' => now(),
        ]);
        VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id, 'cluster_id' => $cluster->id, 'name' => 'VM-'.$name]);
    }
    $alone = Server::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Solo',
        'agent_details' => ['storages' => [['name' => 'local', 'type' => 'dir', 'total_gb' => 94], ['name' => 'pbs', 'type' => 'pbs', 'total_gb' => 9200]]],
        'agent_reported_at' => now(),
    ]);
    VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $alone->id, 'name' => 'VM-Solo']);

    $sites = app(ServerMap::class)->build($customer);
    $column = $sites[0]['columns'][0];

    expect(array_column($column['cluster']['storages'], 'size', 'name'))->toBe(['ceph-vm' => '11,5 TB'])
        ->and(array_column($column['hosts'][0]['storages'], 'size', 'name'))->toBe(['local-lvm' => '832 GB'])
        ->and(array_column($column['hosts'][1]['storages'], 'size', 'name'))->toBe(['local-lvm' => '900 GB'])
        // Outside a cluster the host carries all of them.
        ->and(array_column($sites[0]['columns'][1]['hosts'][0]['storages'], 'size', 'name'))->toBe(['local' => '94 GB', 'pbs' => '9,2 TB']);

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSeeInOrder(['PVE-Cluster', 'Speicher', 'ceph-vm', '11,5 TB', 'PVE-01', 'local-lvm', '832 GB'])
        ->assertDontSee('nas-iso');
});

test('the switch hides stopped guests and says how many', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-01']);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-Laeuft'], 100);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-Vorlage'], 9000, 'stopped');
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'CT-Aus', 'kind' => 'lxc'], 200, 'stopped');

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSee('VM-Vorlage')->assertSee('CT-Aus')
        ->assertSee('aria-checked="false"', false);

    $this->get(route('servermap.index', [$customer, 'running' => 1]))->assertOk()
        ->assertSee('VM-Laeuft')
        ->assertDontSee('VM-Vorlage')
        // A kind with nothing left goes as a whole.
        ->assertDontSee('CT-Aus')->assertDontSee('>Container<', false)
        ->assertSee('2 ausgeschaltete Gäste ausgeblendet')
        ->assertSee('aria-checked="true"', false);
});

test('a host whose guests are all stopped stays on the map when they are hidden', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Ruhe']);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-Schlaf'], 100, 'stopped');

    $this->get(route('servermap.index', [$customer, 'running' => 1]))->assertOk()
        ->assertSee('PVE-Ruhe')
        ->assertDontSee('VM-Schlaf')
        ->assertSee('1 ausgeschalteter Gast ausgeblendet');
});

test('a guest no vzdump job of its cluster or host takes along is marked', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny', 'cluster_viewAny']));

    $cluster = Cluster::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Cluster', 'agent_identifier' => 'proxmox/pvec01',
    ]);
    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'cluster_id' => $cluster->id, 'name' => 'PVE-01']);
    $in = fn (string $name, int $vmid) => extrasGuest($customer, $site, ['server_id' => $host->id, 'cluster_id' => $cluster->id, 'name' => $name], $vmid);

    $in('VM-Gesichert', 100);
    $in('VM-Ausgenommen', 101);
    $in('VM-Einzeln', 250);
    // Entered by hand, no vmid: nothing to tell.
    VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'server_id' => $host->id, 'name' => 'VM-Hand']);

    Backup::forceCreate(['customer_id' => $customer->id, 'name' => 'Alle', 'agent_identifier' => 'proxmox/pvec01/backup-1', 'source' => 'alle Gaeste ausser 101, 250']);
    Backup::forceCreate(['customer_id' => $customer->id, 'name' => 'Einzeln', 'agent_identifier' => 'proxmox/pvec01/backup-2', 'source' => 'VMs 250']);
    // Another customer's job never counts.
    Backup::forceCreate(['customer_id' => Customer::factory()->create()->id, 'name' => 'Fremd', 'agent_identifier' => 'proxmox/pvec01/backup-3', 'source' => 'alle Gaeste']);

    $sites = app(ServerMap::class)->build($customer);

    expect(extrasGuestOf($sites, 'VM-Gesichert')['backup'])->toBeTrue()
        ->and(extrasGuestOf($sites, 'VM-Ausgenommen')['backup'])->toBeFalse()
        ->and(extrasGuestOf($sites, 'VM-Einzeln')['backup'])->toBeTrue()
        ->and(extrasGuestOf($sites, 'VM-Hand')['backup'])->toBeNull()
        ->and($sites[0]['columns'][0]['hosts'][0]['guests']['unprotected'])->toBe(1);

    $this->get(route('servermap.index', $customer))->assertOk()
        ->assertSee('1 ohne Backup')
        ->assertSeeInOrder(['VM-Ausgenommen', 'kein Backup']);
});

test('a standalone host is matched by the name its guests carry, a pool job decides nothing', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-01']);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-Drin'], 100);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-Draussen'], 101);

    Backup::forceCreate(['customer_id' => $customer->id, 'name' => 'Job', 'agent_identifier' => 'proxmox/pve01.example.local/backup-1', 'source' => 'VMs 100']);

    $sites = app(ServerMap::class)->build($customer);
    expect(extrasGuestOf($sites, 'VM-Drin')['backup'])->toBeTrue()
        ->and(extrasGuestOf($sites, 'VM-Draussen')['backup'])->toBeFalse();

    // Which guests a pool holds is not reported: no hint rather than a wrong one.
    Backup::forceCreate(['customer_id' => $customer->id, 'name' => 'Pool', 'agent_identifier' => 'proxmox/pve01.example.local/backup-2', 'source' => 'Pool produktiv']);

    $sites = app(ServerMap::class)->build($customer);
    expect(extrasGuestOf($sites, 'VM-Draussen')['backup'])->toBeNull();
});

test('without a reported job for the cluster or host there is no hint', function () {
    [$customer, $site] = extrasCustomer();
    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));

    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-01']);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-Veeam'], 100);
    // A job of another host: says nothing about this one.
    Backup::forceCreate(['customer_id' => $customer->id, 'name' => 'Job', 'agent_identifier' => 'proxmox/pve02.example.local/backup-1', 'source' => 'alle Gaeste']);

    expect(extrasGuestOf(app(ServerMap::class)->build($customer), 'VM-Veeam')['backup'])->toBeNull();

    $this->get(route('servermap.index', $customer))->assertOk()->assertDontSee('kein Backup');
});

test('the PDF carries the server map with all guests, as far as the orderer may see', function () {
    [$customer, $site] = extrasCustomer();
    $host = Server::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-01',
        'agent_details' => ['cores' => 32, 'memory_gb' => 256, 'storages' => [['name' => 'local-lvm', 'type' => 'lvmthin', 'total_gb' => 832]]],
        'agent_reported_at' => now(),
    ]);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'VM-DC01'], 100);
    extrasGuest($customer, $site, ['server_id' => $host->id, 'name' => 'CT-Alt', 'kind' => 'lxc'], 200, 'stopped');

    $render = function (array $permissions) use ($customer) {
        View::share('pdfOwner', userWithPermissions($permissions));

        try {
            return view('pdf.customer', ['customer' => $customer, 'svgDir' => sys_get_temp_dir(), 'toc' => []])->render();
        } finally {
            View::share('pdfOwner', null);
        }
    };

    $html = $render(['server_viewAny', 'vm_viewAny']);
    expect($html)->toContain('data-toc="Serverplan"')
        ->and($html)->toContain('Speicher: local-lvm 832 GB')
        ->and($html)->toContain('VM-DC01')
        ->and($html)->toContain('CT-Alt')
        ->and($html)->toContain('smap-stopped');

    // Without the VM right only the number; without the server right nothing.
    $html = $render(['server_viewAny']);
    expect($html)->toContain('data-toc="Serverplan"')->and($html)->toContain('2 Gäste')->and($html)->not->toContain('VM-DC01');

    expect($render(['vm_viewAny']))->not->toContain('data-toc="Serverplan"');
});
