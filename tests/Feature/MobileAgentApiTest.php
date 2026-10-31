<?php

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;

/**
 * Die Auto-Dokumentation in der App: sehen, "jetzt melden", Aufgaben
 * umstellen, Token sperren.
 *
 * Alles haengt an einem Recht - agent_manage. Ohne das ist nichts davon
 * erreichbar, auch nicht lesend.
 */
function agentToken(array $rechte): string
{
    return userWithPermissions($rechte)->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function agentAufbau(): array
{
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id, 'name' => 'Zentrale']);
    [$token] = AgentToken::generateFor($kunde, $standort, 'Zentrale-Token', now()->addYear());
    $agent = AgentInstallation::checkin($token, 'proxmox', 'maschine-1', 'pve01', null, '1.2.3', ['proxmox']);

    return [$kunde, $token, $agent];
}

test('die Liste nennt Agenten mit Aufgaben, Takt und Token', function () {
    [$kunde, $token, $agent] = agentAufbau();

    $antwort = test()->withToken(agentToken(['agent_manage']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/agents")
        ->assertOk();

    expect($antwort->json('agenten.0.hostname'))->toBe('pve01');
    expect($antwort->json('agenten.0.version'))->toBe('1.2.3');
    expect($antwort->json('agenten.0.takt_minuten'))->toBeInt();
    expect($antwort->json('agenten.0.laufen_angefordert'))->toBeFalse();
    expect(collect($antwort->json('agenten.0.aufgaben'))->firstWhere('rolle', 'proxmox')['an'])->toBeTrue();
    expect($antwort->json('token.0.name'))->toBe('Zentrale-Token');
    expect($antwort->json('token.0.gesperrt'))->toBeFalse();
    // Die Takte, die der Server zulaesst - die App bietet nur diese an.
    expect($antwort->json('takte'))->not->toBeEmpty();
});

test('ohne agent_manage ist nichts davon erreichbar', function () {
    [$kunde] = agentAufbau();

    test()->withToken(agentToken(['server_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/agents")
        ->assertForbidden();
});

test('jetzt melden setzt die Anforderung', function () {
    [$kunde, $token, $agent] = agentAufbau();

    $antwort = test()->withToken(agentToken(['agent_manage']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/agents/installations/{$agent->id}/run")
        ->assertOk();

    expect($antwort->json('agent.laufen_angefordert'))->toBeTrue();
    expect($agent->fresh()->run_requested_at)->not->toBeNull();
});

test('Aufgaben und Takt lassen sich umstellen', function () {
    [$kunde, $token, $agent] = agentAufbau();
    $takt = (int) array_key_first(config('custom.agent_intervalle'));

    $antwort = test()->withToken(agentToken(['agent_manage']))
        ->putJson("/api/mobile/customers/{$kunde->slug}/agents/installations/{$agent->id}", [
            'roles' => [],
            'interval_minutes' => $takt,
        ])
        ->assertOk();

    expect($agent->fresh()->roles)->toBe([]);
    expect($agent->fresh()->interval_minutes)->toBe($takt);
    expect(collect($antwort->json('agent.aufgaben'))->firstWhere('rolle', 'proxmox')['an'])->toBeFalse();
});

/** Eine Aufgabe, die es für diese Art nicht gibt, weist der Server ab. */
test('eine unbekannte Aufgabe wird abgewiesen', function () {
    [$kunde, $token, $agent] = agentAufbau();

    test()->withToken(agentToken(['agent_manage']))
        ->putJson("/api/mobile/customers/{$kunde->slug}/agents/installations/{$agent->id}", [
            'roles' => ['kaffeekochen'],
        ])
        ->assertStatus(422);
});

test('ein Token lässt sich sperren und wieder freigeben', function () {
    [$kunde, $token] = agentAufbau();
    $app = agentToken(['agent_manage']);

    test()->withToken($app)
        ->postJson("/api/mobile/customers/{$kunde->slug}/agents/tokens/{$token->id}/lock")
        ->assertOk();
    expect($token->fresh()->locked_at)->not->toBeNull();

    test()->withToken($app)
        ->postJson("/api/mobile/customers/{$kunde->slug}/agents/tokens/{$token->id}/unlock")
        ->assertOk();
    expect($token->fresh()->locked_at)->toBeNull();
});

test('ein Agent eines anderen Kunden ist ein 404', function () {
    [$kunde, $token, $agent] = agentAufbau();
    $fremder = Customer::factory()->create();

    test()->withToken(agentToken(['agent_manage']))
        ->postJson("/api/mobile/customers/{$fremder->slug}/agents/installations/{$agent->id}/run")
        ->assertNotFound();
});
