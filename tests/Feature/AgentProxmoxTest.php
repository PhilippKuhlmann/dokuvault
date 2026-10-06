<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\VM;

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
