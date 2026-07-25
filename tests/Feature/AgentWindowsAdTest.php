<?php

use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;

function windowsAdPayload(): array
{
    return [
        'domain' => 'mustermann.local',
        'users' => [
            ['identifier' => 'guid-admin', 'firstName' => null, 'lastName' => null, 'username' => 'Administrator', 'email' => null, 'enabled' => true],
            ['identifier' => 'guid-maxm', 'firstName' => 'Max', 'lastName' => 'Mustermann', 'username' => 'mmustermann', 'email' => 'mmustermann@mustermann.de', 'enabled' => true],
            ['identifier' => 'guid-inactive', 'firstName' => 'Alt', 'lastName' => 'Ausgeschieden', 'username' => 'aausgeschieden', 'email' => null, 'enabled' => false],
        ],
        'groups' => [
            ['identifier' => 'guid-grp-vertrieb', 'name' => 'Vertrieb', 'description' => 'Abteilung Vertrieb'],
        ],
    ];
}

test('Windows-AD-Agent legt Benutzer und Gruppen beim Kunden des Tokens an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'DC01');

    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'users_documented' => 3, 'groups_documented' => 1]);

    expect(ADUser::where('customer_id', $customer->id)->count())->toBe(3);
    expect(ADGroup::where('customer_id', $customer->id)->count())->toBe(1);

    $max = ADUser::where('agent_identifier', 'guid-maxm')->first();
    expect($max->firstName)->toBe('Max');
    expect($max->lastName)->toBe('Mustermann');
    expect($max->username)->toBe('mmustermann');
    expect($max->email)->toBe('mmustermann@mustermann.de');
    expect($max->enabled)->toBeTrue();
    // Passwort wird vom Agent nie gesetzt
    expect($max->password)->toBeNull();

    $inactive = ADUser::where('agent_identifier', 'guid-inactive')->first();
    expect($inactive->enabled)->toBeFalse();

    $group = ADGroup::where('agent_identifier', 'guid-grp-vertrieb')->first();
    expect($group->name)->toBe('Vertrieb');
    expect($group->description)->toBe('Abteilung Vertrieb');
});

test('Agent überschreibt ein manuell gesetztes Passwort nicht', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload())->assertOk();
    $max = ADUser::where('agent_identifier', 'guid-maxm')->first();
    $max->update(['password' => 'geheim123']);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload())->assertOk();

    expect($max->fresh()->password)->toBe('geheim123');
});

test('erneuter Lauf erzeugt keine Duplikate (Upsert)', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload())->assertOk();
    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload())->assertOk();

    expect(ADUser::where('agent_identifier', 'guid-maxm')->count())->toBe(1);
    expect(ADGroup::where('agent_identifier', 'guid-grp-vertrieb')->count())->toBe(1);
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/windows-ad', [])->assertUnauthorized();

    $this->withToken('doc_falsch')
        ->postJson('/api/agent/windows-ad', windowsAdPayload())
        ->assertUnauthorized();
});
