<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;

/**
 * Die Nutzlast entspricht dem, was vmware-doku.ps1 aus einer Antwort von
 * /api/vcenter/host und /api/vcenter/vm baut. Sie ist nachgestellt, nicht von
 * einem echten vCenter aufgezeichnet - das Skript selbst ist gegen ein echtes
 * vCenter noch nicht gelaufen.
 */
function vmwarePayload(): array
{
    return [
        'host' => [
            'identifier' => 'host-1042',
            'hostname' => 'esx-01.kunde.local',
            'os' => 'VMware ESXi',
        ],
        'guests' => [
            [
                'identifier' => 'vm-2201',
                'name' => 'SRV-APP01',
                'type' => 'vmware',
                'os' => 'Windows',
                'status' => 'POWERED_ON',
                'cores' => 8,
                'memory_gb' => 32,
            ],
            [
                'identifier' => 'vm-2202',
                'name' => 'SRV-DB01',
                'type' => 'vmware',
                'os' => 'Linux',
                'status' => 'POWERED_ON',
                'cores' => 4,
                'memory_gb' => 16,
            ],
        ],
    ];
}

test('VMware-Agent legt Host und VMs beim Kunden des Tokens an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'vCenter');

    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'server' => 'esx-01.kunde.local', 'guests_documented' => 2]);

    $server = Server::where('customer_id', $customer->id)->where('agent_identifier', 'host-1042')->first();
    expect($server)->not->toBeNull();
    expect($server->operatingSystem->name)->toBe('VMware ESXi');

    $vms = VM::where('customer_id', $customer->id)->get();
    expect($vms)->toHaveCount(2);
    expect($vms->pluck('server_id')->unique()->all())->toBe([$server->id]);
    expect($vms->firstWhere('agent_identifier', 'vm-2202')->operatingSystem->name)->toBe('Linux');
});

test('meldet vCenter Hersteller und Seriennummer nicht, bleibt Nachgetragenes stehen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())->assertOk();

    $server = Server::where('agent_identifier', 'host-1042')->first();
    $server->update(['manufacturer' => 'Fujitsu', 'serialNumber' => 'YM4K002233']);

    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())->assertOk();

    // Wuerden nicht gemeldete Felder stur mit null geschrieben, waere die von
    // Hand nachgetragene Seriennummer nach dem naechsten Lauf weg.
    expect($server->fresh()->manufacturer)->toBe('Fujitsu');
    expect($server->fresh()->serialNumber)->toBe('YM4K002233');
});

test('erneuter Lauf aktualisiert, statt zu verdoppeln', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())->assertOk();
    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())->assertOk();

    expect(Server::where('agent_identifier', 'host-1042')->count())->toBe(1);
    expect(VM::where('customer_id', $customer->id)->count())->toBe(2);
});

test('zwei Hosts desselben vCenter bleiben zwei Server', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())->assertOk();

    $zweiter = vmwarePayload();
    $zweiter['host']['identifier'] = 'host-1043';
    $zweiter['host']['hostname'] = 'esx-02.kunde.local';
    $zweiter['guests'] = [[
        'identifier' => 'vm-2301', 'name' => 'SRV-TERM', 'type' => 'vmware',
        'os' => 'Windows', 'status' => 'POWERED_ON', 'cores' => 8, 'memory_gb' => 64,
    ]];
    $this->withToken($plain)->postJson('/api/agent/vmware', $zweiter)->assertOk();

    expect(Server::where('customer_id', $customer->id)->count())->toBe(2);

    $esx02 = Server::where('agent_identifier', 'host-1043')->first();
    expect(VM::where('agent_identifier', 'vm-2301')->first()->server_id)->toBe($esx02->id);
});

test('ein Token trifft kein Geraet eines anderen Kunden', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();
    $standortA = Site::factory()->create(['customer_id' => $kundeA->id]);
    [$token, $plain] = AgentToken::generateFor($kundeA, $standortA);

    $this->withToken($plain)->postJson('/api/agent/vmware', vmwarePayload())->assertOk();

    expect(Server::where('customer_id', $kundeB->id)->count())->toBe(0);
    expect(VM::where('customer_id', $kundeB->id)->count())->toBe(0);
});

test('ohne Pflichtfelder: 422', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/vmware', ['host' => ['identifier' => 'host-1']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('host.hostname');
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/vmware', [])->assertUnauthorized();
    $this->withToken('doc_falsch')->postJson('/api/agent/vmware', vmwarePayload())->assertUnauthorized();
});
