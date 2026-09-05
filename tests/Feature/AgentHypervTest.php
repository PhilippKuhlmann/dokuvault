<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;

function hypervPayload(): array
{
    return [
        'host' => [
            'identifier' => 'guid-hv-01',
            'hostname' => 'HV-01',
            'manufacturer' => 'Dell Inc.',
            'model' => 'PowerEdge R650',
            'serial' => 'JK7Y2X3',
            'os' => 'Microsoft Windows Server 2022 Standard',
            'ip' => '10.0.0.10',
            'cpu' => 'Intel Xeon Silver 4310 (24 Kerne)',
            'memory_gb' => 256,
        ],
        'guests' => [
            [
                'identifier' => 'vm-guid-1',
                'name' => 'SRV-FILE',
                'type' => 'hyperv',
                'os' => 'Microsoft Windows Server 2019 Standard',
                'ip' => '10.0.0.20',
                'status' => 'Running',
                'cores' => 4,
                'memory_gb' => 16,
            ],
            [
                'identifier' => 'vm-guid-2',
                'name' => 'SRV-DOCKER',
                'type' => 'hyperv',
                'os' => null,
                'ip' => null,
                'status' => 'Off',
                'cores' => 2,
                'memory_gb' => 8,
            ],
        ],
    ];
}

test('Hyper-V-Agent legt Host und VMs beim Kunden des Tokens an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'HV-Host');

    $this->withToken($plain)->postJson('/api/agent/hyperv', hypervPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'server' => 'HV-01', 'guests_documented' => 2]);

    $server = Server::where('customer_id', $customer->id)->where('agent_identifier', 'guid-hv-01')->first();
    expect($server)->not->toBeNull();
    expect($server->site_id)->toBe($site->id);
    expect($server->manufacturer)->toBe('Dell Inc.');
    expect($server->serialNumber)->toBe('JK7Y2X3');
    // Das "Microsoft "-Praefix wird gekappt, wie beim Windows-Client-Agenten.
    expect($server->operatingSystem->name)->toBe('Windows Server 2022 Standard');

    $vms = VM::where('customer_id', $customer->id)->get();
    expect($vms)->toHaveCount(2);
    expect($vms->pluck('server_id')->unique()->all())->toBe([$server->id]);

    $datei = $vms->firstWhere('agent_identifier', 'vm-guid-1');
    expect($datei->name)->toBe('SRV-FILE');
    expect($datei->operatingSystem->name)->toBe('Windows Server 2019 Standard');

    // Ohne gemeldetes Betriebssystem wird nicht geraten.
    expect($vms->firstWhere('agent_identifier', 'vm-guid-2')->operatingSystem->name)->toBe('Unbekannt');

    expect(IpAddress::where('ipable_type', Server::class)->where('ipable_id', $server->id)->first()->address)
        ->toBe('10.0.0.10');
});

test('ordnet die gemeldeten IPs dem passenden VLAN zu', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    $vlan = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.0.0.0', 'cidr' => '24',
    ]);

    $this->withToken($plain)->postJson('/api/agent/hyperv', hypervPayload())->assertOk();

    $server = Server::where('agent_identifier', 'guid-hv-01')->first();
    expect($server->ipAddresses()->first()->network_id)->toBe($vlan->id);
});

test('erneuter Lauf aktualisiert, statt zu verdoppeln', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/hyperv', hypervPayload())->assertOk();

    $geaendert = hypervPayload();
    $geaendert['host']['hostname'] = 'HV-01-NEU';
    $this->withToken($plain)->postJson('/api/agent/hyperv', $geaendert)->assertOk();

    expect(Server::where('agent_identifier', 'guid-hv-01')->count())->toBe(1);
    expect(VM::where('customer_id', $customer->id)->count())->toBe(2);
    expect(Server::where('agent_identifier', 'guid-hv-01')->first()->name)->toBe('HV-01-NEU');
    expect(IpAddress::where('address', '10.0.0.10')->count())->toBe(1);
});

test('ein Token trifft kein Geraet eines anderen Kunden', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();
    $standortA = Site::factory()->create(['customer_id' => $kundeA->id]);

    $fremder = Server::create([
        'customer_id' => $kundeB->id,
        'site_id' => Site::factory()->create(['customer_id' => $kundeB->id])->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => 'Fremder Host',
        'agent_identifier' => 'guid-hv-01',
        'form_factor' => 'rack',
        'height_units' => 1,
    ]);

    [$token, $plain] = AgentToken::generateFor($kundeA, $standortA);
    $this->withToken($plain)->postJson('/api/agent/hyperv', hypervPayload())->assertOk();

    expect($fremder->fresh()->name)->toBe('Fremder Host')
        ->and($fremder->fresh()->customer_id)->toBe($kundeB->id);
    expect(Server::where('customer_id', $kundeA->id)->where('agent_identifier', 'guid-hv-01')->exists())->toBeTrue();
});

test('ohne Pflichtfelder: 422', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/hyperv', ['host' => ['hostname' => 'ohne Kennung']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('host.identifier');
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/hyperv', [])->assertUnauthorized();
    $this->withToken('doc_falsch')->postJson('/api/agent/hyperv', hypervPayload())->assertUnauthorized();
});
