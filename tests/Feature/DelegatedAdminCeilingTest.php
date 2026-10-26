<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Security audit finding: a delegate with only admin_user or admin_role could
// make themselves role 1 or tick every right on their own role.

function ceilingAdminRole(): Role
{
    return Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
}

function ceilingPermission(string $name): Permission
{
    return Permission::where('name', $name)->first() ?? Permission::factory()->create(['name' => $name]);
}

function ceilingUserForm(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'role_id' => $user->role_id,
    ], $overrides);
}

test('an admin_user delegate cannot give themselves role 1', function () {
    $delegate = userWithPermissions(['admin_user']);
    ceilingAdminRole();

    $this->actingAs($delegate)
        ->patch(route('admin.user.update', $delegate), ceilingUserForm($delegate, ['role_id' => Role::IS_ADMIN]))
        ->assertForbidden();

    expect($delegate->fresh()->role_id)->not->toBe(Role::IS_ADMIN)
        ->and(Gate::forUser($delegate->fresh())->allows('admin_setting'))->toBeFalse();
});

test('an admin_user delegate cannot assign a role with rights they lack', function () {
    $delegate = userWithPermissions(['admin_user']);
    $higher = userWithPermissions(['admin_user', 'admin_setting'])->role;
    $target = userWithPermissions([]);

    $this->actingAs($delegate)
        ->patch(route('admin.user.update', $target), ceilingUserForm($target, ['role_id' => $higher->id]))
        ->assertForbidden();

    $this->actingAs($delegate)
        ->post(route('admin.user.store'), [
            'name' => 'Neu', 'username' => 'neu', 'password' => 'Ein-langes-Kennwort-123!',
            'role_id' => $higher->id,
        ])
        ->assertForbidden();

    expect(User::where('username', 'neu')->exists())->toBeFalse();
});

test('an admin_user delegate cannot take over a role-1 admin', function () {
    $delegate = userWithPermissions(['admin_user']);
    $admin = User::factory()->create(['role_id' => ceilingAdminRole()->id]);
    $hash = $admin->password;

    $this->actingAs($delegate)
        ->patch(route('admin.user.update', $admin), ceilingUserForm($admin, ['password' => 'Uebernommen-123456!']))
        ->assertForbidden();
    $this->actingAs($delegate)->delete(route('admin.user.two-factor', $admin))->assertForbidden();
    $this->actingAs($delegate)->delete(route('admin.user.destroy', $admin))->assertForbidden();
    $this->actingAs($delegate)->get(route('admin.user.edit', $admin))->assertForbidden();

    expect($admin->fresh()->password)->toBe($hash)
        ->and(Hash::check('Uebernommen-123456!', $admin->fresh()->password))->toBeFalse();
});

test('an admin_user delegate still manages users at or below their level', function () {
    $delegate = userWithPermissions(['admin_user', 'server_viewAny']);
    $lower = userWithPermissions(['server_viewAny']);

    $this->actingAs($delegate)
        ->patch(route('admin.user.update', $lower), ceilingUserForm($lower, ['name' => 'Umbenannt']))
        ->assertRedirect();

    expect($lower->fresh()->name)->toBe('Umbenannt');
});

test('the user form only offers assignable roles', function () {
    $delegate = userWithPermissions(['admin_user']);
    ceilingAdminRole()->update(['name' => 'Volladmin']);
    userWithPermissions(['admin_setting'])->role->update(['name' => 'Einstellungen']);

    $this->actingAs($delegate)->get(route('admin.user.create'))
        ->assertOk()
        ->assertDontSee('Volladmin')
        ->assertDontSee('Einstellungen');
});

test('an admin_role delegate cannot tick rights they lack on their own role', function () {
    $delegate = userWithPermissions(['admin_role']);
    $role = $delegate->role;
    $setting = ceilingPermission('admin_setting');

    $this->actingAs($delegate)
        ->patch(route('admin.role.update', $role), [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('id')->push($setting->id)->all(),
        ])
        ->assertForbidden();

    expect(Gate::forUser($delegate->fresh())->allows('admin_setting'))->toBeFalse();
});

test('an admin_role delegate cannot edit role 1 or a higher role, nor create one', function () {
    $delegate = userWithPermissions(['admin_role']);
    $higher = userWithPermissions(['admin_role', 'admin_setting'])->role;
    $setting = ceilingPermission('admin_setting');

    $this->actingAs($delegate)->get(route('admin.role.edit', ceilingAdminRole()))->assertForbidden();
    $this->actingAs($delegate)->get(route('admin.role.edit', $higher))->assertForbidden();
    $this->actingAs($delegate)
        ->patch(route('admin.role.update', $higher), ['name' => $higher->name, 'permissions' => []])
        ->assertForbidden();
    $this->actingAs($delegate)
        ->post(route('admin.role.store'), ['name' => 'Hintertuer', 'permissions' => [$setting->id]])
        ->assertForbidden();

    expect(Role::where('name', 'Hintertuer')->exists())->toBeFalse();
});

test('an admin_role delegate can still build roles from their own rights', function () {
    $delegate = userWithPermissions(['admin_role', 'server_viewAny']);
    $server = ceilingPermission('server_viewAny');

    $this->actingAs($delegate)
        ->post(route('admin.role.store'), ['name' => 'Nur Server', 'permissions' => [$server->id]])
        ->assertRedirect();

    expect(Role::where('name', 'Nur Server')->first()->permissions->pluck('name')->all())->toBe(['server_viewAny']);
});

test('role 1 is not limited by the ceiling', function () {
    $admin = User::factory()->create(['role_id' => ceilingAdminRole()->id]);
    $target = userWithPermissions([]);
    $setting = ceilingPermission('admin_setting');

    $this->actingAs($admin)
        ->patch(route('admin.user.update', $target), ceilingUserForm($target, ['role_id' => Role::IS_ADMIN]))
        ->assertRedirect();
    $this->actingAs($admin)
        ->post(route('admin.role.store'), ['name' => 'Alles', 'permissions' => [$setting->id]])
        ->assertRedirect();

    expect((int) $target->fresh()->role_id)->toBe(Role::IS_ADMIN);
});

// Guards for routes added later: every admin route that acts on one user or
// one role must keep a delegate away from role 1. A new action ("reset
// password", "log in as") that forgets the check fails here.
function ceilingRoutes(string $prefix, string $parameter): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->getName() ?? '', $prefix)
            && in_array($parameter, $route->parameterNames(), true))
        ->map(fn ($route) => [$route->getName(), $route->methods()[0]])
        ->values()
        ->all();
}

test('no user route lets an admin_user delegate act on a role-1 admin', function () {
    $routes = ceilingRoutes('admin.user.', 'user');
    expect($routes)->not->toBeEmpty();

    $this->actingAs(userWithPermissions(['admin_user']));
    $admin = User::factory()->create(['role_id' => ceilingAdminRole()->id]);

    foreach ($routes as [$name, $method]) {
        $status = $this->call($method, route($name, $admin), ceilingUserForm($admin, ['password' => 'Uebernommen-123456!']))
            ->status();

        expect($status)->toBe(403, "$method $name answered $status");
    }

    expect(User::find($admin->id))->not->toBeNull()
        ->and(Hash::check('Uebernommen-123456!', $admin->fresh()->password))->toBeFalse();
});

test('no role route lets an admin_role delegate act on role 1', function () {
    $routes = ceilingRoutes('admin.role.', 'role');
    expect($routes)->not->toBeEmpty();

    $this->actingAs(userWithPermissions(['admin_role']));
    $role = ceilingAdminRole();

    foreach ($routes as [$name, $method]) {
        $status = $this->call($method, route($name, $role), ['name' => 'Umbenannt', 'permissions' => []])
            ->status();

        expect($status)->toBe(403, "$method $name answered $status");
    }

    expect($role->fresh()->name)->not->toBe('Umbenannt');
});
