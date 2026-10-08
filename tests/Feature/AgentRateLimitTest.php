<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

// All agents of a site report from one NAT address with one token. Under
// the API limit per IP (60 a minute) a site stopped at about 260 agents.

function agentLimitToken(): string
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return AgentToken::generateFor($customer, $site, 'Limit', now()->addDay())[1];
}

test('agents of one site are not stopped by the API limit per IP', function () {
    $plain = agentLimitToken();

    foreach (range(1, 70) as $i) {
        $antwort = $this->withToken($plain)->postJson('/api/agent/checkin', [
            'kind' => 'windows', 'machine_id' => 'm-'.$i, 'hostname' => 'PC-'.$i,
        ]);
        expect($antwort->status())->toBe(200);
    }

    expect((int) $antwort->headers->get('X-RateLimit-Limit'))->toBeGreaterThan(60);
});

test('the agent limit counts per token', function () {
    $plain = agentLimitToken();
    $schluessel = 'agent-token:'.hash('sha256', $plain);

    // Pretend this token has used up its minute.
    foreach (range(1, 1200) as $i) {
        RateLimiter::hit(md5('agent'.$schluessel));
    }

    $this->withToken($plain)->postJson('/api/agent/checkin', [
        'kind' => 'windows', 'machine_id' => 'm-1', 'hostname' => 'PC-1',
    ])->assertStatus(429);

    // Another token from the same address still gets through.
    $this->withToken(agentLimitToken())->postJson('/api/agent/checkin', [
        'kind' => 'windows', 'machine_id' => 'm-2', 'hostname' => 'PC-2',
    ])->assertOk();
});

test('the rest of the API keeps its limit per user', function () {
    Sanctum::actingAs(User::factory()->create());
    $antwort = $this->getJson('/api/user');

    expect((int) $antwort->headers->get('X-RateLimit-Limit'))->toBe(60);
});
