<?php

use App\Models\Customer;
use App\Models\Site;

/**
 * Der Netzplan in der App.
 *
 * Gebaut wird er von derselben Klasse wie im Browser (dafuer gibt es
 * NetworkMapTest). Hier geht es darum, was davon ueber die Leitung geht:
 * dass jeder Knoten einen Weg mitbringt, den die App gehen kann, dass der
 * Standortfilter greift und dass die Rechte halten.
 */
function netzplanToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

test('der Netzplan nennt Standort, Gateway, VLAN und die Geräte darin', function () {
    [$kunde, $standort] = networkMapFixture();
    $token = netzplanToken(['network_viewAny', 'firewall_viewAny', 'computer_viewAny', 'internetconnection_viewAny']);

    $antwort = test()->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/network-map")
        ->assertOk();

    expect($antwort->json('standorte.0.name'))->toBe('Zentrale');
    expect($antwort->json('standorte.0.spalten.0.gateway.name'))->toBe('FW-Zentrale');
    expect($antwort->json('standorte.0.spalten.0.gateway.typ'))->toBe('firewall');
    expect($antwort->json('standorte.0.spalten.0.uplinks.0.name'))->toBe('Telekom');
    expect($antwort->json('standorte.0.spalten.0.netze.0.netz.name'))->toBe('Clients · VLAN 10');
    expect($antwort->json('standorte.0.spalten.0.netze.0.netz.adresse'))->toBe('10.0.10.0/24');
    expect($antwort->json('standorte.0.spalten.0.netze.0.gruppen.0.titel'))->toBe('Computer');
    expect($antwort->json('standorte.0.spalten.0.netze.0.gruppen.0.geraete.0.name'))->toBe('PC-Empfang');
    expect($antwort->json('standorte.0.spalten.0.netze.0.gruppen.0.geraete.0.adresse'))->toBe('10.0.10.50');
    expect($antwort->json('standorte.0.spalten.0.netze.0.gruppen.0.geraete.0.typ'))->toBe('computer');
});

/**
 * Ohne das Recht auf die Geraeteart laesst schon der Browser den Verweis
 * weg. Dann darf auch die App keinen Weg anbieten - der Name steht da, der
 * Weg nicht.
 */
test('ohne Recht auf die Geräteart bleibt der Knoten ohne Weg', function () {
    [$kunde] = networkMapFixture();
    $token = netzplanToken(['network_viewAny', 'firewall_viewAny']);

    $antwort = test()->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/network-map")
        ->assertOk();

    $computer = $antwort->json('standorte.0.spalten.0.netze.0.gruppen.0.geraete.0');

    expect($computer['name'])->toBe('PC-Empfang');
    expect($computer['typ'])->toBeNull();
});

test('der Standortfilter blendet andere Standorte aus', function () {
    [$kunde, $standort] = networkMapFixture();
    $zweiter = Site::factory()->create(['customer_id' => $kunde->id, 'name' => 'Filiale']);
    $token = netzplanToken(['network_viewAny', 'firewall_viewAny', 'computer_viewAny']);

    $alle = test()->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/network-map")
        ->assertOk();
    expect(collect($alle->json('standorte'))->pluck('name')->sort()->values()->all())
        ->toBe(['Filiale', 'Zentrale']);

    $gefiltert = test()->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/network-map?site={$zweiter->id}")
        ->assertOk();
    expect(collect($gefiltert->json('standorte'))->pluck('name')->all())->toBe(['Filiale']);
});

/** Ein fremder Standort im Filter zaehlt als kein Filter, nicht als Fehler. */
test('ein fremder Standort im Filter wird übergangen', function () {
    [$kunde] = networkMapFixture();
    $fremder = Site::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    $token = netzplanToken(['network_viewAny', 'firewall_viewAny']);

    $antwort = test()->withToken($token)
        ->getJson("/api/mobile/customers/{$kunde->slug}/network-map?site={$fremder->id}")
        ->assertOk();

    expect($antwort->json('standorte.0.name'))->toBe('Zentrale');
});

test('der Netzplan braucht das Recht, VLANs zu sehen', function () {
    [$kunde] = networkMapFixture();

    test()->withToken(netzplanToken(['firewall_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/network-map")
        ->assertForbidden();
});
