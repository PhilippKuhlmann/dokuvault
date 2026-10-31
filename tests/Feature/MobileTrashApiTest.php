<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

/**
 * Der Papierkorb in der App - dieselben Rechte wie im Browser.
 *
 * Sehen braucht `see_hidden` und das Recht auf die Art, Zurueckholen
 * zusaetzlich das Recht zu loeschen: Es macht ein Loeschen rueckgaengig.
 */
function papierkorbToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function papierkorbServer(Customer $kunde, string $name): Server
{
    return Server::factory()->create([
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Debian 13'])->id,
        'name' => $name,
    ]);
}

test('der Papierkorb nennt das Gelöschte mit Art und Zeitpunkt', function () {
    $kunde = Customer::factory()->create();
    $server = papierkorbServer($kunde, 'SRV-WEG');
    $server->delete();

    $antwort = test()->withToken(papierkorbToken(['see_hidden', 'server_viewAny', 'server_delete']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/trash")
        ->assertOk();

    expect($antwort->json('eintraege.0.name'))->toBe('SRV-WEG');
    expect($antwort->json('eintraege.0.typ'))->toBe('server');
    expect($antwort->json('eintraege.0.id'))->toBe($server->id);
    expect($antwort->json('eintraege.0.geloescht_am'))->not->toBeNull();
    expect($antwort->json('eintraege.0.darf_wiederherstellen'))->toBeTrue();
});

test('ohne see_hidden bleibt der Papierkorb zu', function () {
    $kunde = Customer::factory()->create();

    test()->withToken(papierkorbToken(['server_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/trash")
        ->assertForbidden();
});

test('eine Art ohne Leserecht steht nicht im Papierkorb', function () {
    $kunde = Customer::factory()->create();
    papierkorbServer($kunde, 'SRV-VERBORGEN')->delete();

    $antwort = test()->withToken(papierkorbToken(['see_hidden', 'networkswitch_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/trash")
        ->assertOk();

    expect($antwort->json('eintraege'))->toBe([]);
});

test('Zurückholen braucht das Recht zu löschen', function () {
    $kunde = Customer::factory()->create();
    $server = papierkorbServer($kunde, 'SRV-WEG');
    $server->delete();

    test()->withToken(papierkorbToken(['see_hidden', 'server_viewAny']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/trash/server/{$server->id}/restore")
        ->assertForbidden();

    expect(Server::find($server->id))->toBeNull();
});

test('mit dem Recht kommt das Objekt zurück und die App kann es öffnen', function () {
    $kunde = Customer::factory()->create();
    $server = papierkorbServer($kunde, 'SRV-WEG');
    $server->delete();

    $antwort = test()->withToken(papierkorbToken(['see_hidden', 'server_viewAny', 'server_delete']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/trash/server/{$server->id}/restore")
        ->assertOk();

    expect($antwort->json('objekt.typ'))->toBe('server');
    expect($antwort->json('objekt.id'))->toBe($server->id);
    expect(Server::find($server->id))->not->toBeNull();
});

test('ein fremdes Objekt lässt sich nicht zurückholen', function () {
    $eigener = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $server = papierkorbServer($fremder, 'SRV-FREMD');
    $server->delete();

    test()->withToken(papierkorbToken(['see_hidden', 'server_viewAny', 'server_delete']))
        ->postJson("/api/mobile/customers/{$eigener->slug}/trash/server/{$server->id}/restore")
        ->assertNotFound();
});

test('eine Art außerhalb der Liste ergibt 404', function () {
    $kunde = Customer::factory()->create();

    test()->withToken(papierkorbToken(['see_hidden', 'server_viewAny', 'server_delete']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/trash/users/1/restore")
        ->assertNotFound();
});
