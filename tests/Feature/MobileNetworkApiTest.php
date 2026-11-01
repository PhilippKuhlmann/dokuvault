<?php

use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpRange;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

function ipamAppToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function ipamNetz(Customer $kunde, array $werte = []): Network
{
    return Network::create($werte + [
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'description' => 'Server',
        'vlanId' => 10,
        'network' => '10.0.10.0',
        'subnetmask' => '255.255.255.0',
        'cidr' => 24,
        'gateway' => '10.0.10.1',
        'dhcpStart' => '10.0.10.100',
        'dhcpEnd' => '10.0.10.199',
    ]);
}

function ipamServer(Network $netz, string $adresse): Server
{
    $server = Server::create([
        'customer_id' => $netz->customer_id,
        'site_id' => $netz->site_id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => 'SRV-01',
    ]);
    IpAddress::create(['customer_id' => $netz->customer_id, 'ipable_type' => Server::class, 'ipable_id' => $server->id,
        'address' => $adresse, 'network_id' => $netz->id]);

    return $server;
}

const IPAM_RECHTE = ['network_viewAny', 'network_create', 'network_update', 'network_delete', 'server_viewAny'];

test('die App listet die VLANs mit Auslastung', function () {
    $netz = ipamNetz(Customer::factory()->create());
    ipamNetz($netz->customer, ['vlanId' => 20, 'network' => '10.0.20.0', 'gateway' => null]);

    $antwort = $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->getJson("/api/mobile/customers/{$netz->customer->slug}/networks")
        ->assertOk()
        ->assertJsonCount(2, 'entries')
        ->assertJsonPath('entries.0.name', 'Server · VLAN 10')
        ->assertJsonPath('entries.0.cidr', 24)
        ->assertJsonPath('entries.0.usage.used', 1)
        ->assertJsonPath('entries.0.usage.dhcp', 100)
        ->assertJsonPath('entries.0.usage.total', 254)
        ->assertJsonPath('may_create', true);

    expect($antwort->json('sites'))->toHaveCount(2);
});

test('die App filtert die VLANs nach Standort', function () {
    $netz = ipamNetz(Customer::factory()->create());
    $anderes = ipamNetz($netz->customer, ['vlanId' => 20, 'network' => '10.0.20.0']);

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->getJson("/api/mobile/customers/{$netz->customer->slug}/networks?site={$anderes->site_id}")
        ->assertJsonCount(1, 'entries')
        ->assertJsonPath('entries.0.vlan', 20);
});

test('die App zeigt den IP-Plan eines VLANs', function () {
    $netz = ipamNetz(Customer::factory()->create());
    $server = ipamServer($netz, '10.0.10.5');
    IpRange::create(['customer_id' => $netz->customer_id, 'network_id' => $netz->id,
        'from_ip' => '10.0.10.10', 'to_ip' => '10.0.10.20', 'label' => 'Proxmox']);

    $antwort = $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->getJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}")
        ->assertOk()
        ->assertJsonPath('rows.0.kind', 'device')
        ->assertJsonPath('rows.0.gateway', true)
        ->assertJsonPath('next_free', '10.0.10.2')
        ->assertJsonPath('ranges.0.label', 'Proxmox')
        ->assertJsonPath('ranges.0.count', 11)
        ->assertJsonPath('may_update', true);

    $zeilen = collect($antwort->json('rows'));
    expect($zeilen->firstWhere('from', '10.0.10.5')['devices'][0])
        ->toMatchArray(['name' => 'SRV-01', 'type' => 'server', 'id' => $server->id, 'in_pool' => false]);

    $reserviert = $zeilen->firstWhere('kind', 'reserved');
    expect($reserviert)->toMatchArray(['from' => '10.0.10.10', 'to' => '10.0.10.20', 'label' => 'Proxmox'])
        ->and($reserviert['color'])->toBeString();

    expect($zeilen->firstWhere('kind', 'dhcp'))->toMatchArray(['from' => '10.0.10.100', 'to' => '10.0.10.199']);
});

test('ohne Recht auf die Geraeteliste steht nur der Name im Plan', function () {
    $netz = ipamNetz(Customer::factory()->create());
    ipamServer($netz, '10.0.10.5');

    $zeilen = collect($this->withToken(ipamAppToken(['network_viewAny']))
        ->getJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}")
        ->assertOk()
        ->assertJsonPath('may_update', false)
        ->json('rows'));

    expect($zeilen->firstWhere('from', '10.0.10.5')['devices'][0])->toMatchArray(['name' => 'SRV-01', 'type' => null, 'id' => null]);
});

test('die App legt ein VLAN an und ergaenzt die Maske aus dem CIDR', function () {
    $kunde = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $kunde->id]);

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->postJson("/api/mobile/customers/{$kunde->slug}/networks", [
            'site_id' => $site->id, 'description' => 'Clients', 'vlanId' => 30, 'network' => '10.0.30.0', 'cidr' => 23,
        ])
        ->assertCreated()
        ->assertJsonPath('subnet_mask', '255.255.254.0')
        ->assertJsonPath('usage.total', 510);

    expect(Network::where('customer_id', $kunde->id)->first())
        ->description->toBe('Clients')
        ->subnetmask->toBe('255.255.254.0');
});

test('ein VLAN an einem fremden Standort wird abgelehnt', function () {
    $kunde = Customer::factory()->create();
    $fremd = Site::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->postJson("/api/mobile/customers/{$kunde->slug}/networks", [
            'site_id' => $fremd->id, 'description' => 'X', 'network' => '10.0.30.0', 'subnetmask' => '255.255.255.0',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('site_id');
});

test('ohne Anlegerecht kein neues VLAN', function () {
    $kunde = Customer::factory()->create();

    $this->withToken(ipamAppToken(['network_viewAny']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/networks", ['description' => 'X'])
        ->assertForbidden();
});

test('die App aendert nur die gesendeten Felder eines VLANs', function () {
    $netz = ipamNetz(Customer::factory()->create());

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->patchJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}", ['description' => 'DMZ', 'cidr' => 25])
        ->assertOk()
        ->assertJsonPath('description', 'DMZ')
        ->assertJsonPath('subnet_mask', '255.255.255.128');

    expect($netz->fresh())
        ->gateway->toBe('10.0.10.1')
        ->dhcpStart->toBe('10.0.10.100')
        ->vlanId->toEqual(10);
});

test('ein leerer Wert leert ein optionales Feld', function () {
    $netz = ipamNetz(Customer::factory()->create());

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->putJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}", ['gateway' => ''])
        ->assertOk()
        ->assertJsonPath('gateway', null);
});

test('das VLAN eines anderen Kunden ist ein 404', function () {
    $netz = ipamNetz(Customer::factory()->create());
    $anderer = Customer::factory()->create();

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->getJson("/api/mobile/customers/{$anderer->slug}/networks/{$netz->id}")
        ->assertNotFound();
});

test('ein Kundennutzer sieht keine fremden VLANs', function () {
    $netz = ipamNetz(Customer::factory()->create());

    $antwort = $this->withToken(ipamAppToken(IPAM_RECHTE, Customer::factory()->create()))
        ->getJson("/api/mobile/customers/{$netz->customer->slug}/networks");

    expect($antwort->status())->toBeIn([403, 404]);
});

test('die App loescht ein VLAN in den Papierkorb', function () {
    $netz = ipamNetz(Customer::factory()->create());

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->deleteJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}")
        ->assertNoContent();

    expect(Network::withTrashed()->find($netz->id)->trashed())->toBeTrue();
});

test('die App reserviert einen Bereich', function () {
    $netz = ipamNetz(Customer::factory()->create());

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->postJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}/ip-ranges", [
            'from_ip' => '10.0.10.10', 'to_ip' => '10.0.10.20', 'label' => 'Proxmox', 'note' => 'Cluster',
        ])
        ->assertCreated()
        ->assertJsonPath('ranges.0.label', 'Proxmox')
        ->assertJsonPath('ranges.0.note', 'Cluster')
        ->assertJsonPath('usage.reserved', 11);
});

test('die App loescht einen Bereich', function () {
    $netz = ipamNetz(Customer::factory()->create());
    $bereich = IpRange::create(['customer_id' => $netz->customer_id, 'network_id' => $netz->id,
        'from_ip' => '10.0.10.10', 'to_ip' => '10.0.10.20', 'label' => 'Proxmox']);

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->deleteJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}/ip-ranges/{$bereich->id}")
        ->assertNoContent();

    expect(IpRange::find($bereich->id))->toBeNull();
});

test('ein ueberlappender Bereich wird abgelehnt', function () {
    $netz = ipamNetz(Customer::factory()->create());
    IpRange::create(['customer_id' => $netz->customer_id, 'network_id' => $netz->id,
        'from_ip' => '10.0.10.10', 'to_ip' => '10.0.10.20', 'label' => 'Proxmox']);

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->postJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}/ip-ranges",
            ['from_ip' => '10.0.10.15', 'to_ip' => '10.0.10.30', 'label' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from_ip');
});

test('ein Bereich ausserhalb des Netzes wird abgelehnt', function () {
    $netz = ipamNetz(Customer::factory()->create());

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->postJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}/ip-ranges",
            ['from_ip' => '10.0.10.50', 'to_ip' => '10.0.11.5', 'label' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_ip');
});

test('der Bereich eines anderen Netzes ist ein 404', function () {
    $netz = ipamNetz(Customer::factory()->create());
    $anderes = ipamNetz($netz->customer, ['vlanId' => 20, 'network' => '10.0.20.0']);
    $bereich = IpRange::create(['customer_id' => $netz->customer_id, 'network_id' => $anderes->id,
        'from_ip' => '10.0.20.10', 'to_ip' => '10.0.20.20', 'label' => 'X']);

    $this->withToken(ipamAppToken(IPAM_RECHTE))
        ->deleteJson("/api/mobile/customers/{$netz->customer->slug}/networks/{$netz->id}/ip-ranges/{$bereich->id}")
        ->assertNotFound();

    expect(IpRange::find($bereich->id))->not->toBeNull();
});
