<?php

use App\Livewire\ObjectList;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Support\CustomerAccess;
use Livewire\Livewire;

/*
 * Teams decide which customers a staff user sees. A customer without a
 * team is open to everyone, as before; one with a team only to its members
 * - for everyone else it does not exist, admins included.
 */

/** Two customers: "Eigene Firma" in the team "Chef", "Kunde Offen" without team. */
function teamSetup(): array
{
    $own = Customer::factory()->create(['name' => 'Eigene Firma']);
    $open = Customer::factory()->create(['name' => 'Kunde Offen']);

    $team = Team::create(['name' => 'Chef']);
    $team->customers()->attach($own->id, ['level' => CustomerAccess::FULL]);

    foreach ([$own, $open] as $customer) {
        $site = Site::factory()->create(['customer_id' => $customer->id]);
        Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-'.$customer->slug, 'form_factor' => 'tower']);
    }

    return [$own, $open, $team];
}

test('a member sees the team customer and the open ones', function () {
    [$own, $open, $team] = teamSetup();
    $boss = userWithPermissions(['server_viewAny']);
    $team->members()->attach($boss->id);
    $this->actingAs($boss);

    expect(Customer::pluck('name')->sort()->values()->all())->toBe(['Eigene Firma', 'Kunde Offen'])
        ->and(Server::count())->toBe(2);

    $this->get(route('customer.dashboard', $own))->assertOk();
    $this->get(route('server.index', $own))->assertOk()->assertSee('SRV-eigene-firma');
});

test('a non-member: the customer does not exist - list, search, page, data', function () {
    [$own, $open] = teamSetup();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    expect(Customer::pluck('name')->all())->toBe(['Kunde Offen'])
        ->and(Server::pluck('name')->all())->toBe(['SRV-kunde-offen']);

    $this->get(route('customer.search', ['search' => 'Firma']))->assertOk()->assertDontSee('Eigene Firma');
    $this->get(route('customer.dashboard', $own))->assertNotFound();
    $this->get(route('server.index', $own))->assertNotFound();
    $this->get(route('customer.dashboard', $open))->assertOk();
});

test('an admin outside the team is kept out as well', function () {
    [$own] = teamSetup();
    $this->actingAs(adminUser());

    expect(Customer::whereKey($own->id)->exists())->toBeFalse();
    $this->get(route('customer.dashboard', $own))->assertNotFound();
    $this->get(route('admin.customer.edit', $own->id))->assertNotFound();
});

test('Livewire refuses a hidden customer handed in from outside', function () {
    [$own] = teamSetup();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    // As /livewire/update does it, past the route: onlyOwnCustomer
    // (ScopedToCustomer) stops it.
    Livewire::test(ObjectList::class, ['typ' => 'server', 'customer' => $own])->assertNotFound();
});

test('a customer login is not touched by teams', function () {
    [$own] = teamSetup();
    $role = userWithPermissions(['server_viewAny'])->role;
    $login = User::factory()->create(['role_id' => $role->id, 'customer_id' => $own->id]);
    $this->actingAs($login);

    expect(Customer::pluck('name')->all())->toContain('Eigene Firma')
        ->and(Server::pluck('name')->all())->toBe(['SRV-eigene-firma']);
});

test('without a user (agents, queue, console) nothing is hidden', function () {
    teamSetup();

    expect(Customer::count())->toBe(2)->and(Server::count())->toBe(2);
});

test('several teams: a member of any of them sees the customer', function () {
    [$own, , $team] = teamSetup();
    $south = Team::create(['name' => 'Süd']);
    $own->teams()->attach($south->id, ['level' => CustomerAccess::FULL]);
    $tech = userWithPermissions(['server_viewAny']);
    $south->members()->attach($tech->id);

    expect(CustomerAccess::canSee($tech, $own->id))->toBeTrue()
        ->and(CustomerAccess::canSee(userWithPermissions([]), $own->id))->toBeFalse();
});

test('a new customer gets a free slug even when a hidden one holds the name', function () {
    teamSetup();
    $this->actingAs(userWithPermissions([]));

    $twin = Customer::create(['name' => 'Eigene Firma']);

    expect($twin->slug)->toBe('eigene-firma-1');
});

test('whoever is outside a team cannot take over the account of a member', function () {
    [$own, , $team] = teamSetup();
    $member = userWithPermissions(['server_viewAny']);
    $team->members()->attach($member->id);

    // Role 1 and a delegate with admin_user: both outside the team.
    foreach ([adminUser(), userWithPermissions(['admin_user', 'server_viewAny'])] as $outsider) {
        nutzerWechseln($outsider);
        expect($outsider->mayManage($member))->toBeFalse();
        $this->get(route('admin.user.edit', $member))->assertForbidden();
        $this->patch(route('admin.user.update', $member), ['name' => 'X', 'username' => $member->username, 'email' => $member->email, 'role_id' => $member->role_id])->assertForbidden();
        $this->delete(route('admin.user.destroy', $member))->assertForbidden();
    }
    expect(User::find($member->id))->not->toBeNull();

    // A member of the same team may.
    $peer = adminUser();
    $team->members()->attach($peer->id);
    expect($peer->fresh()->mayManage($member))->toBeTrue();
});

test('a member with a lower level cannot take over one with a higher level', function () {
    [$own] = teamSetup();
    $full = Team::create(['name' => 'Voll']);
    $full->customers()->attach($own->id, ['level' => CustomerAccess::FULL]);
    $read = Team::create(['name' => 'Lesen']);
    $read->customers()->attach($own->id, ['level' => CustomerAccess::READ_NO_SECRETS]);

    $high = userWithPermissions(['server_viewAny']);
    $full->members()->attach($high->id);
    $low = adminUser();
    $read->members()->attach($low->id);

    // Even role 1: with the account it would see the passwords its own team hides.
    expect($low->mayManage($high))->toBeFalse();
});
