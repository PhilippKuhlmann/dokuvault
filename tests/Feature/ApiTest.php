<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

function apiNutzer(array $rechte, ?Customer $kunde = null): array
{
    $nutzer = userWithPermissions($rechte);

    if ($kunde) {
        $nutzer->forceFill(['customer_id' => $kunde->id])->save();
    }

    $nutzer = $nutzer->fresh();

    return [$nutzer, $nutzer->createToken('probe')->plainTextToken];
}

function einServer(Customer $kunde, string $name, string $kennwort = 'GEHEIM'): Server
{
    return Server::create([
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => $name,
        'bmcPassword' => $kennwort,
        'form_factor' => 'rack',
        'height_units' => 1,
    ]);
}

test('ohne Token kommt niemand an die Schnittstelle', function () {
    $this->getJson('/api/servers')->assertStatus(401);
    $this->getJson('/api/customers')->assertStatus(401);
});

test('die Serverliste ist überhaupt erreichbar', function () {
    // Sie war es nicht: "/{customer}" passt auf jedes einzelne Segment und
    // fing "GET /api/servers" ab - die Anfrage suchte einen Kunden namens
    // "servers" und endete in einem 404.
    [$nutzer, $token] = apiNutzer(['server_viewAny']);
    einServer(Customer::factory()->create(), 'Irgendein Server');

    $this->withToken($token)->getJson('/api/servers')
        ->assertStatus(200)
        ->assertJsonCount(1);
});

test('ein Kundentoken sieht nur die eigenen Server', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();

    einServer($kundeA, 'Eigener');
    $fremder = einServer($kundeB, 'Fremder');

    [$nutzer, $token] = apiNutzer(['server_viewAny'], $kundeA);

    $this->withToken($token)->getJson('/api/servers')
        ->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => 'Eigener'])
        ->assertJsonMissing(['name' => 'Fremder']);

    $this->withToken($token)->getJson('/api/servers/'.$fremder->id)->assertStatus(403);
});

test('ein Kundentoken legt keinen Server bei einem fremden Kunden an', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();
    $fremderStandort = Site::factory()->create(['customer_id' => $kundeB->id]);

    [$nutzer, $token] = apiNutzer(['server_create'], $kundeA);

    $this->withToken($token)->postJson('/api/servers', [
        'site_id' => $fremderStandort->id,
        'name' => 'Eingeschleust',
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'form_factor' => 'rack',
        'height_units' => 1,
        'full_depth' => 1,
    ])->assertStatus(422);

    expect(Server::where('name', 'Eingeschleust')->exists())->toBeFalse();
});

test('kein Kennwort steht in einer Antwort der Schnittstelle', function () {
    // Bisher stand dort der verschlüsselte Wert - nutzlos für den Aufrufer und
    // ein Leck in dem Moment, in dem jemand aus dem Attribut einen Cast macht.
    $kunde = Customer::factory()->create();
    $server = einServer($kunde, 'Mit Kennwort', 'GEHEIM-BMC');

    [$nutzer, $token] = apiNutzer(['server_viewAny'], $kunde);

    $antwort = $this->withToken($token)->getJson('/api/servers/'.$server->id);

    $antwort->assertStatus(200)->assertJsonMissingPath('bmcPassword');

    expect($antwort->getContent())->not->toContain('GEHEIM-BMC')
        // Auch nicht als Chiffrat: Laravels Chiffrate beginnen mit "eyJpdiI6".
        ->and($antwort->getContent())->not->toContain('eyJpdiI6');
});

test('die Kundenliste kommt auch ohne Suchbegriff', function () {
    // Ohne ?name= endete der Aufruf in einem 500er.
    Customer::factory()->count(2)->create();

    [$nutzer, $token] = apiNutzer([]);

    $this->withToken($token)->getJson('/api/customers')
        ->assertStatus(200)
        ->assertJsonCount(2);
});

test('ein Kundentoken sieht in der Kundenliste nur sich selbst', function () {
    $kundeA = Customer::factory()->create();
    Customer::factory()->create();

    [$nutzer, $token] = apiNutzer([], $kundeA);

    $this->withToken($token)->getJson('/api/customers')
        ->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $kundeA->id]);
});

test('ein Kundentoken kommt nicht an einen fremden Kunden', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();

    [$nutzer, $token] = apiNutzer([], $kundeA);

    $this->withToken($token)->getJson('/api/'.$kundeB->slug)->assertStatus(403);
    $this->withToken($token)->getJson('/api/'.$kundeB->slug.'/sites')->assertStatus(403);
});

// --- Agenten schreiben nur beim eigenen Kunden ------------------------------

/** Das Kleinste, was der Proxmox-Agent melden darf. */
function agentMeldung(string $bezeichner = 'machine-abc'): array
{
    return ['host' => ['identifier' => $bezeichner, 'hostname' => 'pve01']];
}

test('ein Agent-Token trifft kein Gerät eines anderen Kunden', function () {
    // Der Kunde kommt aus dem Token, nie aus der Anfrage. Die Probe aufs
    // Exempel: derselbe Bezeichner bei zwei Kunden. Ohne customer_id im
    // Suchschlüssel würde der Agent des einen den Server des anderen
    // überschreiben.
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();

    $standortA = Site::factory()->create(['customer_id' => $kundeA->id]);
    $os = OperatingSystem::firstOrCreate(['name' => 'Debian 13']);

    $fremder = Server::create([
        'customer_id' => $kundeB->id,
        'site_id' => Site::factory()->create(['customer_id' => $kundeB->id])->id,
        'operating_system_id' => $os->id,
        'name' => 'Fremder Host',
        'agent_identifier' => 'machine-abc',
        'form_factor' => 'rack',
        'height_units' => 1,
    ]);

    [$token, $plain] = AgentToken::generateFor($kundeA, $standortA, 'PVE');

    $this->withToken($plain)->postJson('/api/agent/proxmox', agentMeldung())->assertOk();

    // Beim fremden Kunden hat sich nichts geändert ...
    expect($fremder->fresh()->name)->toBe('Fremder Host')
        ->and($fremder->fresh()->customer_id)->toBe($kundeB->id);

    // ... und beim eigenen ist ein neuer Server entstanden.
    expect(Server::where('customer_id', $kundeA->id)->where('agent_identifier', 'machine-abc')->exists())
        ->toBeTrue();
});

test('ein falscher Agent-Token kommt nicht durch', function () {
    $this->withToken('doc_erfunden')->postJson('/api/agent/proxmox', agentMeldung())
        ->assertStatus(401);

    $this->postJson('/api/agent/proxmox', agentMeldung())->assertStatus(401);
});
