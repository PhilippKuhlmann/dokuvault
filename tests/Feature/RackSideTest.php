<?php

use App\Livewire\RackEditor;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

function seitenUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack Test', 'height_units' => 42,
    ]);

    return [$customer, $site, $rack];
}

function serverMitTiefe(Customer $customer, Site $site, bool $volleTiefe, string $name = 'SRV'): Server
{
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    return Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => $name,
        'operating_system_id' => $os->id, 'form_factor' => 'rack',
        'full_depth' => $volleTiefe, 'height_units' => 1,
    ]);
}

function editor(Rack $rack, Customer $customer)
{
    return Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer]);
}

test('Bestandseinbauten liegen vorne und gehen über die volle Tiefe', function () {
    [$customer, $site, $rack] = seitenUmgebung();

    $item = $rack->items()->create(['position' => 1, 'height_units' => 1, 'name' => 'Blindplatte 1 HE']);

    expect($item->fresh()->side)->toBe('front');
    expect($item->fresh()->full_depth)->toBeTrue();
});

test('ein Einbau landet auf der gewählten Seite', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $server = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-HALB');

    editor($rack, $customer)
        ->call('setSide', 'rear')
        ->call('placeDevice', 'server', $server->id, 5);

    expect($rack->items()->first()->side)->toBe('rear');
});

test('die Tiefe wird beim Einbauen vom Gerät übernommen', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $halb = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-HALB');
    $voll = serverMitTiefe($customer, $site, volleTiefe: true, name: 'SRV-VOLL');

    editor($rack, $customer)
        ->call('placeDevice', 'server', $halb->id, 1)
        ->call('placeDevice', 'server', $voll->id, 5);

    expect($rack->items()->where('position', 1)->first()->full_depth)->toBeFalse();
    expect($rack->items()->where('position', 5)->first()->full_depth)->toBeTrue();
});

test('hinter einem Gerät in halber Tiefe ist die Rückseite frei', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $vorne = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-VORN');
    $hinten = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-HINTEN');

    editor($rack, $customer)
        ->call('placeDevice', 'server', $vorne->id, 10)
        ->call('setSide', 'rear')
        ->call('placeDevice', 'server', $hinten->id, 10);

    expect($rack->items()->count())->toBe(2);
    expect($rack->items()->where('side', 'rear')->first()->position)->toBe(10);
});

test('hinter einem Gerät in voller Tiefe ist die Rückseite belegt', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $vorne = serverMitTiefe($customer, $site, volleTiefe: true, name: 'SRV-VOLL');
    $hinten = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-HINTEN');

    editor($rack, $customer)
        ->call('placeDevice', 'server', $vorne->id, 10)
        ->call('setSide', 'rear')
        ->call('placeDevice', 'server', $hinten->id, 10)
        ->assertHasErrors('rack');

    expect($rack->items()->count())->toBe(1);
});

test('ein Gerät in voller Tiefe hinten blockiert auch die Vorderseite', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $hinten = serverMitTiefe($customer, $site, volleTiefe: true, name: 'SRV-HINTEN');
    $vorne = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-VORN');

    editor($rack, $customer)
        ->call('setSide', 'rear')
        ->call('placeDevice', 'server', $hinten->id, 20)
        ->call('setSide', 'front')
        ->call('placeDevice', 'server', $vorne->id, 20)
        ->assertHasErrors('rack');

    expect($rack->items()->count())->toBe(1);
});

test('der Einbauen-Knopf sucht den freien Platz auf der gewählten Seite', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $vorne = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-VORN');
    $hinten = serverMitTiefe($customer, $site, volleTiefe: false, name: 'SRV-HINTEN');

    // Vorne HE 1 belegen; hinten muss HE 1 trotzdem frei bleiben.
    editor($rack, $customer)
        ->call('quickPlaceDevice', 'server', $vorne->id)
        ->call('setSide', 'rear')
        ->call('quickPlaceDevice', 'server', $hinten->id);

    expect($rack->items()->where('side', 'front')->first()->position)->toBe(1);
    expect($rack->items()->where('side', 'rear')->first()->position)->toBe(1);
});

test('ein Einbau der Gegenseite lässt sich nicht verschieben oder entfernen', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $item = $rack->items()->create([
        'side' => 'rear', 'position' => 5, 'height_units' => 1,
        'full_depth' => true, 'name' => 'Steckdosenleiste (PDU)',
    ]);

    // Von vorne ist er als Geist sichtbar - aber nicht anzufassen.
    editor($rack, $customer)
        ->call('move', $item->id, 9)->assertHasErrors('rack')
        ->call('remove', $item->id)->assertHasErrors('rack')
        ->call('setHeight', $item->id, 2)->assertHasErrors('rack');

    expect($item->fresh()->position)->toBe(5);
    expect($item->fresh()->height_units)->toBe(1);
});

test('das Schema zeigt die Einbauten der gewählten Seite', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $rack->items()->create(['side' => 'front', 'position' => 1, 'height_units' => 1,
        'full_depth' => false, 'name' => 'NUR-VORNE']);
    $rack->items()->create(['side' => 'rear', 'position' => 30, 'height_units' => 1,
        'full_depth' => false, 'name' => 'NUR-HINTEN']);

    editor($rack, $customer)
        ->assertSee('NUR-VORNE')->assertDontSee('NUR-HINTEN')
        ->call('setSide', 'rear')
        ->assertSee('NUR-HINTEN')->assertDontSee('NUR-VORNE');
});

test('ein durchgehender Einbau erscheint auf beiden Seiten', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $rack->items()->create(['side' => 'front', 'position' => 1, 'height_units' => 1,
        'full_depth' => true, 'name' => 'DURCHGEHEND']);

    editor($rack, $customer)
        ->assertSee('DURCHGEHEND')
        ->call('setSide', 'rear')
        ->assertSee('DURCHGEHEND')
        ->assertSee('durchgehend');
});

test('eine unbekannte Seite fällt auf die Vorderseite zurück', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();

    editor($rack, $customer)
        ->call('setSide', 'oben')
        ->assertSet('side', 'front');
});

test('Katalogelemente bringen ihre Tiefe mit', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = seitenUmgebung();
    $pdu = RackCatalogItem::create([
        'name' => 'PDU Test', 'height_units' => 1, 'appearance' => 'pdu',
        'full_depth' => false, 'sort_order' => 1,
    ]);

    editor($rack, $customer)
        ->call('setSide', 'rear')
        ->call('placeCatalog', $pdu->id, 2);

    $item = $rack->items()->first();
    expect($item->side)->toBe('rear');
    expect($item->full_depth)->toBeFalse();
});
