<?php

use App\Models\AgentToken;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\VM;
use App\Support\AgentAnzeige;

function proxmoxPayload(): array
{
    return [
        'host' => [
            'identifier' => 'machine-abc',
            'hostname' => 'pve01',
            'manufacturer' => 'Dell',
            'model' => 'PowerEdge R740',
            'serial' => 'SN12345',
            'ip' => '10.0.0.10',
            'pve_version' => '8.2.4',
            'kernel' => '6.8.4-2-pve',
            'cpu' => 'Intel Xeon Gold (40 Kerne)',
            'memory_gb' => 256,
            'storages' => [
                ['name' => 'local-lvm', 'type' => 'lvmthin', 'total_gb' => 1000, 'used_gb' => 400],
            ],
        ],
        'guests' => [
            ['identifier' => 'pve01/qemu/100', 'vmid' => 100, 'name' => 'web01', 'type' => 'qemu', 'os_id' => 'debian', 'os_version' => '12', 'ip' => '10.0.0.20', 'status' => 'running', 'cores' => 4, 'memory_gb' => 8],
            ['identifier' => 'pve01/lxc/200', 'vmid' => 200, 'name' => 'db01', 'type' => 'lxc', 'os_id' => 'debian', 'os_version' => '13', 'status' => 'running'],
        ],
    ];
}

/**
 * Der Betriebssystem-Katalog, soweit ein Test ihn braucht.
 *
 * Er wird ausdruecklich angelegt: Der Proxmox-Agent ergaenzt den Katalog
 * nicht, sondern ordnet nur zu. Was hier fehlt, bleibt am Objekt leer.
 */
function osKatalog(array $namen): void
{
    foreach ($namen as $name) {
        OperatingSystem::firstOrCreate(['name' => $name]);
    }
}

test('Proxmox-Agent legt Host als Server und Gäste als VMs an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'PVE');
    osKatalog(['Proxmox VE 8', 'Debian 12', 'Debian 13']);

    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'guests_documented' => 2]);

    $server = Server::where('customer_id', $customer->id)->where('agent_identifier', 'machine-abc')->first();
    expect($server)->not->toBeNull();
    expect($server->site_id)->toBe($site->id);
    expect($server->name)->toBe('pve01');
    expect($server->serialNumber)->toBe('SN12345');
    // Versionsspezifisch: 7/8/9 haben unterschiedliche Support-Enden, ein
    // Sammel-Eintrag "Proxmox VE" haette das nicht abbilden koennen.
    expect($server->operatingSystem->name)->toBe('Proxmox VE 8');
    expect(VM::where('server_id', $server->id)->count())->toBe(2);

    // Gast-IP landet im VM-Feld
    $vm = VM::where('agent_identifier', 'pve01/qemu/100')->first();
    // Die Adresse steht im Block, nicht mehr als Spalte am Geraet.
    expect($vm->ipAddresses()->pluck('address')->all())->toBe(['10.0.0.20']);

    // Der springende Punkt: 12 und 13 landen nicht beide unter "Debian" oder
    // gar "Linux". An der Version haengt das Support-Ende.
    expect($vm->operatingSystem->name)->toBe('Debian 12');
    expect(VM::where('agent_identifier', 'pve01/lxc/200')->first()->operatingSystem->name)->toBe('Debian 13');
});

test('Agent überschreibt manuell gepflegte Dienste nicht', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    // Erstlauf legt den Server an
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    $server = Server::where('agent_identifier', 'machine-abc')->first();

    // Nutzer trägt Rollen ein (komma-getrennt, so wie im Formular)
    $server->update(['services' => 'AD,DNS,DHCP,FS']);

    // Erneuter Agent-Lauf darf die Dienste nicht überschreiben
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();

    expect($server->fresh()->getRawOriginal('services'))->toBe('AD,DNS,DHCP,FS');
});

test('erneuter Lauf erzeugt keine Duplikate (Upsert)', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();

    expect(Server::where('agent_identifier', 'machine-abc')->count())->toBe(1);
    expect(VM::where('agent_identifier', 'pve01/qemu/100')->count())->toBe(1);
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/proxmox', [])->assertUnauthorized();

    $this->withToken('doc_falsch')
        ->postJson('/api/agent/proxmox', ['host' => ['identifier' => 'x', 'hostname' => 'y']])
        ->assertUnauthorized();
});

test('ordnet die gemeldete IP automatisch dem passenden VLAN zu', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    $vlan = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.0.0.0', 'cidr' => '24',
    ]);

    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();

    $server = Server::where('agent_identifier', 'machine-abc')->first();
    expect($server->ipAddresses()->first()->network_id)->toBe($vlan->id);
});

test('eine von Hand korrigierte VLAN-Zuordnung übersteht einen erneuten Lauf', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    $falschesVlan = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.0.0.0', 'cidr' => '24',
    ]);

    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    $server = Server::where('agent_identifier', 'machine-abc')->first();
    $server->ipAddresses()->first()->update(['network_id' => $falschesVlan->id]);

    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();

    expect($server->ipAddresses()->first()->fresh()->network_id)->toBe($falschesVlan->id);
});

test('ordnet die gemeldete Version dem passenden Proxmox-VE-Katalogeintrag zu', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    osKatalog(['Proxmox VE 7']);
    $payload = proxmoxPayload();
    $payload['host']['pve_version'] = '7.4-3';
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(Server::where('agent_identifier', 'machine-abc')->first()->operatingSystem->name)->toBe('Proxmox VE 7');
});

test('ohne auswertbare Version bleibt der Server ohne Betriebssystem', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    osKatalog(['Proxmox VE 8']);

    $payload = proxmoxPayload();
    unset($payload['host']['pve_version']);
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    // Ein unversioniertes "Proxmox VE" steht nicht im Katalog - und wird auch
    // nicht angelegt. Lieber leer als eine geratene Version.
    expect(Server::where('agent_identifier', 'machine-abc')->first()->operating_system_id)->toBeNull();
});

test('der Agent legt keine Betriebssysteme an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    osKatalog(['Proxmox VE 8', 'Debian 12', 'Debian 13']);
    $vorher = OperatingSystem::count();

    $payload = proxmoxPayload();
    // Weder das eine noch das andere steht im Katalog.
    $payload['host']['pve_version'] = '99.0';
    $payload['guests'][0]['os_id'] = 'alpine';
    $payload['guests'][0]['os_version'] = '3.20';
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(OperatingSystem::count())->toBe($vorher);
    expect(Server::where('agent_identifier', 'machine-abc')->first()->operating_system_id)->toBeNull();
    expect(VM::where('agent_identifier', 'pve01/qemu/100')->first()->operating_system_id)->toBeNull();
});

test('ein von Hand eingetragenes Betriebssystem übersteht einen Lauf ohne Treffer', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    osKatalog(['Proxmox VE 8']);

    $payload = proxmoxPayload();
    $payload['guests'][0]['os_id'] = 'alpine';
    $payload['guests'][0]['os_version'] = '3.20';
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    $handeintrag = OperatingSystem::create(['name' => 'Alpine Linux 3.20']);
    $vm = VM::where('agent_identifier', 'pve01/qemu/100')->first();
    $vm->update(['operating_system_id' => $handeintrag->id]);

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect($vm->fresh()->operating_system_id)->toBe($handeintrag->id);
});

test('Token aktualisiert last_used_at', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    expect($token->last_used_at)->toBeNull();
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    expect($token->fresh()->last_used_at)->not->toBeNull();
});

test('BIOS placeholders are not stored as hardware, and stored ones get cleared', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'pve');

    // Whitebox board: the system fields say nothing.
    $payload = proxmoxPayload();
    $payload['host']['manufacturer'] = 'System manufacturer';
    $payload['host']['model'] = 'System Product Name';
    $payload['host']['serial'] = 'System Serial Number';

    // An earlier run had stored exactly those placeholders.
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    $server = Server::where('agent_identifier', 'machine-abc')->sole();
    $server->update(['manufacturer' => 'System manufacturer', 'model' => 'To Be Filled By O.E.M.', 'serialNumber' => '0000000000']);

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    $server->refresh();
    expect($server->manufacturer)->toBeNull()
        ->and($server->model)->toBeNull()
        ->and($server->serialNumber)->toBeNull();
});

test('a value entered by hand survives a placeholder from the agent', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'pve');

    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    $server = Server::where('agent_identifier', 'machine-abc')->sole();
    $server->update(['manufacturer' => 'Eigenbau', 'model' => 'ASUS PRIME B550-PLUS']);

    $payload = proxmoxPayload();
    $payload['host']['manufacturer'] = 'To Be Filled By O.E.M.';
    $payload['host']['model'] = 'Default string';

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    $server->refresh();
    expect($server->manufacturer)->toBe('Eigenbau')
        ->and($server->model)->toBe('ASUS PRIME B550-PLUS')
        // Real serial from the payload still arrives.
        ->and($server->serialNumber)->toBe('SN12345');
});

test('a guest set to DHCP by hand stays DHCP when the agent reports its address again', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site);
    $netz = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.10.250.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
    ]);
    $meldung = ['host' => ['identifier' => 'pve-x', 'hostname' => 'pve01'], 'guests' => [
        ['identifier' => 'pve01/qemu/101', 'vmid' => 101, 'name' => 'test', 'type' => 'qemu', 'ip' => '10.10.250.166'],
    ]];

    $this->withToken($plain)->postJson('/api/agent/proxmox', $meldung)->assertOk();
    $vm = VM::where('agent_identifier', 'pve01/qemu/101')->sole();

    // Set to DHCP on the page: the row loses its address, keeps the network.
    $vm->ipAddresses()->sole()->update(['address' => null, 'dhcp' => true, 'network_id' => $netz->id]);

    $this->withToken($plain)->postJson('/api/agent/proxmox', $meldung)->assertOk();

    expect($vm->ipAddresses()->count())->toBe(1)
        ->and($vm->ipAddresses()->sole()->dhcp)->toBeTrue();
});

test('Proxmox tags that name a service of the catalog are added to the guest services', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site);
    foreach (['DNS', 'docker'] as $name) {
        Service::firstOrCreate(['name' => $name]);
    }
    $meldung = fn (array $tags) => ['host' => ['identifier' => 'pve-x', 'hostname' => 'pve01'], 'guests' => [
        ['identifier' => 'pve01/lxc/250016', 'vmid' => 250016, 'name' => 'dns01', 'type' => 'lxc', 'tags' => $tags],
    ]];

    $this->withToken($plain)->postJson('/api/agent/proxmox', $meldung(['linux', 'dns']))->assertOk();
    $vm = VM::where('agent_identifier', 'pve01/lxc/250016')->sole();
    // Catalog spelling, unknown tags ignored.
    expect($vm->getRawOriginal('services'))->toBe('DNS');

    // Added, never removed: a service entered by hand stays.
    $vm->update(['services' => 'DNS,Fileserver']);
    $this->withToken($plain)->postJson('/api/agent/proxmox', $meldung(['docker']))->assertOk();
    expect($vm->fresh()->getRawOriginal('services'))->toBe('DNS,Fileserver,docker');
});

/**
 * Customer, site and token for a Proxmox report in one go.
 */
function proxmoxToken(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'PVE');

    return [$customer, $site, $plain];
}

test('what the host and its guests report beyond the columns is kept', function () {
    [$customer, , $plain] = proxmoxToken();
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();

    $server = Server::where('agent_identifier', 'machine-abc')->sole();
    expect($server->agent_details)->toMatchArray([
        'pve_version' => '8.2.4', 'kernel' => '6.8.4-2-pve', 'cpu' => 'Intel Xeon Gold (40 Kerne)', 'memory_gb' => 256,
    ])
        ->and($server->agent_details['storages'][0]['name'])->toBe('local-lvm')
        ->and($server->agent_reported_at)->not->toBeNull();

    $vm = VM::where('agent_identifier', 'pve01/qemu/100')->sole();
    expect($vm->agent_details)->toHaveCount(5)->toMatchArray(['type' => 'qemu', 'vmid' => 100, 'status' => 'running', 'cores' => 4, 'memory_gb' => 8]);

    // Not sent: no entry instead of empty values.
    expect(VM::where('agent_identifier', 'pve01/lxc/200')->sole()->agent_details)
        ->not->toHaveKey('cores');
});

test('the server and VM cards show the report and say it came from the agent', function () {
    [$customer, , $plain] = proxmoxToken();
    $payload = proxmoxPayload();
    $payload['guests'][1]['memory_gb'] = 0.5;
    $payload['guests'][0]['cpu_pct'] = 12.3;
    $payload['guests'][0]['memory_used_gb'] = 6;
    $payload['guests'][1] += ['disk_gb' => 8, 'disk_used_gb' => 2.4];
    $payload['host']['storages'][] = ['name' => 'nas-aus', 'type' => 'nfs', 'total_gb' => 0, 'used_gb' => 0];
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    $this->actingAs(userWithPermissions(['server_viewAny', 'vm_viewAny']));
    $this->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertSee('6.8.4-2-pve')
        ->assertSee('256 GB')
        ->assertSee('8,5 GB laufend · 8,5 GB zugewiesen')
        ->assertSee('2 (1 VM, 1 LXC)')
        ->assertSee('400 GB / 1 TB')
        ->assertSee('40 %')
        ->assertSee('nicht verfügbar')
        ->assertDontSee('0 GB / 0 GB')
        ->assertSee('Vom Agent gemeldet am');
    $this->get("/{$customer->slug}/vm")
        ->assertOk()
        ->assertSee('title="Container (LXC)"', false)
        ->assertSee('title="Virtuelle Maschine"', false)
        // Symbol und Farbe je Typ: Zwei gleich graue Abzeichen unterschieden
        // sich nur im Wort, und man las in einer Liste jedes einzeln.
        ->assertSee('bg-cerulean-50', false)
        ->assertSee('bg-violet-50', false)
        ->assertSee('4 Kerne')
        ->assertSee('6 GB von 8 GB')
        ->assertDontSee('75 %')
        ->assertSee('12 %')
        ->assertSee('alle Kerne')
        ->assertSee('0,5 GB')
        ->assertDontSee('von 256 GB')
        ->assertSee('2,4 GB von 8 GB')
        ->assertSeeInOrder(['title="Container (LXC)"', 'läuft'], false)
        ->assertSee('Vom Agent gemeldet am');
});

test('a server entered by hand shows no agent note', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => null, 'name' => 'Handarbeit']);

    $this->actingAs(userWithPermissions(['server_viewAny']));
    $this->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertSee('Handarbeit')
        ->assertDontSee('Vom Agent gemeldet am');
});

test('a node creates its cluster and puts itself and its guests in it', function () {
    [$customer, $site, $plain] = proxmoxToken();
    $payload = proxmoxPayload();
    $payload['host']['cluster'] = 'pve-cluster';
    $payload['host']['storages'][] = ['name' => 'ceph-vm', 'type' => 'rbd', 'total_gb' => 3000, 'used_gb' => 100];

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)
        ->assertOk()->assertJson(['cluster' => 'pve-cluster']);

    $cluster = Cluster::where('customer_id', $customer->id)->sole();
    expect($cluster->name)->toBe('pve-cluster')
        ->and($cluster->type)->toBe('ceph')
        ->and($cluster->site_id)->toBe($site->id)
        ->and($cluster->agent_identifier)->toBe('proxmox/pve-cluster')
        ->and(Server::where('agent_identifier', 'machine-abc')->sole()->cluster_id)->toBe($cluster->id)
        ->and(VM::where('customer_id', $customer->id)->pluck('cluster_id')->unique()->all())->toBe([$cluster->id]);

    // The second node finds the same cluster.
    $payload['host']['identifier'] = 'machine-def';
    $payload['host']['hostname'] = 'pve02';
    $payload['guests'] = [];
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(Cluster::where('customer_id', $customer->id)->count())->toBe(1)
        ->and(Server::where('agent_identifier', 'machine-def')->sole()->cluster_id)->toBe($cluster->id);
});

test('a cluster created by hand is taken over, its type and assignments stay', function () {
    [$customer, $site, $plain] = proxmoxToken();
    $vonHand = Cluster::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Cluster', 'type' => 'shared_storage']);
    $anderer = Cluster::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'Anderer', 'type' => 'local']);
    $server = Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => null, 'agent_identifier' => 'machine-abc', 'cluster_id' => $anderer->id]);

    $payload = proxmoxPayload();
    $payload['host']['cluster'] = 'pve-cluster';
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(Cluster::where('customer_id', $customer->id)->count())->toBe(2)
        ->and($vonHand->fresh()->agent_identifier)->toBe('proxmox/pve-cluster')
        ->and($vonHand->fresh()->type)->toBe('shared_storage')
        ->and($server->fresh()->cluster_id)->toBe($anderer->id);
});

test('a single node without a cluster name creates no cluster', function () {
    [$customer, , $plain] = proxmoxToken();
    $payload = proxmoxPayload();
    $payload['host']['cluster'] = '';

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk()->assertJson(['cluster' => null]);

    expect(Cluster::where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('the cluster type follows replication and storage', function (array $host, string $typ) {
    [$customer, , $plain] = proxmoxToken();
    $payload = proxmoxPayload();
    $payload['host'] = array_merge($payload['host'], ['cluster' => 'c1'], $host);

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(Cluster::where('customer_id', $customer->id)->sole()->type)->toBe($typ);
})->with([
    'replication' => [['replication' => true], 'replication'],
    'nfs' => [['storages' => [['name' => 'nas', 'type' => 'nfs', 'total_gb' => 1, 'used_gb' => 0]]], 'shared_storage'],
    'pbs only' => [['storages' => [['name' => 'pbs', 'type' => 'pbs', 'total_gb' => 1, 'used_gb' => 0]]], 'local'],
]);

test('storage turns amber from 80 % and red from 90 %', function () {
    [$customer, , $plain] = proxmoxToken();
    $payload = proxmoxPayload();
    $payload['host']['storages'] = [
        ['name' => 'fast-voll', 'type' => 'dir', 'total_gb' => 100, 'used_gb' => 85],
        ['name' => 'voll', 'type' => 'dir', 'total_gb' => 100, 'used_gb' => 95],
    ];
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    $this->actingAs(userWithPermissions(['server_viewAny']));
    $this->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertSee('bg-amber-500', false)
        ->assertSee('bg-red-500', false);
});

test('sizes read like the rest of the app', function (float $gb, string $text) {
    expect(AgentAnzeige::gb($gb))->toBe($text);
})->with([
    [8, '8 GB'],
    [24.5, '24,5 GB'],
    [0.5, '0,5 GB'],
    [1006, '1 TB'],
    [5440, '5,4 TB'],
]);

test('a VM at its memory limit stays neutral, a container warns', function () {
    [$customer, , $plain] = proxmoxToken();
    $payload = proxmoxPayload();
    $payload['guests'][0] += ['memory_used_gb' => 8, 'cpu_pct' => 1];
    $payload['guests'][1] += ['memory_gb' => 1, 'memory_used_gb' => 0.95];
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    $karten = AgentAnzeige::vm(VM::where('agent_identifier', 'pve01/qemu/100')->sole());
    $ram = collect($karten['System'])->firstWhere(0, 'Arbeitsspeicher');
    expect($ram[2]['warnen'])->toBeFalse();

    $karten = AgentAnzeige::vm(VM::where('agent_identifier', 'pve01/lxc/200')->sole());
    $ram = collect($karten['System'])->firstWhere(0, 'Arbeitsspeicher');
    expect($ram[2]['warnen'])->toBeTrue();
});
