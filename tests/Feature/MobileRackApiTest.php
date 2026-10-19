<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;

function rackAppNutzer(array $rechte, ?Customer $kunde = null): User
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh();
}

function rackAppToken(User $nutzer): string
{
    return $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function rackAppUmgebung(): array
{
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $rack = Rack::create([
        'customer_id' => $kunde->id, 'site_id' => $standort->id,
        'name' => 'Rack HH-01', 'height_units' => 12, 'location' => 'Serverraum EG',
    ]);
    $server = Server::create([
        'customer_id' => $kunde->id, 'site_id' => $standort->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => 'SRV-01', 'form_factor' => 'rack', 'height_units' => 2,
    ]);

    return [$kunde, $standort, $rack, $server];
}

const RACK_RECHTE = ['rack_viewAny', 'rack_create', 'rack_update', 'rack_delete'];

test('die App zeigt ein Rack mit seinen Einbauten', function () {
    [$kunde, , $rack, $server] = rackAppUmgebung();
    $rack->items()->create([
        'side' => 'front', 'position' => 3, 'height_units' => 2, 'full_depth' => true,
        'device_type' => Server::class, 'device_id' => $server->id,
    ]);

    $this->withToken(rackAppToken(rackAppNutzer(['rack_viewAny'])))
        ->getJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}")
        ->assertOk()
        ->assertJsonPath('name', 'Rack HH-01')
        ->assertJsonPath('hoehe', 12)
        ->assertJsonPath('darf_bearbeiten', false)
        ->assertJsonPath('einbauten.0.name', 'SRV-01')
        ->assertJsonPath('einbauten.0.oberste_he', 4)
        ->assertJsonPath('einbauten.0.geraet', ['typ' => 'server', 'id' => $server->id]);
});

test('ohne Rack-Recht gibt es kein Rack', function () {
    [$kunde, , $rack] = rackAppUmgebung();

    $this->withToken(rackAppToken(rackAppNutzer(['server_viewAny'])))
        ->getJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}")
        ->assertForbidden();
});

test('ein Kundennutzer kommt nicht an fremde Racks', function () {
    [$kunde, , $rack] = rackAppUmgebung();
    $eigener = Customer::factory()->create();
    $token = rackAppToken(rackAppNutzer(RACK_RECHTE, $eigener));

    // Fremder Kunde in der Adresse: isCustomer.
    $this->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}")
        ->assertForbidden();

    // Eigener Kunde, fremdes Rack: scoped binding.
    $this->withToken($token)
        ->deleteJson("/api/mobile/customers/{$eigener->slug}/racks/{$rack->id}")
        ->assertNotFound();

    expect($rack->fresh())->not->toBeNull();
});

test('ein Rack laesst sich anlegen, aendern und loeschen', function () {
    [$kunde, $standort] = rackAppUmgebung();
    $token = rackAppToken(rackAppNutzer(RACK_RECHTE));

    $id = $this->withToken($token)
        ->postJson("/api/mobile/customers/{$kunde->slug}/racks", [
            'site_id' => $standort->id, 'name' => 'Rack Neu', 'height_units' => 24,
        ])
        ->assertCreated()
        ->assertJsonPath('standort.id', $standort->id)
        ->json('id');

    $this->withToken($token)
        ->putJson("/api/mobile/customers/{$kunde->slug}/racks/{$id}", [
            'site_id' => $standort->id, 'name' => 'Rack Umbenannt', 'height_units' => 42, 'note' => 'Schluessel beim Empfang',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Rack Umbenannt')
        ->assertJsonPath('notiz', 'Schluessel beim Empfang');

    $this->withToken($token)
        ->deleteJson("/api/mobile/customers/{$kunde->slug}/racks/{$id}")
        ->assertNoContent();

    expect(Rack::find($id))->toBeNull()
        ->and(Rack::withTrashed()->find($id))->not->toBeNull();
});

test('ein Standort eines anderen Kunden wird abgelehnt', function () {
    [$kunde] = rackAppUmgebung();
    $fremd = Site::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->postJson("/api/mobile/customers/{$kunde->slug}/racks", [
            'site_id' => $fremd->id, 'name' => 'Rack Neu', 'height_units' => 24,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('site_id');
});

test('ein Rack wird nicht kleiner als sein oberster Einbau', function () {
    [$kunde, $standort, $rack] = rackAppUmgebung();
    $rack->items()->create(['side' => 'front', 'position' => 10, 'height_units' => 2, 'name' => 'Fachboden']);

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->putJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}", [
            'site_id' => $standort->id, 'name' => 'Rack HH-01', 'height_units' => 8,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('height_units');

    expect($rack->fresh()->height_units)->toBe(12);
});

test('ohne Position kommt ein Geraet auf die unterste freie HE', function () {
    [$kunde, , $rack, $server] = rackAppUmgebung();
    $rack->items()->create(['side' => 'front', 'position' => 1, 'height_units' => 1, 'name' => 'Fachboden']);

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->postJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/items", [
            'seite' => 'front', 'typ' => 'server', 'id' => $server->id,
        ])
        ->assertCreated()
        ->assertJsonPath('position', 2)
        ->assertJsonPath('hoehe', 2);
});

test('dieselben Kollisionsregeln wie im Browser', function () {
    [$kunde, , $rack, $server] = rackAppUmgebung();
    $rack->items()->create(['side' => 'front', 'position' => 4, 'height_units' => 1, 'name' => 'Fachboden']);

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->postJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/items", [
            'seite' => 'front', 'typ' => 'server', 'id' => $server->id, 'position' => 3,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rack');

    expect($rack->items()->count())->toBe(1);
});

test('ein Geraet eines anderen Kunden laesst sich nicht einbauen', function () {
    [$kunde, , $rack] = rackAppUmgebung();
    [, , , $fremderServer] = rackAppUmgebung();

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->postJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/items", [
            'seite' => 'front', 'typ' => 'server', 'id' => $fremderServer->id,
        ])
        ->assertForbidden();
});

test('ein Katalogelement wird eingebaut, verschoben, vergroessert und entfernt', function () {
    [$kunde, , $rack] = rackAppUmgebung();
    $katalog = RackCatalogItem::create(['name' => 'Fachboden', 'height_units' => 1, 'full_depth' => false, 'appearance' => 'shelf']);
    $token = rackAppToken(rackAppNutzer(RACK_RECHTE));
    $basis = "/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/items";

    $id = $this->withToken($token)
        ->postJson($basis, ['seite' => 'rear', 'katalog_id' => $katalog->id, 'position' => 5])
        ->assertCreated()
        ->assertJsonPath('name', 'Fachboden')
        ->assertJsonPath('seite', 'rear')
        ->assertJsonPath('volle_tiefe', false)
        ->json('id');

    $this->withToken($token)
        ->patchJson("{$basis}/{$id}", ['position' => 8, 'hoehe' => 2])
        ->assertOk()
        ->assertJsonPath('position', 8)
        ->assertJsonPath('oberste_he', 9);

    $this->withToken($token)->deleteJson("{$basis}/{$id}")->assertNoContent();

    expect($rack->items()->count())->toBe(0);
});

test('ein Einbau eines anderen Racks ist hier nicht zu finden', function () {
    [$kunde, $standort, $rack] = rackAppUmgebung();
    $anderes = Rack::create(['customer_id' => $kunde->id, 'site_id' => $standort->id, 'name' => 'Rack 2', 'height_units' => 12]);
    $item = $anderes->items()->create(['side' => 'front', 'position' => 1, 'height_units' => 1, 'name' => 'Fachboden']);

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->deleteJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/items/{$item->id}")
        ->assertNotFound();

    expect($item->fresh())->not->toBeNull();
});

test('nur lesen heisst nicht umbauen', function () {
    [$kunde, , $rack, $server] = rackAppUmgebung();
    $token = rackAppToken(rackAppNutzer(['rack_viewAny']));

    $this->withToken($token)
        ->postJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/items", [
            'seite' => 'front', 'typ' => 'server', 'id' => $server->id,
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/palette")
        ->assertForbidden();
});

test('die Palette nennt die freien Geraete und den Katalog', function () {
    [$kunde, , $rack, $server] = rackAppUmgebung();
    RackCatalogItem::create(['name' => 'Bürstenleiste', 'height_units' => 1, 'appearance' => 'brush']);

    $this->withToken(rackAppToken(rackAppNutzer(RACK_RECHTE)))
        ->getJson("/api/mobile/customers/{$kunde->slug}/racks/{$rack->id}/palette")
        ->assertOk()
        ->assertJsonPath('geraete.0.typ', 'server')
        ->assertJsonPath('geraete.0.eintraege.0', ['id' => $server->id, 'name' => 'SRV-01', 'hoehe' => 2])
        ->assertJsonPath('katalog.0.name', 'Bürstenleiste');
});
