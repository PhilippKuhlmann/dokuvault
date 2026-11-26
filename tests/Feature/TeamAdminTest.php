<?php

use App\Livewire\AdminActivityLog;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Rack;
use App\Models\RackItem;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Support\CustomerAccess;
use App\Support\ExpiringItems;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * Admin -> Teams and the section "Zugriff" on a customer: who may change
 * what, and the places that list across customers without a global scope.
 */

test('whoever creates a team is in it, and the members are logged', function () {
    $boss = userWithPermissions(['admin_team']);
    $tech = userWithPermissions([]);
    $this->actingAs($boss);

    $this->post(route('admin.team.store'), ['name' => 'Chef', 'members' => [$tech->id]])
        ->assertRedirect(route('admin.team.index'));

    $team = Team::firstWhere('name', 'Chef');
    expect($team->members()->pluck('users.id')->sort()->values()->all())->toBe(collect([$boss->id, $tech->id])->sort()->values()->all())
        ->and(Activity::where('event', 'members_changed')->where('subject_id', $team->id)->exists())->toBeTrue();
});

test('only a member may change or delete a team', function () {
    $team = Team::create(['name' => 'Chef']);
    $team->members()->attach(userWithPermissions([])->id);
    $outsider = userWithPermissions(['admin_team']);
    $this->actingAs($outsider);

    $this->get(route('admin.team.index'))->assertOk()->assertSee('Chef')->assertDontSee(route('admin.team.edit', $team));
    $this->get(route('admin.team.edit', $team))->assertForbidden();
    $this->patch(route('admin.team.update', $team), ['name' => 'Chef', 'members' => [$outsider->id]])->assertForbidden();
    $this->delete(route('admin.team.destroy', $team))->assertForbidden();

    expect($team->hasMember($outsider))->toBeFalse();
});

test('a team keeps at least one member, and is not deleted while customers hang on it', function () {
    $boss = userWithPermissions(['admin_team']);
    $team = Team::create(['name' => 'Chef']);
    $team->members()->attach($boss->id);
    $customer = Customer::factory()->create();
    $team->customers()->attach($customer->id);
    $this->actingAs($boss);

    $this->patch(route('admin.team.update', $team), ['name' => 'Chef', 'members' => []])->assertSessionHasErrors('members');
    $this->delete(route('admin.team.destroy', $team))->assertSessionHasErrors('team');
    expect(Team::whereKey($team->id)->exists())->toBeTrue();

    $team->customers()->detach();
    $this->delete(route('admin.team.destroy', $team))->assertRedirect(route('admin.team.index'));
    expect(Team::whereKey($team->id)->exists())->toBeFalse();
});

test('a customer is given teams on its page - never so that the editor is locked out', function () {
    $tech = userWithPermissions(['admin_customer', 'admin_team']);
    $mine = Team::create(['name' => 'Nord']);
    $mine->members()->attach($tech->id);
    $other = Team::create(['name' => 'Süd']);
    $customer = Customer::factory()->create(['name' => 'Kunde A']);
    $this->actingAs($tech);

    $this->patch(route('admin.customer.update', $customer), ['name' => 'Kunde A', 'teams' => [$other->id]])
        ->assertSessionHasErrors('teams');
    expect($customer->teams()->count())->toBe(0);

    $this->patch(route('admin.customer.update', $customer), ['name' => 'Kunde A', 'teams' => [$mine->id, $other->id]])
        ->assertSessionHasNoErrors();
    expect($customer->teams()->pluck('name')->all())->toBe(['Nord', 'Süd'])
        ->and(DB::table('customer_team')->where('customer_id', $customer->id)->pluck('level')->unique()->all())->toBe([CustomerAccess::FULL])
        ->and(Activity::where('event', 'access_changed')->where('subject_id', $customer->id)->value('properties')['attributes']['Teams'])->toBe('Nord (Vollzugriff), Süd (Vollzugriff)');

    $this->get(route('admin.customer.index'))->assertSee('nur Nord, Süd');
});

test('a new customer starts with the creator\'s teams ticked', function () {
    $tech = userWithPermissions(['admin_customer', 'admin_team']);
    $team = Team::create(['name' => 'Nord']);
    $team->members()->attach($tech->id);
    $this->actingAs($tech);

    $this->get(route('admin.customer.create'))->assertOk()
        ->assertSee('name="access['.$team->id.']" value="full" class="peer sr-only"'."\n".'                                    checked', false);

    $this->post(route('admin.customer.store'), ['name' => 'Neukunde', 'teams' => [$team->id]]);
    expect(Customer::firstWhere('name', 'Neukunde')->teams()->pluck('name')->all())->toBe(['Nord']);
});

/** A customer of team "Chef", a login of it, and an outsider with every admin right. */
function hiddenWorld(): array
{
    $hidden = Customer::factory()->create(['name' => 'Eigene Firma']);
    $team = Team::create(['name' => 'Chef']);
    $team->customers()->attach($hidden->id);
    $login = User::factory()->create(['name' => 'Login Eigene', 'customer_id' => $hidden->id, 'role_id' => userWithPermissions([])->role_id]);
    $outsider = userWithPermissions(['admin_user', 'admin_activity', 'admin_setting', 'admin_customer']);

    return [$hidden, $login, $outsider];
}

test('the user admin does not list or open logins of a hidden customer', function () {
    [$hidden, $login, $outsider] = hiddenWorld();
    $this->actingAs($outsider);

    $this->get(route('admin.user.index'))->assertOk()->assertDontSee('Login Eigene');
    $this->get(route('admin.user.edit', $login))->assertNotFound();
    $this->get(route('admin.customer.index'))->assertDontSee('Eigene Firma');
});

test('the activity log leaves out entries of a hidden customer', function () {
    [$hidden, , $outsider] = hiddenWorld();
    activity()->performedOn($hidden)->withProperties(['objekt' => 'Geheimes Objekt'])->event('updated')->log('x');
    $this->actingAs($outsider);

    Livewire::test(AdminActivityLog::class)->assertDontSee('Geheimes Objekt');
    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Geheimes Objekt');
});

test('a rack item entry belongs to the customer of its rack and stays hidden', function () {
    [$hidden, , $outsider] = hiddenWorld();
    $site = Site::factory()->create(['customer_id' => $hidden->id]);
    $rack = Rack::withoutGlobalScopes()->create(['customer_id' => $hidden->id, 'site_id' => $site->id, 'name' => 'R1', 'height_units' => 42]);
    $item = RackItem::create(['rack_id' => $rack->id, 'position' => 3, 'height_units' => 1, 'name' => 'Geheimes Patchfeld']);

    $entry = Activity::where('subject_type', RackItem::class)->where('subject_id', $item->id)->latest('id')->first();
    expect($entry?->customer_id)->toBe($hidden->id);

    $this->actingAs($outsider);
    Livewire::test(AdminActivityLog::class)->assertDontSee('Geheimes Patchfeld');
});

test('the expiry mail of a staff user leaves out hidden customers', function () {
    [$hidden] = hiddenWorld();
    Certificate::forceCreate(['customer_id' => $hidden->id, 'name' => 'geheim.example', 'expiry_date' => now()->addDays(3)]);

    // From the console, without a signed-in user: no global scope helps.
    $outsider = userWithPermissions(['certificate_viewAny']);
    $member = userWithPermissions(['certificate_viewAny']);
    Team::firstWhere('name', 'Chef')->members()->attach($member->id);

    expect(ExpiringItems::forUser($outsider, ['certificate'])->pluck('name')->all())->not->toContain('geheim.example')
        ->and(ExpiringItems::forUser($member, ['certificate'])->pluck('name')->all())->toContain('geheim.example');
});

test('the console brings a member back into a team nobody may change', function () {
    $team = Team::create(['name' => 'Chef']);
    $user = userWithPermissions([]);

    $this->artisan('team:add-member', ['team' => 'Chef', 'user' => $user->username])->assertSuccessful();

    expect($team->hasMember($user))->toBeTrue();
});

test('with "Kunden verwalten" alone the access is shown, not changed', function () {
    $tech = userWithPermissions(['admin_customer']);
    $team = Team::create(['name' => 'Chef']);
    $team->members()->attach($tech->id);
    $customer = Customer::factory()->create(['name' => 'Kunde A']);
    $this->actingAs($tech);

    $this->get(route('admin.customer.edit', $customer->id))->assertOk()
        ->assertSee('Den Zugriff ändern darf nur, wer Teams verwalten darf.')
        ->assertDontSee('name="access[', false);

    // The name changes, the access stays - and sent anyway, it is refused.
    $this->patch(route('admin.customer.update', $customer), ['name' => 'Kunde B'])->assertRedirect();
    $this->patch(route('admin.customer.update', $customer), ['name' => 'Kunde B', 'teams' => [$team->id]])->assertForbidden();

    expect($customer->fresh()->name)->toBe('Kunde B')->and($customer->teams()->count())->toBe(0);
});

test('the form sends one switch per team: not assigned, or a level', function () {
    $boss = userWithPermissions(['admin_customer', 'admin_team']);
    $chef = Team::create(['name' => 'Chef']);
    $chef->members()->attach($boss->id);
    $tech = Team::create(['name' => 'Technik']);
    $south = Team::create(['name' => 'Süd']);
    $customer = Customer::factory()->create(['name' => 'Eigene Firma']);
    $this->actingAs($boss);

    $this->patch(route('admin.customer.update', $customer), ['name' => 'Eigene Firma', 'access' => [
        $chef->id => 'full', $tech->id => 'read_no_secrets', $south->id => 'none',
    ]])->assertSessionHasNoErrors();

    expect(DB::table('customer_team')->where('customer_id', $customer->id)->pluck('level', 'team_id')->all())
        ->toBe([$chef->id => 'full', $tech->id => 'read_no_secrets']);

    // All "not assigned": open to everyone again.
    $this->patch(route('admin.customer.update', $customer), ['name' => 'Eigene Firma', 'access' => [
        $chef->id => 'none', $tech->id => 'none', $south->id => 'none',
    ]])->assertSessionHasNoErrors();
    expect($customer->teams()->count())->toBe(0);
});
