<?php

use App\Livewire\ObjektFormular;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use Livewire\Livewire;

/*
 * Who is in which AD group: reported by the AD agent, editable by hand from
 * the user and from the group, shown in both lists.
 */

function adMitgliederPayload(array $members): array
{
    return [
        'domain' => 'mustermann.local',
        'users' => [
            ['identifier' => 'guid-max', 'username' => 'mmustermann'],
            ['identifier' => 'guid-eva', 'username' => 'emusterfrau'],
        ],
        'groups' => [
            ['identifier' => 'guid-vertrieb', 'name' => 'Vertrieb', 'members' => $members],
        ],
    ];
}

function adMitgliederToken(Customer $customer): string
{
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return AgentToken::generateFor($customer, $site)[1];
}

test('the agent records the direct members of a group', function () {
    $customer = Customer::factory()->create();
    $plain = adMitgliederToken($customer);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', adMitgliederPayload(['guid-max', 'guid-eva', 'guid-unbekannt']))->assertOk();

    $gruppe = ADGroup::where('agent_identifier', 'guid-vertrieb')->sole();
    expect($gruppe->users->pluck('username')->all())->toBe(['emusterfrau', 'mmustermann']);
    expect(ADUser::where('agent_identifier', 'guid-max')->sole()->groups->pluck('name')->all())->toBe(['Vertrieb']);
});

test('a member removed in AD is removed, a link to a hand-documented user stays', function () {
    $customer = Customer::factory()->create();
    $plain = adMitgliederToken($customer);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', adMitgliederPayload(['guid-max', 'guid-eva']))->assertOk();
    $gruppe = ADGroup::where('agent_identifier', 'guid-vertrieb')->sole();
    $vonHand = ADUser::create(['customer_id' => $customer->id, 'username' => 'extern']);
    $gruppe->users()->attach($vonHand);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', adMitgliederPayload(['guid-max']))->assertOk();

    expect($gruppe->fresh()->users->pluck('username')->all())->toBe(['extern', 'mmustermann']);
});

test('an older script without members leaves the memberships alone', function () {
    $customer = Customer::factory()->create();
    $plain = adMitgliederToken($customer);

    $this->withToken($plain)->postJson('/api/agent/windows-ad', adMitgliederPayload(['guid-max']))->assertOk();

    $ohne = adMitgliederPayload([]);
    unset($ohne['groups'][0]['members']);
    $this->withToken($plain)->postJson('/api/agent/windows-ad', $ohne)->assertOk();

    expect(ADGroup::where('agent_identifier', 'guid-vertrieb')->sole()->users)->toHaveCount(1);
});

test('groups are edited on the user and members on the group', function () {
    $customer = Customer::factory()->create();
    $user = ADUser::create(['customer_id' => $customer->id, 'username' => 'mmustermann']);
    $vertrieb = ADGroup::create(['customer_id' => $customer->id, 'name' => 'Vertrieb']);
    $technik = ADGroup::create(['customer_id' => $customer->id, 'name' => 'Technik', 'description' => 'Abteilung Technik']);
    $this->actingAs(userWithPermissions(['aduser_update', 'adgroup_update']));

    Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $user->id)
        ->assertSet('form.groups', [])
        ->set('form.groups', [(string) $vertrieb->id, (string) $technik->id])
        ->call('speichern')
        ->assertHasNoErrors();

    expect($user->fresh()->groups->pluck('name')->all())->toBe(['Technik', 'Vertrieb']);

    Livewire::test(ObjektFormular::class, ['typ' => 'adgroup', 'customer' => $customer])
        ->call('bearbeiten', 'adgroup', $technik->id)
        ->assertSet('form.users', [(string) $user->id])
        ->set('form.users', [])
        ->call('speichern')
        ->assertHasNoErrors();

    expect($user->fresh()->groups->pluck('name')->all())->toBe(['Vertrieb']);
});

test('a group of another customer cannot be linked', function () {
    $customer = Customer::factory()->create();
    $user = ADUser::create(['customer_id' => $customer->id, 'username' => 'mmustermann']);
    $fremd = ADGroup::create(['customer_id' => Customer::factory()->create()->id, 'name' => 'Fremd']);
    $this->actingAs(userWithPermissions(['aduser_update']));

    Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $user->id)
        ->set('form.groups', [(string) $fremd->id])
        ->call('speichern')
        ->assertHasErrors('form.groups.0');

    expect($user->fresh()->groups)->toHaveCount(0);
});

test('both lists show the memberships', function () {
    $customer = Customer::factory()->create();
    $user = ADUser::create(['customer_id' => $customer->id, 'username' => 'mmustermann']);
    $gruppe = ADGroup::create(['customer_id' => $customer->id, 'name' => 'Vertrieb']);
    $gruppe->users()->attach($user);
    $this->actingAs(userWithPermissions(['aduser_viewAny', 'adgroup_viewAny']));

    $this->get(route('aduser.index', $customer))->assertOk()->assertSee('Gruppen')->assertSee('Vertrieb');
    $this->get(route('adgroup.index', $customer))->assertOk()->assertSee('Mitglieder')->assertSee('mmustermann');
});
