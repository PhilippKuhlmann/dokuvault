<?php

use App\Livewire\ObjectModal;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Support\CustomerAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
 * The level of a team on a customer: "read" takes writing away - for
 * admins too, through every path; the role stays the upper bound.
 */

/** "Eigene Firma" with team "Chef" (full) and team "Technik" (read). */
function levelWorld(): array
{
    $own = Customer::factory()->create(['name' => 'Eigene Firma']);
    $site = Site::factory()->create(['customer_id' => $own->id]);
    $server = Server::forceCreate(['customer_id' => $own->id, 'site_id' => $site->id, 'name' => 'SRV-GF', 'form_factor' => 'tower']);

    $boss = Team::create(['name' => 'Chef']);
    $tech = Team::create(['name' => 'Technik']);
    $own->teams()->attach($boss->id, ['level' => CustomerAccess::FULL]);
    $own->teams()->attach($tech->id, ['level' => CustomerAccess::READ]);

    return [$own, $server, $boss, $tech];
}

function technician(Team $team, array $rights = ['server_viewAny', 'server_create', 'server_update', 'server_delete']): User
{
    $user = userWithPermissions($rights);
    $team->members()->attach($user->id);

    return $user;
}

test('read: the page shows, says so, and offers no pencil', function () {
    [$own, , , $tech] = levelWorld();
    $this->actingAs(technician($tech));

    $this->get(route('server.index', $own))->assertOk()
        ->assertSee('SRV-GF')
        ->assertSee('Nur lesen')
        ->assertDontSee("typ: 'server', id:", false);
    $this->get(route('customer.dashboard', $own))->assertOk()->assertSee('Nur lesen');
});

test('read: the form refuses, and so does the model behind it', function () {
    [$own, $server, , $tech] = levelWorld();
    $this->actingAs(technician($tech));

    Livewire::test(ObjectModal::class, ['typ' => 'server', 'customer' => $own])
        ->call('bearbeiten', 'server', $server->id)
        ->assertForbidden();

    // Past every button: the safety net in OwnedByCustomer.
    expect(fn () => $server->update(['name' => 'SRV-NEU']))->toThrow(AuthorizationException::class)
        ->and(fn () => $server->delete())->toThrow(AuthorizationException::class)
        ->and(fn () => Server::forceCreate(['customer_id' => $own->id, 'site_id' => $server->site_id, 'name' => 'X', 'form_factor' => 'tower']))
        ->toThrow(AuthorizationException::class);
    expect($server->fresh()->name)->toBe('SRV-GF');
});

test('an admin in a reading team only reads as well', function () {
    [$own, $server, , $tech] = levelWorld();
    $admin = adminUser();
    $tech->members()->attach($admin->id);
    $this->actingAs($admin);

    $this->get(route('server.index', $own))->assertOk()->assertSee('Nur lesen');
    expect(fn () => $server->update(['name' => 'SRV-NEU']))->toThrow(AuthorizationException::class);
});

test('in two teams the higher level counts', function () {
    [$own, $server, $boss, $tech] = levelWorld();
    $user = technician($tech);
    $boss->members()->attach($user->id);
    $this->actingAs($user);

    expect(CustomerAccess::levelFor($user, $own->id))->toBe(CustomerAccess::FULL);
    $server->update(['name' => 'SRV-NEU']);
    expect($server->fresh()->name)->toBe('SRV-NEU');
    $this->get(route('server.index', $own))->assertDontSee('Nur lesen');
});

test('elsewhere the same technician writes as the role allows', function () {
    [, , , $tech] = levelWorld();
    $other = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $other->id]);
    $this->actingAs(technician($tech));

    $server = Server::forceCreate(['customer_id' => $other->id, 'site_id' => $site->id, 'name' => 'SRV-K', 'form_factor' => 'tower']);
    $server->update(['name' => 'SRV-K2']);

    expect($server->fresh()->name)->toBe('SRV-K2');
});

test('who only reads cannot lift their own team on the customer page', function () {
    [$own, , , $tech] = levelWorld();
    $user = technician($tech, ['admin_customer', 'admin_team']);
    $this->actingAs($user);

    $this->patch(route('admin.customer.update', $own), [
        'name' => 'Eigene Firma', 'teams' => [$tech->id], 'levels' => [$tech->id => CustomerAccess::FULL],
    ])->assertForbidden();

    expect(DB::table('customer_team')->where('team_id', $tech->id)->value('level'))->toBe(CustomerAccess::READ);
});

test('with full access one sets the levels - never so that one keeps less than full', function () {
    [$own, , $boss, $tech] = levelWorld();
    $user = technician($boss, ['admin_customer', 'admin_team']);
    $this->actingAs($user);

    $this->patch(route('admin.customer.update', $own), [
        'name' => 'Eigene Firma', 'teams' => [$boss->id], 'levels' => [$boss->id => CustomerAccess::READ],
    ])->assertSessionHasErrors('teams');

    $this->patch(route('admin.customer.update', $own), [
        'name' => 'Eigene Firma', 'teams' => [$boss->id, $tech->id],
        'levels' => [$boss->id => CustomerAccess::FULL, $tech->id => CustomerAccess::READ_NO_SECRETS],
    ])->assertSessionHasNoErrors();

    expect(DB::table('customer_team')->where('team_id', $tech->id)->value('level'))->toBe(CustomerAccess::READ_NO_SECRETS);
    $this->get(route('admin.customer.edit', $own->id))->assertOk()->assertSee('Lesen ohne Kennwörter');
});

test('agents and the console still write - they are no user', function () {
    [, $server] = levelWorld();

    $server->update(['name' => 'SRV-AGENT']);

    expect($server->fresh()->name)->toBe('SRV-AGENT');
});

test('who only reads gets no pencil and no form for the customer itself', function () {
    [$own, , , $tech] = levelWorld();
    $this->actingAs(technician($tech, ['admin_customer', 'admin_team']));

    $this->get(route('admin.customer.index'))->assertOk()
        ->assertSee('Eigene Firma')
        ->assertDontSee('/admin/customer/'.$own->id.'/edit', false);
    $this->get(route('admin.customer.edit', $own->id))->assertForbidden();
});
