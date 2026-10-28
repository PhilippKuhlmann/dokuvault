<?php

use App\Models\AgentToken;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Site;

function windowsClientPayload(): array
{
    return [
        'client' => [
            'identifier' => 'guid-machine-01',
            'hostname' => 'PC-VERTRIEB-01',
            'manufacturer' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'serial' => 'PF12345',
            'os' => 'Microsoft Windows 11 Pro',
            'ip' => '10.0.0.55',
        ],
    ];
}

test('Windows-Client-Agent legt den Rechner beim Kunden des Tokens an', function () {
    // Agents only pick from the catalog - the entry exists, as it does on
    // a real installation (seeder or admin area).
    OperatingSystem::firstOrCreate(['name' => 'Windows 11 Pro']);

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'Rollout-Skript');

    $this->withToken($plain)->postJson('/api/agent/windows-client', windowsClientPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'client' => 'PC-VERTRIEB-01']);

    $computer = Computer::where('customer_id', $customer->id)->where('agent_identifier', 'guid-machine-01')->first();
    expect($computer)->not->toBeNull();
    expect($computer->site_id)->toBe($site->id);
    expect($computer->name)->toBe('PC-VERTRIEB-01');
    expect($computer->manufacturer)->toBe('Lenovo');
    expect($computer->model)->toBe('ThinkPad T14');
    expect($computer->serialNumber)->toBe('PF12345');
    // Win32_OperatingSystem.Caption liefert immer das "Microsoft "-Praefix,
    // der Katalog fuehrt Windows-Systeme ohne - das Praefix wird gekappt.
    expect($computer->operatingSystem->name)->toBe('Windows 11 Pro');

    $adresse = IpAddress::where('ipable_id', $computer->id)->where('ipable_type', Computer::class)->first();
    expect($adresse->address)->toBe('10.0.0.55');
});

test('trifft ein bereits vorhandenes Betriebssystem ohne "Microsoft"-Präfix statt einen Karteileiche anzulegen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    $vorhanden = OperatingSystem::factory()->create(['name' => 'Windows 11 Pro']);

    $this->withToken($plain)->postJson('/api/agent/windows-client', windowsClientPayload())->assertOk();

    expect(OperatingSystem::where('name', 'Windows 11 Pro')->count())->toBe(1);
    $computer = Computer::where('agent_identifier', 'guid-machine-01')->first();
    expect($computer->operating_system_id)->toBe($vorhanden->id);
});

test('ordnet die gemeldete IP automatisch dem passenden VLAN zu', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    $vlan = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.0.0.0', 'cidr' => '24',
    ]);
    // Ein Netz an einem anderen Standort desselben Kunden darf nicht treffen.
    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'network' => '10.0.1.0', 'cidr' => '24',
    ]);

    $this->withToken($plain)->postJson('/api/agent/windows-client', windowsClientPayload())->assertOk();

    $computer = Computer::where('agent_identifier', 'guid-machine-01')->first();
    expect($computer->ipAddresses()->first()->network_id)->toBe($vlan->id);
});

test('erneuter Lauf erzeugt keine Duplikate und ueberschreibt manuelle Angaben nicht sinnwidrig', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/windows-client', windowsClientPayload())->assertOk();
    $this->withToken($plain)->postJson('/api/agent/windows-client', windowsClientPayload())->assertOk();

    expect(Computer::where('agent_identifier', 'guid-machine-01')->count())->toBe(1);
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/windows-client', [])->assertUnauthorized();

    $this->withToken('doc_falsch')
        ->postJson('/api/agent/windows-client', windowsClientPayload())
        ->assertUnauthorized();
});

test('an agent never adds an operating system to the shared catalog', function () {
    // The catalog is shared by every customer: a name an agent (or whoever
    // holds an agent token) makes up must not appear there.
    [$token, $plain] = AgentToken::generateFor(
        $customer = Customer::factory()->create(),
        Site::factory()->create(['customer_id' => $customer->id]),
    );
    $before = OperatingSystem::count();

    $this->withToken($plain)->postJson('/api/agent/windows-client', ['client' => [
        'identifier' => 'pc-unbekannt', 'hostname' => 'PC-UNBEKANNT', 'os' => 'Microsoft Erfundenes OS 3000',
    ]])->assertOk();

    expect(OperatingSystem::count())->toBe($before)
        ->and(OperatingSystem::where('name', 'Erfundenes OS 3000')->exists())->toBeFalse()
        ->and(Computer::where('agent_identifier', 'pc-unbekannt')->sole()->operating_system_id)->toBeNull();
});

test('an operating system chosen by hand survives an unknown name from the agent', function () {
    $os = OperatingSystem::firstOrCreate(['name' => 'Windows 11 Pro']);
    [$token, $plain] = AgentToken::generateFor(
        $customer = Customer::factory()->create(),
        $site = Site::factory()->create(['customer_id' => $customer->id]),
    );
    Computer::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PC-1',
        'agent_identifier' => 'pc-1', 'operating_system_id' => $os->id,
    ]);

    $this->withToken($plain)->postJson('/api/agent/windows-client', ['client' => [
        'identifier' => 'pc-1', 'hostname' => 'PC-1', 'os' => 'Microsoft Erfundenes OS 3000',
    ]])->assertOk();

    expect(Computer::where('agent_identifier', 'pc-1')->sole()->operating_system_id)->toBe($os->id);
});
