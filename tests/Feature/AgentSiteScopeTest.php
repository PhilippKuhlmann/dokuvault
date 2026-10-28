<?php

use App\Models\Accesspoint;
use App\Models\AgentToken;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Site;

// Security audit hardening note: an agent token was bound to its customer,
// not to its site - it found devices of every site of that customer by their
// hardware id, overwrote them and moved them to its own site. Now an agent
// only sees and changes devices of its own site.

function twoSites(): array
{
    $customer = Customer::factory()->create();
    $siteA = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale']);
    $siteB = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Filiale']);
    [, $plainB] = AgentToken::generateFor($customer, $siteB, 'Filiale-Agent', now()->addYear());

    return [$customer, $siteA, $siteB, $plainB];
}

test('a server documented at another site is neither changed nor moved', function () {
    [$customer, $siteA, $siteB, $plainB] = twoSites();
    $serverA = rackTestServer($customer, $siteA, 'SRV-ZENTRALE');
    $serverA->update(['agent_identifier' => 'guid-srv-01', 'serialNumber' => 'ORIGINAL']);

    $this->withToken($plainB)->postJson('/api/agent/windows-server', ['server' => [
        'identifier' => 'guid-srv-01', 'hostname' => 'SRV-UEBERSCHRIEBEN', 'serial' => 'GEFAELSCHT',
    ]])->assertOk();

    $serverA->refresh();
    expect($serverA->name)->toBe('SRV-ZENTRALE')
        ->and($serverA->site_id)->toBe($siteA->id)
        ->and($serverA->serialNumber)->toBe('ORIGINAL');

    // The agent documents what it sees at its own site.
    expect(Server::where('site_id', $siteB->id)->where('agent_identifier', 'guid-srv-01')->value('name'))->toBe('SRV-UEBERSCHRIEBEN');
});

test('a computer documented at another site stays where it is', function () {
    [$customer, $siteA, , $plainB] = twoSites();
    $pc = Computer::create([
        'customer_id' => $customer->id, 'site_id' => $siteA->id,
        'name' => 'PC-ZENTRALE', 'agent_identifier' => 'pc-guid-1',
    ]);

    $this->withToken($plainB)->postJson('/api/agent/windows-client', ['client' => [
        'identifier' => 'pc-guid-1', 'hostname' => 'PC-UEBERSCHRIEBEN',
    ]])->assertOk();

    expect($pc->fresh()->name)->toBe('PC-ZENTRALE')
        ->and($pc->fresh()->site_id)->toBe($siteA->id);
});

test('a UniFi accesspoint of another site is left alone', function () {
    [$customer, $siteA, , $plainB] = twoSites();
    $ap = Accesspoint::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $siteA->id,
        'name' => 'AP-ZENTRALE', 'agent_identifier' => 'unifi-ap-1',
    ]);

    $this->withToken($plainB)->postJson('/api/agent/unifi', ['accesspoints' => [
        ['identifier' => 'unifi-ap-1', 'name' => 'AP-UEBERSCHRIEBEN'],
    ]])->assertOk();

    expect($ap->fresh()->name)->toBe('AP-ZENTRALE')
        ->and($ap->fresh()->site_id)->toBe($siteA->id);
});

test('at its own site the agent still updates what it documented', function () {
    [$customer, , $siteB, $plainB] = twoSites();
    $serverB = rackTestServer($customer, $siteB, 'SRV-FILIALE');
    $serverB->update(['agent_identifier' => 'guid-srv-02']);

    $this->withToken($plainB)->postJson('/api/agent/windows-server', ['server' => [
        'identifier' => 'guid-srv-02', 'hostname' => 'SRV-FILIALE-NEU',
    ]])->assertOk();

    expect($serverB->fresh()->name)->toBe('SRV-FILIALE-NEU')
        ->and(Server::where('agent_identifier', 'guid-srv-02')->count())->toBe(1);
});
