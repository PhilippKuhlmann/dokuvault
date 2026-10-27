<?php

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use Spatie\Activitylog\Models\Activity;

// One token per agent, and a token that can be locked: locking stops exactly
// one agent at once and unlocking lets it run again without a reinstall.

function lockToken(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, ...AgentToken::generateFor($customer, $site, 'DC01-Dienst', now()->addYear())];
}

function lockCheckin(string $plain, string $machine)
{
    app('auth')->forgetGuards();

    return test()->withToken($plain)->postJson('/api/agent/checkin', [
        'kind' => 'windows', 'machine_id' => $machine, 'hostname' => strtoupper($machine),
        'domain' => 'firma.local', 'version' => '26.10.27-1200-abc', 'detected' => ['windows-server'],
    ]);
}

test('the first check-in binds the token to its machine, a second machine is refused', function () {
    [, $token, $plain] = lockToken();

    lockCheckin($plain, 'dc01')->assertOk();
    lockCheckin($plain, 'dc01')->assertOk();

    lockCheckin($plain, 'srv02')
        ->assertStatus(409)
        ->assertJsonFragment(['message' => __('Dieser Token gehört bereits zum Agenten :host. Erzeugen Sie für diesen Agenten einen eigenen Token.', ['host' => 'DC01'])]);

    expect($token->fresh()->machine_id)->toBe('dc01')
        ->and(AgentInstallation::pluck('machine_id')->all())->toBe(['dc01']);
});

test('a locked agent can send nothing until unlocked', function () {
    [$customer, $token, $plain] = lockToken();
    lockCheckin($plain, 'dc01')->assertOk();
    $admin = userWithPermissions(['agent_manage']);

    $this->actingAs($admin)->post(route('agent.lock', [$customer, $token]))->assertRedirect();
    expect($token->fresh()->isLocked())->toBeTrue();

    lockCheckin($plain, 'dc01')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson('/api/agent/windows-server', [])->assertUnauthorized();

    app('auth')->forgetGuards();
    $this->actingAs($admin)->post(route('agent.unlock', [$customer, $token]))->assertRedirect();
    expect($token->fresh()->isLocked())->toBeFalse();

    lockCheckin($plain, 'dc01')->assertOk();
    expect(Activity::whereIn('event', ['agent_locked', 'agent_unlocked'])->count())->toBe(2);
});

test('locking one agent leaves the others running', function () {
    [$customer, $token, $plain] = lockToken();
    [$other, $otherPlain] = AgentToken::generateFor($customer, $token->site, 'SRV02-Dienst', now()->addYear());
    lockCheckin($plain, 'dc01')->assertOk();
    lockCheckin($otherPlain, 'srv02')->assertOk();

    $this->actingAs(userWithPermissions(['agent_manage']));
    $this->post(route('agent.lock', [$customer, $token]));

    lockCheckin($plain, 'dc01')->assertUnauthorized();
    lockCheckin($otherPlain, 'srv02')->assertOk();
});

test('removing an agent from the list frees its token for another machine', function () {
    [$customer, $token, $plain] = lockToken();
    lockCheckin($plain, 'dc01')->assertOk();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->delete(route('agent.installation.destroy', [$customer, AgentInstallation::sole()]));

    lockCheckin($plain, 'dc01-neu')->assertOk();
    expect($token->fresh()->machine_id)->toBe('dc01-neu');
});

test('without agent_manage nobody locks or unlocks', function () {
    [$customer, $token] = lockToken();
    $this->actingAs(userWithPermissions([]));

    $this->post(route('agent.lock', [$customer, $token]))->assertForbidden();
    expect($token->fresh()->isLocked())->toBeFalse();
});

test('the agent page shows lock state, owner and the buttons', function () {
    [$customer, $token, $plain] = lockToken();
    lockCheckin($plain, 'dc01')->assertOk();
    $token->forceFill(['locked_at' => now()])->save();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->get(route('agent.index', $customer))
        ->assertOk()
        ->assertSee(__('gesperrt'))
        ->assertSee('DC01')
        ->assertSee(__('Entsperren'));
});
