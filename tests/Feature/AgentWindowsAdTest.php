<?php

use App\Models\ADDomain;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
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

function adAgentKunde(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'DC01');

    return [$customer, $site, $plain];
}

function adAgentServer(Customer $customer, Site $site, string $name): Server
{
    return Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => $name,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id,
    ]);
}

test('the AD agent fills the domain and links its machines', function () {
    [$customer, $site, $plain] = adAgentKunde();
    adAgentServer($customer, $site, 'DC01');
    adAgentServer($customer, $site, 'SYNC01');

    $payload = windowsAdPayload() + ['ad' => [
        'netbios' => 'MUSTERMANN',
        'domain_mode' => 'Windows2016Domain',
        'upn_suffixes' => ['mustermann.de'],
        'fsmo' => array_fill_keys(['pdc', 'rid', 'infrastructure', 'schema', 'naming'], 'dc01.mustermann.local'),
        'domain_controllers' => [
            ['name' => 'dc01.mustermann.local', 'ip' => '10.0.0.10'],
            ['name' => 'dc02.mustermann.local', 'ip' => '10.0.0.11'],
        ],
        'dns_forwarders' => ['1.1.1.1', '9.9.9.9'],
        'dhcp_servers' => ['dc01.mustermann.local'],
        'ca_hosts' => ['dc01.mustermann.local'],
        'entra_connect_host' => 'SYNC01',
    ]];

    $this->withToken($plain)->postJson('/api/agent/windows-ad', $payload)
        ->assertOk()
        // DC02 is not documented yet - reported back instead of silently dropped.
        ->assertJson(['unmatched_hosts' => ['dc02.mustermann.local']]);

    $domaene = ADDomain::where('customer_id', $customer->id)->sole();
    expect($domaene->domain)->toBe('mustermann.local')
        ->and($domaene->netbios)->toBe('MUSTERMANN')
        ->and($domaene->functional_level)->toBe('2016')
        ->and($domaene->upn_suffixes)->toBe('mustermann.de')
        ->and($domaene->fsmo_holder)->toBe('DC01 (alle 5 Rollen)')
        ->and($domaene->dns_forwarders)->toBe('1.1.1.1, 9.9.9.9')
        ->and($domaene->dhcp_server)->toBe('dc01.mustermann.local')
        ->and((bool) $domaene->entra_connect)->toBeTrue()
        ->and($domaene->hostNames('dc'))->toBe('DC01')
        ->and($domaene->hostNames('ca'))->toBe('DC01')
        ->and($domaene->hostNames('entra_connect'))->toBe('SYNC01');
});

test('a second run updates the same domain and keeps what was entered by hand', function () {
    [$customer, $site, $plain] = adAgentKunde();
    $domaene = ADDomain::factory()->create([
        'customer_id' => $customer->id, 'domain' => 'Mustermann.local',
        'dsrmpassword' => 'Geheim!', 'notes' => 'Von Hand', 'dhcp_server' => 'Firewall',
    ]);

    // Functional level only, DHCP query failed (empty list).
    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload() + ['ad' => [
        'domain_mode' => 'Windows2012R2Domain', 'dhcp_servers' => [],
    ]])->assertOk();

    expect(ADDomain::where('customer_id', $customer->id)->count())->toBe(1);
    $domaene->refresh();
    expect($domaene->functional_level)->toBe('2012R2')
        ->and($domaene->dsrmpassword)->toBe('Geheim!')
        ->and($domaene->notes)->toBe('Von Hand')
        ->and($domaene->dhcp_server)->toBe('Firewall');
});

test('an older script that sends only the domain name still creates the domain', function () {
    [$customer, , $plain] = adAgentKunde();

    $this->withToken($plain)->postJson('/api/agent/windows-ad', windowsAdPayload())->assertOk();

    $domaene = ADDomain::where('customer_id', $customer->id)->sole();
    expect($domaene->netbios)->toBe('MUSTERMANN')
        ->and($domaene->functional_level)->toBeNull();
});
