<?php

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\InitialSetup;
use Database\Seeders\LocalDatabaseSeeder;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

// Security audit finding: the production seeder created a working
// admin/password administrator. Now the first administrator is created
// under /setup with a one-time code.

function setupForm(string $code, array $overrides = []): array
{
    return array_merge([
        'setup_code' => $code,
        'name' => 'Erste Administratorin',
        'username' => 'chefin',
        'email' => 'chefin@example.test',
        'password' => 'Ein-Gutes-Kennwort-2026',
        'password_confirmation' => 'Ein-Gutes-Kennwort-2026',
    ], $overrides);
}

/** Runs the production seeder like an install does and returns the printed code. */
function seedProductionAndReadCode(): string
{
    Artisan::call('db:seed', ['--class' => ProductionDatabaseSeeder::class, '--force' => true]);

    preg_match('/Einrichtungscode: ([A-Z0-9-]+)/', Artisan::output(), $match);
    expect($match)->not->toBeEmpty('the seeder printed no setup code');

    return $match[1];
}

test('the production seeder creates no admin/password account', function () {
    seedProductionAndReadCode();

    expect(User::count())->toBe(0)
        ->and(InitialSetup::pending())->toBeTrue();

    $this->post('/login', ['username' => 'admin', 'password' => 'password']);
    $this->assertGuest();
});

test('a fresh production install leads every page to /setup', function () {
    seedProductionAndReadCode();

    $this->get('/')->assertRedirect(route('setup'));
    $this->get('/login')->assertRedirect(route('setup'));
    $this->get(route('setup'))->assertOk()->assertSee(__('Einrichtungscode'));
});

test('the code from the seeder creates the first administrator', function () {
    $code = seedProductionAndReadCode();

    $this->post(route('setup.store'), setupForm($code))
        ->assertRedirect(route('login'));

    $admin = User::where('username', 'chefin')->sole();
    expect($admin->role_id)->toBe(Role::IS_ADMIN)
        ->and(Hash::check('Ein-Gutes-Kennwort-2026', $admin->password))->toBeTrue()
        ->and(Setting::wert(Setting::SETUP_CODE))->toBeNull()
        ->and(InitialSetup::pending())->toBeFalse();

    // The page is gone for good, and the normal login works.
    $this->get(route('setup'))->assertNotFound();
    $this->post(route('setup.store'), setupForm($code, ['username' => 'zweiter']))->assertNotFound();
    $this->get('/login')->assertOk();

    $this->post('/login', ['username' => 'chefin', 'password' => 'Ein-Gutes-Kennwort-2026']);
    $this->assertAuthenticatedAs($admin);
});

test('a wrong code creates nobody', function () {
    seedProductionAndReadCode();

    $this->post(route('setup.store'), setupForm('FALSCH-FALSCH-FALSCH-FALSC'))
        ->assertSessionHasErrors('setup_code');

    expect(User::count())->toBe(0);
});

test('the setup follows the password rules', function () {
    $code = seedProductionAndReadCode();

    $this->post(route('setup.store'), setupForm($code, ['password' => 'kurz', 'password_confirmation' => 'kurz']))
        ->assertSessionHasErrors('password');

    expect(User::count())->toBe(0)
        ->and(InitialSetup::pending())->toBeTrue();
});

test('the code is accepted in lower case and with spaces around it', function () {
    $code = seedProductionAndReadCode();

    $this->post(route('setup.store'), setupForm('  '.strtolower($code).' '))
        ->assertRedirect(route('login'));

    expect(User::where('username', 'chefin')->exists())->toBeTrue();
});

test('SETUP_TOKEN from the environment works instead of a generated code', function () {
    config(['custom.setup_token' => 'mein-fester-code-123']);
    $this->seed(ProductionDatabaseSeeder::class);

    expect(Setting::wert(Setting::SETUP_CODE))->toBeNull()
        ->and(InitialSetup::pending())->toBeTrue();

    $this->post(route('setup.store'), setupForm('mein-fester-code-123'))
        ->assertRedirect(route('login'));

    expect(InitialSetup::hasAdmin())->toBeTrue()
        ->and(InitialSetup::pending())->toBeFalse();
});

test('setup:code replaces a lost code', function () {
    $old = seedProductionAndReadCode();

    $this->artisan('setup:code')->assertSuccessful();

    expect(InitialSetup::check($old))->toBeFalse();
});

test('setup:code refuses once an administrator exists', function () {
    adminUser();

    $this->artisan('setup:code')->assertFailed();

    expect(Setting::wert(Setting::SETUP_CODE))->toBeNull();
});

test('without a setup code nothing changes - existing installations', function () {
    // No code, no administrator: an install from before /setup, or a test.
    expect(InitialSetup::pending())->toBeFalse();

    $this->get('/login')->assertOk();
    $this->get(route('setup'))->assertNotFound();
});

test('an existing administrator wins over a leftover code', function () {
    InitialSetup::newCode();
    adminUser();

    expect(InitialSetup::pending())->toBeFalse();
    $this->get('/login')->assertOk();
});

test('the demo keeps its documented admin/password login', function () {
    $this->seed(LocalDatabaseSeeder::class);

    $admin = User::where('username', 'admin')->sole();

    expect($admin->role_id)->toBe(Role::IS_ADMIN)
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and(InitialSetup::pending())->toBeFalse();
});
