<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Server;
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
            ['identifier' => 'pve01/qemu/100', 'vmid' => 100, 'name' => 'web01', 'type' => 'qemu', 'ostype' => 'l26', 'ip' => '10.0.0.20', 'status' => 'running', 'cores' => 4, 'memory_gb' => 8],
            ['identifier' => 'pve01/lxc/200', 'vmid' => 200, 'name' => 'db01', 'type' => 'lxc', 'ostype' => 'debian', 'status' => 'running'],
        ],
    ];
}

test('Proxmox-Agent legt Host als Server und Gäste als VMs an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'PVE');

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

    $payload = proxmoxPayload();
    $payload['host']['pve_version'] = '7.4-3';
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(Server::where('agent_identifier', 'machine-abc')->first()->operatingSystem->name)->toBe('Proxmox VE 7');
});

test('ohne auswertbare Version faellt der Katalogeintrag auf den Sammel-Namen zurück', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $payload = proxmoxPayload();
    unset($payload['host']['pve_version']);
    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)->assertOk();

    expect(Server::where('agent_identifier', 'machine-abc')->first()->operatingSystem->name)->toBe('Proxmox VE');
});

test('Token aktualisiert last_used_at', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    expect($token->last_used_at)->toBeNull();
    $this->withToken($plain)->postJson('/api/agent/proxmox', proxmoxPayload())->assertOk();
    expect($token->fresh()->last_used_at)->not->toBeNull();
});
