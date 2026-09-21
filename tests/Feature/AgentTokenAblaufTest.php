<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;

/**
 * Kunde + Standort in einem Griff - jeder Test hier braucht beides.
 */
function agentKundeStandort(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('ein abgelaufener Token wird abgewiesen', function () {
    [$customer, $site] = agentKundeStandort();
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'Alt', now()->subDay());

    $this->withToken($plain)->postJson('/api/agent/proxmox', [])
        ->assertStatus(401);
});

test('ein Token mit Frist in der Zukunft kommt durch die Anmeldung', function () {
    [$customer, $site] = agentKundeStandort();
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'Frisch', now()->addYear());

    // Nicht 401: Der Token wird angenommen. Was der Endpunkt dann mit einem
    // leeren Rumpf macht (Validierung), ist hier nicht die Frage.
    $antwort = $this->withToken($plain)->postJson('/api/agent/proxmox', []);
    expect($antwort->status())->not->toBe(401);
});

test('ein Altbestands-Token ohne Frist bleibt gültig', function () {
    [$customer, $site] = agentKundeStandort();
    // generateFor ohne Frist bildet den Zustand vor der Pflicht ab.
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'Altbestand');

    expect($token->expires_at)->toBeNull();

    $antwort = $this->withToken($plain)->postJson('/api/agent/proxmox', []);
    expect($antwort->status())->not->toBe(401);
});

test('ein neuer Token ohne Ablaufdatum wird nicht angelegt', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $site] = agentKundeStandort();

    $this->post(route('agent.store', $customer), ['name' => 'Ohne Frist', 'site_id' => $site->id])
        ->assertSessionHasErrors('expires_at');

    expect(AgentToken::where('customer_id', $customer->id)->count())->toBe(0);
});

test('ein Ablaufdatum in der Vergangenheit wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $site] = agentKundeStandort();

    $this->post(route('agent.store', $customer), [
        'name' => 'Rückdatiert', 'site_id' => $site->id,
        'expires_at' => now()->subDay()->format('Y-m-d'),
    ])->assertSessionHasErrors('expires_at');
});

test('erneuern macht den alten Klartext ungültig und setzt eine neue Frist', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $site] = agentKundeStandort();
    [$token, $alt] = AgentToken::generateFor($customer, $site, 'Zu erneuern', now()->addMonth());

    // Der alte Wert funktioniert - noch.
    expect($this->withToken($alt)->postJson('/api/agent/proxmox', [])->status())->not->toBe(401);

    $this->post(route('agent.erneuern', [$customer, $token]));
    $neu = session('newToken');

    expect($neu)->not->toBe($alt);

    // Der alte Wert ist ab sofort ungültig, der neue kommt durch.
    $this->withToken($alt)->postJson('/api/agent/proxmox', [])->assertStatus(401);
    expect($this->withToken($neu)->postJson('/api/agent/proxmox', [])->status())->not->toBe(401);

    // Die Zuordnung bleibt, die Frist ist neu und in der Zukunft.
    $token->refresh();
    expect($token->customer_id)->toBe($customer->id)
        ->and($token->site_id)->toBe($site->id)
        ->and($token->expires_at->isFuture())->toBeTrue();
});

test('die Token-Liste zeigt Ablauf und Erneuern-Knopf', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $site] = agentKundeStandort();
    AgentToken::generateFor($customer, $site, 'Mit Frist', now()->addYear());

    $this->get(route('agent.index', $customer))
        ->assertOk()
        ->assertSee(__('Läuft ab'))
        ->assertSee(__('Erneuern'));
});

test('ein Altbestands-Token ist in der Liste als unbegrenzt gekennzeichnet', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $site] = agentKundeStandort();
    AgentToken::generateFor($customer, $site, 'Altbestand');

    $this->get(route('agent.index', $customer))
        ->assertOk()
        ->assertSee(__('unbegrenzt (Altbestand)'));
});
