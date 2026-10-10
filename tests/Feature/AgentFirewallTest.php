<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\Network;
use App\Models\Setting;
use App\Models\Site;
use App\Support\ExpiringItems;

/** What opnsense.sh sends. */
function opnsensePayload(array $override = []): array
{
    return array_replace([
        'identifier' => 'opnsense:00:0d:b9:aa:bb:01',
        'name' => 'fw01.kunde.local',
        'manufacturer' => 'OPNsense',
        'model' => 'OPNsense',
        'firmware' => '25.1.4',
        'management_url' => 'https://192.168.10.1',
        'interfaces' => [
            ['name' => 'WAN', 'device' => 'igb0', 'ip' => '203.0.113.5/29', 'vlan' => null, 'wan' => true],
            ['name' => 'LAN', 'device' => 'igb1', 'ip' => '192.168.10.1/24', 'vlan' => null],
            ['name' => 'Gast', 'device' => 'igb1_vlan20', 'ip' => '192.168.20.1/24', 'vlan' => 20],
        ],
        // The second range lies in no reported network and is ignored.
        'dhcp_ranges' => [
            ['start' => '192.168.20.100', 'end' => '192.168.20.199', 'dns' => ['9.9.9.9', '1.1.1.1']],
            ['start' => '192.168.10.100', 'end' => '192.168.10.199'],
            ['start' => '172.16.0.10', 'end' => '172.16.0.20'],
        ],
        'vpns' => [
            ['type' => 'IPsec', 'name' => 'Filiale Nord', 'remote' => '198.51.100.7'],
            ['type' => 'WireGuard', 'name' => 'Admin-Laptop', 'remote' => null],
        ],
        'port_forwards' => [
            ['description' => 'Mailserver', 'interface' => 'wan', 'protocol' => 'TCP', 'port' => '25', 'target' => '192.168.10.20', 'target_port' => '25'],
        ],
        'gateways' => [['name' => 'WAN_GW', 'address' => '203.0.113.1']],
    ], $override);
}

function firewallToken(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'Firewall');

    return [$customer, $site, $plain];
}

test('Firewall-Agent legt die Firewall beim Kunden des Tokens an', function () {
    [$customer, $site, $plain] = firewallToken();
    Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'network' => '192.168.10.0', 'cidr' => '24']);

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'created' => true, 'interfaces_documented' => 3, 'vpns_documented' => 2, 'port_forwards_documented' => 1]);

    $firewall = Firewall::where('customer_id', $customer->id)->sole();
    expect($firewall->name)->toBe('fw01.kunde.local')
        ->and($firewall->site_id)->toBe($site->id)
        ->and($firewall->firmware)->toBe('25.1.4')
        ->and($firewall->management_url)->toBe('https://192.168.10.1')
        ->and($firewall->agent_details['vpns'][0]['name'])->toBe('Filiale Nord')
        ->and($firewall->agent_details['port_forwards'][0]['target'])->toBe('192.168.10.20')
        ->and($firewall->agent_reported_at)->not->toBeNull()
        ->and($firewall->ipAddresses()->pluck('address')->all())->toContain('192.168.10.1');
});

test('ein zweiter Lauf aktualisiert dieselbe Firewall und lässt Gepflegtes stehen', function () {
    [$customer, , $plain] = firewallToken();

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload())->assertOk();

    $firewall = Firewall::where('customer_id', $customer->id)->sole();
    $firewall->update(['name' => 'Firewall Zentrale', 'notes' => 'Wartung über Systemhaus', 'management_url' => 'https://fw.kunde.de:4443']);

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload([
        'firmware' => '25.1.5',
        'vpns' => [],
    ]))->assertOk()->assertJson(['created' => false]);

    $firewall->refresh();
    expect(Firewall::where('customer_id', $customer->id)->count())->toBe(1)
        ->and($firewall->name)->toBe('Firewall Zentrale')
        ->and($firewall->notes)->toBe('Wartung über Systemhaus')
        ->and($firewall->management_url)->toBe('https://fw.kunde.de:4443')
        ->and($firewall->firmware)->toBe('25.1.5')
        ->and($firewall->agent_details['vpns'])->toBe([]);
});

test('eine von Hand angelegte Firewall wird über die Seriennummer übernommen', function () {
    [$customer, $site, $plain] = firewallToken();
    $vorhanden = Firewall::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'UTM Keller', 'serialNumber' => 'SP-123456', 'firmware' => '12.6',
    ]);

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload([
        'identifier' => 'securepoint:SP-123456', 'manufacturer' => 'Securepoint', 'serial' => 'SP-123456', 'firmware' => '14.0.2',
    ]))->assertOk()->assertJson(['created' => false]);

    $vorhanden->refresh();
    expect(Firewall::where('customer_id', $customer->id)->count())->toBe(1)
        ->and($vorhanden->name)->toBe('UTM Keller')
        ->and($vorhanden->agent_identifier)->toBe('securepoint:SP-123456')
        ->and($vorhanden->firmware)->toBe('14.0.2');
});

test('der Token eines Kunden schreibt nicht in die Firewall eines anderen', function () {
    [$kundeA, , $plainA] = firewallToken();
    [$kundeB, , $plainB] = firewallToken();

    $this->withToken($plainA)->postJson('/api/agent/firewall', opnsensePayload())->assertOk();
    $this->withToken($plainB)->postJson('/api/agent/firewall', opnsensePayload(['firmware' => '99']))->assertOk();

    expect(Firewall::where('customer_id', $kundeA->id)->sole()->firmware)->toBe('25.1.4')
        ->and(Firewall::where('customer_id', $kundeB->id)->sole()->firmware)->toBe('99');
});

test('ohne Token oder ohne Kennung wird abgelehnt', function () {
    [, , $plain] = firewallToken();

    $this->postJson('/api/agent/firewall', opnsensePayload())->assertUnauthorized();
    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload(['identifier' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors('identifier');
});

test('die Firewall-Karte zeigt, was der Agent gemeldet hat', function () {
    [$customer, , $plain] = firewallToken();
    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload())->assertOk();

    $this->actingAs(userWithPermissions(['firewall_viewAny']));
    $this->get("/{$customer->slug}/firewall")
        ->assertOk()
        ->assertSee('Filiale Nord')
        ->assertSee('192.168.10.20:25')
        ->assertSee('VLAN 20')
        ->assertSee('Vom Agent gemeldet am');
});

test('eine auslaufende Subscription steht in der Ablauf-Mail', function () {
    [, , $plain] = firewallToken();
    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload([
        'subscription_until' => now()->addDays(10)->toDateString(),
    ]))->assertOk();

    $items = ExpiringItems::forUser(userWithPermissions(['firewall_viewAny']), Setting::EXPIRY_KINDS);

    expect($items->pluck('name'))->toContain('fw01.kunde.local');
});

test('interne Schnittstellen werden zu Netzen unter VLAN, WAN nicht', function () {
    [$customer, $site, $plain] = firewallToken();

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload())
        ->assertOk()->assertJson(['networks_documented' => 2]);

    $netze = Network::where('customer_id', $customer->id)->orderBy('network')->get();
    expect($netze)->toHaveCount(2)
        ->and($netze[0]->only(['description', 'network', 'cidr', 'subnetmask', 'gateway', 'vlanId']))
        ->toBe(['description' => 'LAN', 'network' => '192.168.10.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0', 'gateway' => '192.168.10.1', 'vlanId' => null])
        // No DNS named for the LAN range: the firewall hands out itself.
        ->and($netze[0]->only(['dns1', 'dns2']))->toBe(['dns1' => '192.168.10.1', 'dns2' => null])
        ->and($netze[1]->only(['dns1', 'dns2']))->toBe(['dns1' => '9.9.9.9', 'dns2' => '1.1.1.1'])
        ->and($netze[1]->only(['description', 'network', 'vlanId', 'gateway', 'dhcpStart', 'dhcpEnd']))
        ->toBe(['description' => 'Gast', 'network' => '192.168.20.0', 'vlanId' => 20, 'gateway' => '192.168.20.1', 'dhcpStart' => '192.168.20.100', 'dhcpEnd' => '192.168.20.199'])
        ->and($netze[0]->site_id)->toBe($site->id);

    // The firewall's own address sits in the network it belongs to.
    $firewall = Firewall::where('customer_id', $customer->id)->sole();
    expect($firewall->ipAddresses()->where('address', '192.168.20.1')->value('network_id'))->toBe($netze[1]->id);

    // A second run neither duplicates nor changes anything.
    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload())->assertOk();
    expect(Network::where('customer_id', $customer->id)->count())->toBe(2);
});

test('ein von Hand angelegtes Netz wird ergänzt, nicht überschrieben', function () {
    [$customer, $site, $plain] = firewallToken();
    $netz = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Gäste-WLAN', 'network' => '192.168.20.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'vlanId' => null, 'gateway' => null, 'dns1' => '192.168.20.5', 'dns2' => null, 'dhcpStart' => '192.168.20.50', 'dhcpEnd' => null,
    ]);

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload())->assertOk();

    $netz->refresh();
    expect(Network::where('customer_id', $customer->id)->where('network', '192.168.20.0')->count())->toBe(1)
        ->and($netz->description)->toBe('Gäste-WLAN')
        ->and($netz->dns1)->toBe('192.168.20.5')
        // dns2 was empty and is filled - dns1 typed in stays.
        ->and($netz->dns2)->toBe('1.1.1.1')
        ->and($netz->dhcpStart)->toBe('192.168.20.50')
        ->and($netz->vlanId)->toBe(20)
        ->and($netz->gateway)->toBe('192.168.20.1')
        ->and($netz->dhcpEnd)->toBe('192.168.20.199');
});

test('ein Netz ohne DHCP-Bereich bekommt keinen DNS-Eintrag', function () {
    [$customer, , $plain] = firewallToken();

    $this->withToken($plain)->postJson('/api/agent/firewall', opnsensePayload(['dhcp_ranges' => []]))->assertOk();

    expect(Network::where('customer_id', $customer->id)->whereNotNull('dns1')->count())->toBe(0);
});
