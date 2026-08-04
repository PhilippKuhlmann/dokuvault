<?php

use App\Livewire\RackEditor;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

function serverUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2025 Standard']);

    return [$customer, $site, $os];
}

function serverMitBauform(Customer $customer, Site $site, string $bauform, string $name = 'SRV', int $he = 1): Server
{
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    return Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => $name,
        'operating_system_id' => $os->id, 'form_factor' => $bauform, 'full_depth' => true,
        'height_units' => $he,
    ]);
}

test('store speichert Bauform und Einbautiefe', function () {
    $this->actingAs(userWithPermissions(['server_create']));
    [$customer, $site, $os] = serverUmgebung();

    $this->post("/{$customer->slug}/server", [
        'site_id' => $site->id, 'name' => 'SRV-01', 'operating_system_id' => $os->id,
        'form_factor' => 'rack', 'full_depth' => '0', 'height_units' => 2,
    ]);

    $server = Server::first();
    expect($server->form_factor)->toBe('rack');
    expect($server->full_depth)->toBeFalse();
    expect($server->height_units)->toBe(2);
});

test('ein Standserver braucht keine Einbautiefe', function () {
    $this->actingAs(userWithPermissions(['server_create']));
    [$customer, $site, $os] = serverUmgebung();

    // Das Formular blendet das Feld aus, es kommt also gar nicht mit.
    $this->post("/{$customer->slug}/server", [
        'site_id' => $site->id, 'name' => 'SRV-TOWER', 'operating_system_id' => $os->id,
        'form_factor' => 'tower',
    ])->assertSessionHasNoErrors();

    expect(Server::first()->form_factor)->toBe('tower');
});

test('beim 19-Zoll-Server bleibt die Einbautiefe Pflicht', function () {
    $this->actingAs(userWithPermissions(['server_create']));
    [$customer, $site, $os] = serverUmgebung();

    $this->post("/{$customer->slug}/server", [
        'site_id' => $site->id, 'name' => 'SRV-01', 'operating_system_id' => $os->id,
        'form_factor' => 'rack',
    ])->assertSessionHasErrors(['full_depth', 'height_units']);
});

test('eine unbekannte Bauform wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['server_create']));
    [$customer, $site, $os] = serverUmgebung();

    $this->post("/{$customer->slug}/server", [
        'site_id' => $site->id, 'name' => 'SRV-01', 'operating_system_id' => $os->id,
        'form_factor' => 'schrank', 'full_depth' => '1', 'height_units' => 1,
    ])->assertSessionHasErrors('form_factor');

    expect(Server::count())->toBe(0);
});

test('Bestandsserver gelten als 19-Zoll in voller Tiefe', function () {
    [$customer, $site] = serverUmgebung();
    $os = OperatingSystem::factory()->create(['name' => 'Ubuntu 26.04']);

    // Ohne die beiden Felder angelegt, wie es die Migration vorfindet.
    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Altbestand', 'operating_system_id' => $os->id,
    ]);

    expect($server->fresh()->form_factor)->toBe('rack');
    expect($server->fresh()->full_depth)->toBeTrue();
});

test('die Rack-Auswahl zeigt nur 19-Zoll-Server', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site] = serverUmgebung();
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack A', 'height_units' => 42,
    ]);

    serverMitBauform($customer, $site, 'rack', 'SRV-RACK');
    serverMitBauform($customer, $site, 'tower', 'SRV-TOWER');

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->assertSee('SRV-RACK')
        ->assertDontSee('SRV-TOWER');
});

test('ein Standserver lässt sich auch per direktem Aufruf nicht einbauen', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site] = serverUmgebung();
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack A', 'height_units' => 42,
    ]);
    $tower = serverMitBauform($customer, $site, 'tower', 'SRV-TOWER');

    // Die Auswahlliste zeigt ihn nicht - der Aufruf laesst sich trotzdem
    // nachbilden, deshalb muss der Server selbst ablehnen.
    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $tower->id, 1);

    expect($rack->items()->count())->toBe(0);
});

test('ein 19-Zoll-Server lässt sich einbauen', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site] = serverUmgebung();
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack A', 'height_units' => 42,
    ]);
    $server = serverMitBauform($customer, $site, 'rack', 'SRV-RACK');

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $server->id, 1);

    expect($rack->items()->count())->toBe(1);
});

test('ein Server bringt seine Höhe mit ins Rack', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site] = serverUmgebung();
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack A', 'height_units' => 42,
    ]);
    $server = serverMitBauform($customer, $site, 'rack', 'SRV-2HE', he: 2);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $server->id, 1);

    expect($rack->items()->first()->height_units)->toBe(2);
});

test('Bestandsserver bekommen eine Höheneinheit', function () {
    [$customer, $site] = serverUmgebung();
    $os = OperatingSystem::factory()->create(['name' => 'Ubuntu 26.04']);

    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Altbestand', 'operating_system_id' => $os->id,
    ]);

    expect($server->fresh()->height_units)->toBe(1);
});
