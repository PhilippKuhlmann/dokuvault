<?php

use App\Models\AgentToken;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;

function windowsServerPayload(array $rollen = []): array
{
    return [
        'server' => [
            'identifier' => 'guid-srv-01',
            'hostname' => 'SRV-DC-01',
            'manufacturer' => 'HPE',
            'model' => 'ProLiant DL360 Gen10',
            'serial' => 'CZ21120ABC',
            'os' => 'Microsoft Windows Server 2022 Standard',
            'ip' => '10.0.0.5',
            'cpu' => 'Intel Xeon Gold 5218 (32 Kerne)',
            'memory_gb' => 128,
            'roles' => $rollen ?: ['AD-Domain-Services', 'DNS', 'FileAndStorage-Services', 'PowerShell'],
        ],
    ];
}

test('Windows-Server-Agent legt einen Server an, keinen Computer', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'DC');

    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'server' => 'SRV-DC-01']);

    $server = Server::where('customer_id', $customer->id)->where('agent_identifier', 'guid-srv-01')->first();
    expect($server)->not->toBeNull();
    expect($server->manufacturer)->toBe('HPE');
    expect($server->operatingSystem->name)->toBe('Windows Server 2022 Standard');
    expect($server->ipAddresses()->first()->address)->toBe('10.0.0.5');

    // Genau das war der Fehler des Windows-Client-Agenten: er legte auch auf
    // einem Server einen Computer an.
    expect(Computer::where('customer_id', $customer->id)->count())->toBe(0);
});

test('uebernimmt nur Rollen, die der Dienstekatalog auch fuehrt', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    Service::firstOrCreate(['name' => 'AD']);
    Service::firstOrCreate(['name' => 'DNS']);
    // 'Fileserver' fehlt im Katalog - die gemeldete Rolle wird verworfen,
    // statt einen Katalogeintrag anzulegen, den niemand gepflegt hat.
    Service::where('name', 'Fileserver')->delete();

    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())->assertOk();

    $server = Server::where('agent_identifier', 'guid-srv-01')->first();
    expect($server->services)->toBe(['AD', 'DNS']);
    expect(Service::where('name', 'Fileserver')->exists())->toBeFalse();
    // 'PowerShell' ist keine Rolle, die etwas ueber die Aufgabe aussagt.
    expect(Service::where('name', 'PowerShell')->exists())->toBeFalse();
});

test('ueberschreibt selbst gepflegte Dienste nicht', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    Service::firstOrCreate(['name' => 'AD']);
    Service::firstOrCreate(['name' => 'DNS']);

    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())->assertOk();

    $server = Server::where('agent_identifier', 'guid-srv-01')->first();
    $server->update(['services' => 'AD,DNS,Backup']);

    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())->assertOk();

    expect($server->fresh()->services)->toBe(['AD', 'DNS', 'Backup']);
});

test('erneuter Lauf aktualisiert, statt zu verdoppeln', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())->assertOk();
    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())->assertOk();

    expect(Server::where('agent_identifier', 'guid-srv-01')->count())->toBe(1);
});

test('ein Token trifft kein Geraet eines anderen Kunden', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();
    $standortA = Site::factory()->create(['customer_id' => $kundeA->id]);
    [$token, $plain] = AgentToken::generateFor($kundeA, $standortA);

    $this->withToken($plain)->postJson('/api/agent/windows-server', windowsServerPayload())->assertOk();

    expect(Server::where('customer_id', $kundeB->id)->count())->toBe(0);
    expect(Server::where('customer_id', $kundeA->id)->count())->toBe(1);
});

test('ohne Pflichtfelder: 422', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/windows-server', ['server' => ['identifier' => 'x']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('server.hostname');
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/windows-server', [])->assertUnauthorized();
    $this->withToken('doc_falsch')->postJson('/api/agent/windows-server', windowsServerPayload())->assertUnauthorized();
});
