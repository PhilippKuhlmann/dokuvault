<?php

use App\Livewire\DeviceIpAddresses;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Router;
use App\Models\Site;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function routerWithNetworks(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $mgmt = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Management', 'vlanId' => 30,
        'network' => '10.10.30.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.10.30.1', 'dhcpStart' => '100', 'dhcpEnd' => '200',
    ]);
    $clients = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Clients', 'vlanId' => 20,
        'network' => '10.10.20.0', 'cidr' => '24', 'subnetmask' => '255.255.255.0',
        'gateway' => '10.10.20.1', 'dhcpStart' => '100', 'dhcpEnd' => '200',
    ]);

    $router = Router::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'RTR-Core', 'ip' => '10.10.30.1', 'port' => '443',
        'username' => 'admin', 'password' => 'x',
    ]);

    return [$customer, $router, $clients];
}

test('ein Router mit zusätzlicher IP erscheint im IPAM in beiden VLANs', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $router, $clients] = routerWithNetworks();

    // Zusätzliche Gateway-IP im Clients-VLAN
    $router->ipAddresses()->create([
        'customer_id' => $customer->id,
        'network_id' => $clients->id,
        'address' => '10.10.20.1',
        'label' => 'Gateway Clients',
    ]);

    $response = $this->get("/{$customer->slug}/ip-plan");
    $response->assertStatus(200);
    // Router in Management (.30.1) und in Clients (.20.1)
    $response->assertSee('10.10.30.1');
    $response->assertSee('10.10.20.1');
    $response->assertSee('RTR-Core (Gateway Clients)');
});

test('IP über die Livewire-Komponente hinzufügen und entfernen', function () {
    $this->actingAs(userWithPermissions(['router_update']));
    [$customer, $router, $clients] = routerWithNetworks();

    Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->set('address', '10.10.20.1')
        ->set('network_id', $clients->id)
        ->set('label', 'Gateway Clients')
        ->call('add');

    expect($router->ipAddresses()->count())->toBe(1);

    $id = $router->ipAddresses()->first()->id;
    Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->call('remove', $id);

    expect($router->ipAddresses()->count())->toBe(0);
});

test('VLAN-Option liefert das Netz-Präfix fürs clientseitige Vorbefüllen', function () {
    $this->actingAs(userWithPermissions(['router_update']));
    [$customer, $router, $clients] = routerWithNetworks();

    // Das data-prefix-Attribut (erste 3 Oktette) treibt das Alpine-Vorbefüllen des IP-Feldes
    Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->assertSee('data-prefix="10.10.20."', false)
        ->assertSee('data-prefix="10.10.30."', false);
});

test('ohne Bearbeiten-Recht kann keine IP hinzugefügt werden', function () {
    $this->actingAs(userWithPermissions([])); // kein router_update
    [$customer, $router] = routerWithNetworks();

    Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->set('address', '10.10.20.5')
        ->call('add')
        ->assertForbidden();

    expect($router->ipAddresses()->count())->toBe(0);
});

test('ungültige IP wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['router_update']));
    [$customer, $router] = routerWithNetworks();

    Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->set('address', 'keine-ip')
        ->call('add')
        ->assertHasErrors('address');

    expect($router->ipAddresses()->count())->toBe(0);
});

test('die Netz-Anzeige nennt Beschreibung und VLAN-Nummer', function () {
    $netz = new Network(['description' => 'Server & Management', 'vlanId' => 30]);
    expect($netz->anzeige())->toBe('Server & Management · VLAN 30');

    // Steht die Beschreibung auf derselben Zeile schon als Bezeichnung,
    // bleibt nur die Nummer.
    expect($netz->anzeige(true))->toBe('VLAN 30');

    expect((new Network(['description' => 'DMZ']))->anzeige())->toBe('DMZ');
    expect((new Network(['vlanId' => 47]))->anzeige())->toBe('VLAN 47');
    expect((new Network)->anzeige())->toBeNull();
});

test('die Listenkarte zeigt zur Adresse den Netznamen samt VLAN-Nummer', function () {
    $this->actingAs(userWithPermissions(['router_viewAny']));
    [$customer, $router, $clients] = routerWithNetworks();

    $router->ipAddresses()->create([
        'customer_id' => $customer->id, 'address' => '10.10.20.5',
        'network_id' => $clients->id, 'label' => 'PVE GUI',
    ]);

    $this->get("/{$customer->slug}/router")->assertOk()
        ->assertSee('10.10.20.5')
        ->assertSee('Clients · VLAN 20', false);
});

test('die Kennung des Geraets laesst sich vom Client nicht umbiegen', function () {
    $this->actingAs(userWithPermissions(['router_update']));
    [$customer, $router] = routerWithNetworks();

    $fremd = Customer::factory()->create();

    // #[Locked]: Livewire lehnt die Aenderung ab, bevor eine Aktion laeuft -
    // vorher fing das erst die Pruefung in device() ab.
    foreach (['customerId' => $fremd->id, 'modelId' => 999, 'modelClass' => 'App\Models\Server'] as $feld => $wert) {
        expect(fn () => Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
            ->set($feld, $wert))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    }
});

test('ein VLAN laesst sich im IP-Block anlegen und ist danach ausgewaehlt', function () {
    $this->actingAs(userWithPermissions(['router_update', 'network_create']));
    [$customer, $router] = routerWithNetworks();

    $komponente = Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->set('vlanDescription', 'DMZ')
        ->set('vlanNummer', 90)
        ->set('vlanNetwork', '10.10.90.0')
        ->set('vlanCidr', 24)
        ->set('vlanGateway', '10.10.90.1')
        ->set('vlanDns1', '10.10.30.10')
        ->set('vlanDhcpStart', '10.10.90.100')
        ->set('vlanDhcpEnd', '10.10.90.200')
        ->call('vlanAnlegen')
        ->assertHasNoErrors();

    $netz = Network::where('description', 'DMZ')->firstOrFail();
    expect($netz->customer_id)->toBe($customer->id);
    // Der Standort kommt vom Geraet, nicht aus dem Formular.
    expect($netz->site_id)->toBe($router->site_id);
    expect($netz->vlanId)->toBe(90);

    // Alle Felder des VLAN-Formulars stehen auch im Modal zur Verfuegung.
    expect($netz->cidr)->toBe('24');
    expect($netz->gateway)->toBe('10.10.90.1');
    expect($netz->dns1)->toBe('10.10.30.10');
    expect($netz->dhcpStart)->toBe('10.10.90.100');
    expect($netz->dhcpEnd)->toBe('10.10.90.200');

    // Direkt ausgewaehlt und Modal zu - man war mitten im Eintragen einer IP.
    $komponente->assertSet('network_id', $netz->id)->assertSet('vlanModal', false);
});

test('ohne network_create bleibt das VLAN-Anlegen gesperrt', function () {
    $this->actingAs(userWithPermissions(['router_update'])); // kein network_create
    [$customer, $router] = routerWithNetworks();

    Livewire::test(DeviceIpAddresses::class, ['model' => $router, 'customer' => $customer])
        ->set('vlanDescription', 'Heimlich')
        ->set('vlanNetwork', '10.10.99.0')
        ->call('vlanAnlegen')
        ->assertForbidden();

    expect(Network::where('description', 'Heimlich')->count())->toBe(0);
});
