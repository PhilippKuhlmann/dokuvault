<?php

use App\Models\AgentToken;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Site;

/**
 * IPAM und Auto-Dokumentation reagierten als einzige Listen mit Standortbezug
 * nicht auf den Standortfilter der Seitenleiste.
 *
 * Beim IPAM ist die Abgrenzung fachlich wichtig: gefiltert werden die angezeigten
 * VLANs, NICHT die darin belegten Adressen. Ein Gerät eines anderen Standorts mit
 * einer IP in diesem Netz muss sichtbar bleiben, sonst erscheint eine vergebene
 * Adresse faelschlich als frei.
 */
function ipPlanFixture(): array
{
    $customer = Customer::factory()->create();
    $hamburg = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale Hamburg']);
    $muenchen = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Filiale Muenchen']);

    $netHamburg = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'description' => 'Clients Hamburg', 'vlanId' => 20,
        'network' => '10.10.20.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.10.20.1', 'dhcpStart' => '100', 'dhcpEnd' => '200',
    ]);
    $netMuenchen = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $muenchen->id,
        'description' => 'Clients Muenchen', 'vlanId' => 40,
        'network' => '10.10.40.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.10.40.1', 'dhcpStart' => '100', 'dhcpEnd' => '200',
    ]);

    return [$customer, $hamburg, $muenchen, $netHamburg, $netMuenchen];
}

test('IPAM zeigt bei gewaehltem Standort nur dessen VLANs', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $hamburg] = ipPlanFixture();

    $this->withSession(['site' => $hamburg->id])
        ->get("/{$customer->slug}/ip-plan")
        ->assertStatus(200)
        ->assertSee('Clients Hamburg')
        ->assertDontSee('Clients Muenchen');
});

test('IPAM zeigt ohne Standortfilter alle VLANs', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer] = ipPlanFixture();

    $this->withSession(['site' => 'all'])
        ->get("/{$customer->slug}/ip-plan")
        ->assertStatus(200)
        ->assertSee('Clients Hamburg')
        ->assertSee('Clients Muenchen');
});

test('IPAM blendet Geraete fremder Standorte im gefilterten VLAN NICHT aus', function () {
    // Kernpunkt: sonst erschiene eine tatsaechlich vergebene Adresse als frei.
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $hamburg, $muenchen] = ipPlanFixture();
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    // Geraet steht in Muenchen, hat aber eine IP im Hamburger Netz
    $fremd = Computer::create([
        'customer_id' => $customer->id,
        'site_id' => $muenchen->id,
        'name' => 'PC-Fremdstandort',
        'operating_system_id' => $os->id,
    ]);
    $fremd->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.10.20.50']);

    $this->withSession(['site' => $hamburg->id])
        ->get("/{$customer->slug}/ip-plan")
        ->assertStatus(200)
        ->assertSee('Clients Hamburg')
        ->assertSee('PC-Fremdstandort')   // muss sichtbar bleiben
        ->assertSee('10.10.20.50');
});

test('Auto-Dokumentation zeigt bei gewaehltem Standort nur dessen Tokens', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $hamburg, $muenchen] = ipPlanFixture();

    AgentToken::generateFor($customer, $hamburg, 'Token Hamburg');
    AgentToken::generateFor($customer, $muenchen, 'Token Muenchen');

    $this->withSession(['site' => $hamburg->id])
        ->get("/{$customer->slug}/agent")
        ->assertStatus(200)
        ->assertSee('Token Hamburg')
        ->assertDontSee('Token Muenchen');
});

test('Auto-Dokumentation zeigt ohne Standortfilter alle Tokens', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $hamburg, $muenchen] = ipPlanFixture();

    AgentToken::generateFor($customer, $hamburg, 'Token Hamburg');
    AgentToken::generateFor($customer, $muenchen, 'Token Muenchen');

    $this->withSession(['site' => 'all'])
        ->get("/{$customer->slug}/agent")
        ->assertStatus(200)
        ->assertSee('Token Hamburg')
        ->assertSee('Token Muenchen');
});
