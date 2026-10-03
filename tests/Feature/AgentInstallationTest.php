<?php

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;

/*
 * Installed agents report in before every run and get the roles ticked on
 * the agent page. Two DCs of one domain: both report as servers, only the
 * first one reports AD.
 */

function installationToken(?Customer $customer = null): array
{
    $customer ??= Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return AgentToken::generateFor($customer, $site, 'Dienst', now()->addMonth());
}

function checkinAls(string $plain, string $machine, array $detected, ?string $domain = 'firma.local')
{
    return test()->withToken($plain)->postJson('/api/agent/checkin', [
        'kind' => 'windows',
        'machine_id' => $machine,
        'hostname' => strtoupper($machine),
        'domain' => $domain,
        'version' => '26.10.03-1200-abc',
        'detected' => $detected,
    ]);
}

test('the first check-in records the machine and assigns what it detected', function () {
    [$token, $plain] = installationToken();

    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad', 'unbekannt'])
        ->assertOk()
        ->assertExactJson(['roles' => ['windows-server', 'windows-ad']]);

    $installation = AgentInstallation::sole();
    expect($installation->customer_id)->toBe($token->customer_id)
        ->and($installation->agent_token_id)->toBe($token->id)
        ->and($installation->hostname)->toBe('DC01')
        ->and($installation->domain)->toBe('firma.local')
        ->and($installation->last_seen_at)->not->toBeNull();
});

test('the second DC of a domain reports as server but not AD', function () {
    [, $plain] = installationToken();

    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad']);
    checkinAls($plain, 'dc02', ['windows-server', 'windows-ad'])
        ->assertExactJson(['roles' => ['windows-server']]);
});

test('a DC of another domain gets AD as well', function () {
    [, $plain] = installationToken();

    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad'], 'firma.local');
    checkinAls($plain, 'dc-tochter', ['windows-server', 'windows-ad'], 'tochter.local')
        ->assertExactJson(['roles' => ['windows-server', 'windows-ad']]);
});

test('another customer does not block AD', function () {
    [, $plainA] = installationToken();
    [, $plainB] = installationToken();

    checkinAls($plainA, 'dc01', ['windows-server', 'windows-ad']);
    checkinAls($plainB, 'dc01', ['windows-server', 'windows-ad'])
        ->assertExactJson(['roles' => ['windows-server', 'windows-ad']]);

    expect(AgentInstallation::count())->toBe(2);
});

test('a role switched off on the page stays off', function () {
    [, $plain] = installationToken();

    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad']);
    AgentInstallation::sole()->update(['roles' => ['windows-server']]);

    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad'])
        ->assertExactJson(['roles' => ['windows-server']]);
});

test('a role detected later is switched on', function () {
    [, $plain] = installationToken();

    checkinAls($plain, 'srv01', ['windows-server']);
    checkinAls($plain, 'srv01', ['windows-server', 'hyperv'])
        ->assertExactJson(['roles' => ['windows-server', 'hyperv']]);
});

test('check-in needs a valid token and a known kind', function () {
    $this->postJson('/api/agent/checkin', ['kind' => 'windows'])->assertUnauthorized();

    [, $plain] = installationToken();
    $this->withToken($plain)->postJson('/api/agent/checkin', [
        'kind' => 'gibt-es-nicht', 'machine_id' => 'x', 'hostname' => 'x',
    ])->assertUnprocessable();
});

test('the agent page lists installed agents and saves the ticked roles', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$token, $plain] = installationToken();
    $customer = $token->customer;

    checkinAls($plain, 'dc02', ['windows-server', 'windows-ad']);
    $installation = AgentInstallation::sole();

    $this->get(route('agent.index', $customer))
        ->assertOk()
        ->assertSee('DC02')
        ->assertSee('firma.local');

    $this->put(route('agent.installation.update', [$customer, $installation]), ['roles' => ['windows-ad']])
        ->assertRedirect(route('agent.index', $customer));
    expect($installation->fresh()->roles)->toBe(['windows-ad']);

    // Roles of another kind are refused.
    $this->put(route('agent.installation.update', [$customer, $installation]), ['roles' => ['proxmox']])
        ->assertSessionHasErrors('roles.0');

    // Nothing ticked: the agent runs nothing.
    $this->put(route('agent.installation.update', [$customer, $installation]), []);
    expect($installation->fresh()->roles)->toBe([]);

    $this->delete(route('agent.installation.destroy', [$customer, $installation]))
        ->assertRedirect(route('agent.index', $customer));
    expect(AgentInstallation::count())->toBe(0);
});

test('agents of another customer cannot be changed', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [, $plain] = installationToken();
    checkinAls($plain, 'dc01', ['windows-server']);

    $fremd = Customer::factory()->create();
    $this->put(route('agent.installation.update', [$fremd, AgentInstallation::sole()]), ['roles' => []])
        ->assertNotFound();
});

function reportAls(string $plain, string $machine, array $results)
{
    return test()->withToken($plain)->postJson('/api/agent/report', ['machine_id' => $machine, 'results' => $results]);
}

test('a run report is stored per role, cut to the end of the output', function () {
    [, $plain] = installationToken();
    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad']);

    reportAls($plain, 'dc01', [
        ['role' => 'windows-server', 'ok' => true, 'message' => 'Server gemeldet'],
        ['role' => 'windows-ad', 'ok' => false, 'message' => str_repeat('x', 3000).'Zugriff verweigert'],
    ])->assertOk();

    $installation = AgentInstallation::sole();
    expect($installation->last_run_at)->not->toBeNull()
        ->and($installation->resultFor('windows-server')['ok'])->toBeTrue()
        ->and($installation->failedRoles())->toBe(['windows-ad'])
        ->and($installation->resultFor('windows-ad')['message'])->toEndWith('Zugriff verweigert')
        ->and(mb_strlen($installation->resultFor('windows-ad')['message']))->toBe(AgentInstallation::MESSAGE_LENGTH);
});

test('results of roles no longer assigned are dropped', function () {
    [, $plain] = installationToken();
    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad']);
    reportAls($plain, 'dc01', [['role' => 'windows-ad', 'ok' => false, 'message' => 'kaputt']]);

    AgentInstallation::sole()->update(['roles' => ['windows-server']]);
    reportAls($plain, 'dc01', [['role' => 'windows-server', 'ok' => true]]);

    expect(AgentInstallation::sole()->failedRoles())->toBe([]);
});

test('a report needs a machine that checked in with this customer', function () {
    [, $plain] = installationToken();
    reportAls($plain, 'unbekannt', [['role' => 'windows-server', 'ok' => true]])->assertNotFound();

    [, $fremd] = installationToken();
    checkinAls($fremd, 'dc01', ['windows-server']);
    reportAls($plain, 'dc01', [['role' => 'windows-server', 'ok' => true]])->assertNotFound();
});

test('the agent page shows a failed run with its message', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$token, $plain] = installationToken();
    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad']);
    reportAls($plain, 'dc01', [['role' => 'windows-ad', 'ok' => false, 'message' => 'Get-ADUser: Zugriff verweigert']]);

    $this->get(route('agent.index', $token->customer))
        ->assertOk()
        ->assertSee('Active Directory fehlgeschlagen')
        ->assertSee('Get-ADUser: Zugriff verweigert');
});

test('the dashboard warns about silent agents, failed runs and expiring tokens', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    [$token, $plain] = installationToken();
    $customer = $token->customer;
    $token->update(['expires_at' => now()->addDays(10)->endOfDay(), 'name' => 'Token Zentrale']);

    checkinAls($plain, 'dc01', ['windows-server', 'windows-ad']);
    reportAls($plain, 'dc01', [['role' => 'windows-ad', 'ok' => false, 'message' => 'kaputt']]);
    checkinAls($plain, 'srv-alt', ['windows-server']);
    AgentInstallation::where('machine_id', 'srv-alt')->update(['last_seen_at' => now()->subDays(2)]);

    $this->get(route('customer.dashboard', $customer))
        ->assertOk()
        ->assertViewHas('agentWarnings', fn ($w) => $w->pluck('name')->sort()->values()->all() === ['DC01', 'SRV-ALT', 'Token Zentrale'])
        ->assertSee('läuft ab in 10 Tagen');
});

test('without agents or tokens there is no agents tile', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['see_hidden']));

    $this->get(route('customer.dashboard', $customer))->assertOk()->assertViewHas('agentWarnings', null);
});

test('without the right to manage agents there is no agents tile', function () {
    [$token] = installationToken();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('customer.dashboard', $token->customer))->assertOk()->assertViewHas('agentWarnings', null);
});
