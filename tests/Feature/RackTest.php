<?php

use App\Livewire\RackEditor;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\RackItem;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function customerWithRack(int $heightUnits = 42): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack Test', 'height_units' => $heightUnits,
    ]);

    return [$customer, $site, $rack];
}

/** Server ohne Factory: die wuerfelt eine operating_system_id, die es im Test nicht gibt. */
function rackTestServer(Customer $customer, Site $site, string $name = 'SRV-01'): Server
{
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2025 Standard']);

    return Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => $name, 'operating_system_id' => $os->id,
    ]);
}

// --- CRUD ---

test('store legt ein Rack an und leitet in den Editor', function () {
    $this->actingAs(userWithPermissions(['rack_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $response = $this->post("/{$customer->slug}/rack", [
        'site_id' => $site->id, 'name' => 'Rack A', 'height_units' => 42,
    ]);

    $rack = Rack::first();
    $response->assertRedirect("/{$customer->slug}/rack/{$rack->id}/edit");
    $this->assertDatabaseHas('racks', ['name' => 'Rack A', 'customer_id' => $customer->id]);
});

test('store scheitert ohne Pflichtfelder', function () {
    $this->actingAs(userWithPermissions(['rack_create']));
    $customer = Customer::factory()->create();

    $this->post("/{$customer->slug}/rack", [])->assertSessionHasErrors(['name', 'site_id', 'height_units']);
    expect(Rack::count())->toBe(0);
});

test('update kann das Rack nicht kleiner machen als der oberste Einbau', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack(42);
    $rack->items()->create(['position' => 40, 'height_units' => 2, 'name' => 'Patchfeld 24 Port']);

    $this->patch("/{$customer->slug}/rack/{$rack->id}", [
        'site_id' => $site->id, 'name' => $rack->name, 'height_units' => 24,
    ])->assertSessionHasErrors('height_units');

    expect($rack->fresh()->height_units)->toBe(42);
});

test('destroy löscht weich, Papierkorb kann wiederherstellen und die Einbauten bleiben', function () {
    $this->actingAs(userWithPermissions(['rack_delete', 'see_hidden']));
    [$customer, $site, $rack] = customerWithRack();
    $rack->items()->create(['position' => 1, 'height_units' => 1, 'name' => 'Blindplatte 1 HE']);

    $this->delete("/{$customer->slug}/rack/{$rack->id}");
    $this->assertSoftDeleted('racks', ['id' => $rack->id]);

    $this->post("/{$customer->slug}/trash/rack/{$rack->id}/restore");
    expect($rack->fresh()->deleted_at)->toBeNull();
    expect($rack->fresh()->items()->count())->toBe(1);
});

test('Liste zeigt Racks samt Belegung, ohne Berechtigung 403', function () {
    $this->actingAs(userWithPermissions(['rack_viewAny']));
    [$customer, $site, $rack] = customerWithRack();
    $rack->update(['name' => 'SichtbaresRack']);
    $rack->items()->create(['position' => 3, 'height_units' => 1, 'name' => 'Rangierfeld']);

    $this->get("/{$customer->slug}/rack")->assertStatus(200)
        ->assertSee('SichtbaresRack')->assertSee('Rangierfeld');

    $this->actingAs(userWithPermissions([]));
    $this->get("/{$customer->slug}/rack")->assertStatus(403);
});

// --- Editor ---

test('placeDevice baut ein dokumentiertes Gerät ein', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack();
    $server = rackTestServer($customer, $site);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $server->id, 10)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rack_items', [
        'rack_id' => $rack->id, 'position' => 10,
        'device_type' => Server::class, 'device_id' => $server->id,
    ]);
});

test('Gerät eines fremden Kunden lässt sich nicht einbauen', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack();

    $other = Customer::factory()->create();
    $otherSite = Site::factory()->create(['customer_id' => $other->id]);
    $foreign = rackTestServer($other, $otherSite, 'SRV-FREMD');

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $foreign->id, 10)
        ->assertStatus(403);

    expect(RackItem::count())->toBe(0);
});

test('ein bereits verbautes Gerät kann kein zweites Mal eingebaut werden', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack();
    $server = rackTestServer($customer, $site);

    $component = Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $server->id, 10)
        ->call('placeDevice', 'server', $server->id, 20)
        ->assertHasErrors('rack');

    expect(RackItem::count())->toBe(1);
});

test('Kollision: belegte Höheneinheiten lehnen einen zweiten Einbau ab', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack();
    $rack->items()->create(['position' => 10, 'height_units' => 2, 'name' => 'Fachboden 2 HE']);

    // Blindplatte 3 HE ab U9 wuerde U9-U11 belegen und U10/U11 schneiden
    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeCatalog', 'blindplatte3', 9)
        ->assertHasErrors('rack');

    expect(RackItem::count())->toBe(1);
});

test('move verschiebt auf freie Position und lehnt Kollision ab', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack();
    $a = $rack->items()->create(['position' => 1, 'height_units' => 1, 'name' => 'Rangierfeld']);
    $rack->items()->create(['position' => 5, 'height_units' => 1, 'name' => 'Patchfeld 24 Port']);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('move', $a->id, 20)->assertHasNoErrors()
        ->call('move', $a->id, 5)->assertHasErrors('rack');

    expect($a->fresh()->position)->toBe(20);
});

test('placeCatalog kennt nur Katalog-Schlüssel und respektiert die Rackhöhe', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack(12);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeCatalog', 'gibtsnicht', 1)->assertHasErrors('rack')
        ->call('placeCatalog', 'blindplatte3', 11)->assertHasErrors('rack')   // U11-U13 > 12 HE
        ->call('placeCatalog', 'blindplatte3', 10)->assertHasNoErrors();      // U10-U12 passt

    expect(RackItem::count())->toBe(1);
});

test('setHeight wächst nach oben und stößt am Rackdeckel an', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $rack] = customerWithRack(12);
    $item = $rack->items()->create(['position' => 11, 'height_units' => 1, 'name' => 'Fachboden 1 HE']);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('setHeight', $item->id, 2)->assertHasNoErrors()
        ->call('setHeight', $item->id, 3)->assertHasErrors('rack');   // U11-U13 > 12 HE

    expect($item->fresh()->height_units)->toBe(2);
});

test('Editor verweigert Nutzern ohne rack_update jede Aktion', function () {
    $this->actingAs(userWithPermissions(['rack_viewAny']));
    [$customer, $site, $rack] = customerWithRack();

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->assertStatus(403);
});

// --- Struktur-Invariante ---

test('jeder Eintrag in rack_device_types zeigt auf ein Model mit customer_id und name', function () {
    foreach (config('custom.rack_device_types') as $key => [$class, $label]) {
        expect(class_exists($class))->toBeTrue("Klasse {$class} ({$key}) existiert nicht");

        $table = (new $class)->getTable();
        expect(Schema::hasColumn($table, 'customer_id'))
            ->toBeTrue("{$table} hat keine customer_id-Spalte");
        expect(Schema::hasColumn($table, 'name'))
            ->toBeTrue("{$table} hat keine name-Spalte");
    }
});
