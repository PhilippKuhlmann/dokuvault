<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function demoAdmin(): User
{
    $rolle = Role::factory()->create(['id' => Role::IS_ADMIN, 'name' => 'Admin']);

    return User::factory()->create([
        'username' => 'admin',
        'password' => Hash::make('password'),
        'role_id' => $rolle->id,
    ]);
}

function eigenerBenutzer(): User
{
    $rolle = Role::factory()->create(['id' => (Role::max('id') ?? 0) + 100]);

    return User::factory()->create([
        'username' => 'selbst-angelegt',
        'password' => Hash::make('password'),
        'role_id' => $rolle->id,
    ]);
}

test('ohne Demo-Modus ist niemand geschützt', function () {
    config(['app.demo' => false]);
    $admin = demoAdmin();

    expect($admin->istDemoGeschuetzt())->toBeFalse();

    $this->actingAs($admin)->delete("/admin/user/{$admin->id}");
    $this->assertDatabaseMissing('users', ['id' => $admin->id]);
});

test('in der Demo lässt sich ein vordefinierter Zugang nicht löschen', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();

    $this->actingAs($admin)->delete("/admin/user/{$admin->id}")
        ->assertRedirect("/admin/user/{$admin->id}/edit");

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'username' => 'admin']);
});

test('in der Demo bleibt ein vordefinierter Zugang vollständig unverändert', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();
    $vorher = $admin->only(['name', 'username', 'email', 'role_id', 'customer_id']);

    $this->actingAs($admin)->patch("/admin/user/{$admin->id}", [
        'name' => 'Neuer Name',
        'username' => 'admin',
        'email' => 'gekapert@example.test',
        'password' => 'gekapertes-passwort',
        'role_id' => $admin->role_id,
    ])->assertRedirect("/admin/user/{$admin->id}/edit");

    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();
    expect($admin->fresh()->only(array_keys($vorher)))->toBe($vorher);
});

test('in der Demo lässt sich der Benutzername eines vordefinierten Zugangs nicht ändern', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();

    // Der Schutz erkennt den Zugang am Benutzernamen. Waere der aenderbar,
    // hebte eine Umbenennung den Schutz auf - und die dokumentierte Anmeldung
    // funktionierte nicht mehr.
    $this->actingAs($admin)->patch("/admin/user/{$admin->id}", [
        'name' => $admin->name,
        'username' => 'nicht-mehr-geschuetzt',
        'email' => 'admin@example.test',
        'role_id' => $admin->role_id,
    ]);

    expect($admin->fresh()->username)->toBe('admin');
    expect($admin->fresh()->istDemoGeschuetzt())->toBeTrue();
});

test('in der Demo lässt sich die Rolle eines vordefinierten Zugangs nicht herabstufen', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();
    $andere = Role::factory()->create(['id' => 500, 'name' => 'Kunde']);

    $this->actingAs($admin)->patch("/admin/user/{$admin->id}", [
        'name' => $admin->name,
        'username' => 'admin',
        'email' => 'admin@example.test',
        'role_id' => $andere->id,
    ]);

    // Eine herabgestufte Admin-Rolle sperrt genauso aus wie ein neues Passwort.
    expect($admin->fresh()->role_id)->toBe(Role::IS_ADMIN);
});

test('in der Demo lässt sich das eigene Passwort nicht über das Profil ändern', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();

    $this->actingAs($admin)->put('/password', [
        'current_password' => 'password',
        'password' => 'neues-passwort-123',
        'password_confirmation' => 'neues-passwort-123',
    ]);

    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();
});

test('in der Demo lässt sich der eigene Zugang nicht über das Profil löschen', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();

    $this->actingAs($admin)->delete('/profile', ['password' => 'password']);

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('selbst angelegte Benutzer bleiben auch in der Demo voll bearbeitbar', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();
    $eigener = eigenerBenutzer();

    expect($eigener->istDemoGeschuetzt())->toBeFalse();

    $this->actingAs($admin)->patch("/admin/user/{$eigener->id}", [
        'name' => $eigener->name,
        'username' => $eigener->username,
        'email' => 'neu@example.test',
        'password' => 'ein-neues-passwort',
        'role_id' => $eigener->role_id,
    ]);
    expect(Hash::check('ein-neues-passwort', $eigener->fresh()->password))->toBeTrue();

    $this->actingAs($admin)->delete("/admin/user/{$eigener->id}");
    $this->assertDatabaseMissing('users', ['id' => $eigener->id]);
});

test('das Bearbeiten-Formular zeigt den Hinweis und blendet Löschen aus', function () {
    config(['app.demo' => true]);
    $admin = demoAdmin();
    $eigener = eigenerBenutzer();

    $this->actingAs($admin)->get("/admin/user/{$admin->id}/edit")
        ->assertSee('Demo-Zugang')
        ->assertDontSee('admin.user.destroy')
        ->assertDontSee('name="username"', false);

    $this->actingAs($admin)->get("/admin/user/{$eigener->id}/edit")
        ->assertDontSee('Demo-Zugang')
        ->assertSee('Löschen!');
});
