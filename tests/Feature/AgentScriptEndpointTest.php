<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;

/*
 * The Windows service fetches the current script before every run, so
 * updates reach installed services without a new download.
 */

function dienstToken(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return AgentToken::generateFor($customer, $site, 'Dienst', now()->addMonth());
}

test('the service gets the current script with its own token filled in', function () {
    [, $plain] = dienstToken();

    $antwort = $this->withToken($plain)->get('/api/agent/script/windows-ad')->assertOk();

    expect($antwort->headers->get('Content-Type'))->toContain('text/plain');
    expect($antwort->getContent())
        ->toContain($plain)
        ->toContain(url('/api/agent/windows-ad'))
        ->not->toContain('__AGENT_TOKEN__')
        ->not->toContain('__API_URL__');
});

test('without a valid token there is no script', function () {
    $this->get('/api/agent/script/windows-server')->assertUnauthorized();
    $this->withToken('doc_falsch')->get('/api/agent/script/windows-server')->assertUnauthorized();
});

test('an expired token gets no script', function () {
    [$token, $plain] = dienstToken();
    $token->forceFill(['expires_at' => now()->subDay()])->save();

    $this->withToken($plain)->get('/api/agent/script/windows-server')->assertUnauthorized();
});

test('agents that need credentials or unknown names are not served', function (string $agent) {
    [, $plain] = dienstToken();

    $this->withToken($plain)->get('/api/agent/script/'.$agent)->assertNotFound();
})->with(['unifi', 'vmware', 'microsoft365', 'proxmox', 'gibt-es-nicht']);

test('after creating a token the agent page offers the service with a ready install line', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $seite = $this->followingRedirects()
        ->post(route('agent.store', $customer), ['name' => 'Dienst', 'site_id' => $site->id, 'expires_at' => now()->addMonth()->format('Y-m-d')])
        ->assertOk();

    $seite->assertSee(route('agent.dienst', $customer), false)
        ->assertSee(route('agent.dienst.proxmox', $customer), false)
        ->assertSee('dokuvault-agent.exe install -url '.url('/').' -token '.session('newToken'), false);

    expect(file_exists(public_path('downloads/dokuvault-agent.exe')))->toBeTrue();
});

function tokenFuerDienstExe(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    test()->post(route('agent.store', $customer), ['name' => 'Exe', 'site_id' => $site->id, 'expires_at' => now()->addMonth()->format('Y-m-d')]);

    return [$customer, session('newToken')];
}

test('the preconfigured exe carries URL and token at its end', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $plain] = tokenFuerDienstExe();

    $antwort = $this->get(route('agent.dienst', $customer))->assertOk();
    $inhalt = $antwort->getContent();

    // Layout read by agent/windows-service/embedded.go: JSON, uint32 LE, marker.
    expect(substr($inhalt, -8))->toBe('DVCFG001');
    $laenge = unpack('V', substr($inhalt, -12, 4))[1];
    $json = json_decode(substr($inhalt, -12 - $laenge, $laenge), true);

    expect($json)->toBe(['url' => url('/'), 'token' => $plain]);
    expect(str_starts_with($inhalt, 'MZ'))->toBeTrue();
    expect($antwort->headers->get('Content-Disposition'))->toContain('dokuvault-agent-'.$customer->slug.'.exe');
});

test('the preconfigured exe is only available shortly after creating a token, for that customer', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer] = tokenFuerDienstExe();
    $anderer = Customer::factory()->create();

    $this->get(route('agent.dienst', $anderer))->assertStatus(410);

    $this->travel(31)->minutes();
    $this->get(route('agent.dienst', $customer))->assertStatus(410);
});

test('without the right to agents there is no exe', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    $this->get(route('agent.dienst', $customer))->assertForbidden();
});

test('the Proxmox agent fetches the bash script, unattended bash agents only', function () {
    [, $plain] = dienstToken();

    $this->withToken($plain)->get('/api/agent/script/proxmox?shell=bash')
        ->assertOk()
        ->assertSee('#!/usr/bin/env bash', false)
        ->assertSee($plain, false)
        ->assertSee(url('/api/agent/proxmox'), false);

    // Without ?shell=bash the Windows service asks - Proxmox has no PowerShell.
    $this->withToken($plain)->get('/api/agent/script/proxmox')->assertNotFound();
    // UniFi has a bash variant, but needs credentials at call time.
    $this->withToken($plain)->get('/api/agent/script/unifi?shell=bash')->assertNotFound();
});

test('the Proxmox installer comes with address and token filled in', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$customer, $plain] = tokenFuerDienstExe();

    $antwort = $this->get(route('agent.dienst.proxmox', $customer))->assertOk();
    $inhalt = $antwort->getContent();

    expect($inhalt)
        ->toContain('BASE_URL="'.rtrim(url('/'), '/').'"')
        ->toContain('TOKEN="'.$plain.'"')
        ->toContain('/api/agent/script/proxmox?shell=bash')
        ->not->toContain('__BASE_URL__')
        ->not->toContain('__AGENT_TOKEN__');
    expect($antwort->headers->get('Content-Disposition'))->toContain('dokuvault-agent-proxmox.sh');

    $this->travel(31)->minutes();
    $this->get(route('agent.dienst.proxmox', $customer))->assertStatus(410);
});
