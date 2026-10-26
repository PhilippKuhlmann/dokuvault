<?php

use App\Livewire\ProtokollKennwort;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\PasswordHistory;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// Security audit findings: the activity log showed previous passwords without
// brake, log entry or customer check, and kept bcrypt hashes of account
// passwords; and an empty SESSION_SECURE_COOKIE= switched off the Secure flag.

function historyFirewall(Customer $customer, string $old = 'Das-Alte-2026'): array
{
    $firewall = Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'cloud_backup_password' => $old,
    ]);
    $firewall->update(['cloud_backup_password' => 'Das-Neue-2026']);

    return [$firewall, PasswordHistory::where('subject_id', $firewall->id)->where('subject_type', Firewall::class)->pluck('id')->all()];
}

function historyViewer(array $permissions = ['admin_activity', 'firewall_viewAny'], ?Customer $customer = null): User
{
    $user = userWithPermissions($permissions);
    $user->forceFill(['customer_id' => $customer?->id])->save();

    return $user->fresh();
}

test('a revealed previous password is logged like every other reveal', function () {
    [$firewall, $ids] = historyFirewall(Customer::factory()->create());
    $this->actingAs(historyViewer());

    Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['cloud_backup_password']])
        ->call('zeigen')
        ->assertSee('Das-Alte-2026');

    expect(Activity::where('event', 'kennwort_angesehen')->where('subject_id', $firewall->id)->count())->toBe(1);
});

test('a customer-bound log viewer cannot reveal another customer\'s passwords', function () {
    $own = Customer::factory()->create();
    [, $ids] = historyFirewall(Customer::factory()->create());
    $this->actingAs(historyViewer(customer: $own));

    Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['cloud_backup_password']])
        ->call('zeigen')
        ->assertForbidden();
});

test('without the right to see the object there is no previous password', function () {
    [, $ids] = historyFirewall(Customer::factory()->create());
    $this->actingAs(historyViewer(['admin_activity']));

    Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['cloud_backup_password']])
        ->call('zeigen')
        ->assertForbidden();
});

test('previous passwords share the brake of every other reveal', function () {
    [, $ids] = historyFirewall(Customer::factory()->create());
    $this->actingAs(historyViewer());

    $component = Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['cloud_backup_password']]);

    for ($i = 0; $i < Setting::kennwortAbrufe(); $i++) {
        $component->call('zeigen')->call('verbergen');
    }

    $component->call('zeigen')
        ->assertHasErrors('kennwort')
        ->assertDontSee('Das-Alte-2026');
});

test('an account password change is logged, but its hash is not kept', function () {
    $this->actingAs(adminUser());
    $user = User::factory()->create(['password' => Hash::make('Alt-Geheim-2026')]);

    $user->update(['password' => Hash::make('Neu-Geheim-2026')]);

    expect(PasswordHistory::where('subject_type', User::class)->count())->toBe(0)
        ->and(Activity::where('event', 'password_changed')->where('subject_id', $user->id)->where('subject_type', User::class)->exists())->toBeTrue();
});

test('the migration removes account password hashes already kept', function () {
    $user = User::factory()->create();
    PasswordHistory::create([
        'subject_type' => User::class, 'subject_id' => $user->id, 'subject_name' => $user->name,
        'field' => 'password', 'value' => Hash::make('Alt'),
    ]);
    [, $firewallIds] = historyFirewall(Customer::factory()->create());

    (require database_path('migrations/2026_10_26_120000_remove_account_password_hashes_from_history.php'))->up();

    expect(PasswordHistory::where('subject_type', User::class)->count())->toBe(0)
        ->and(PasswordHistory::whereIn('id', $firewallIds)->count())->toBe(count($firewallIds));
});

// --- Secure cookie ------------------------------------------------------------

dataset('secure cookie', [
    'empty line from .env.example, production' => ['', 'production', 'http://doku.test', true],
    'empty line, https address' => ['', 'local', 'https://doku.test', true],
    'empty line, plain http in the LAN' => ['', 'local', 'http://doku.test', false],
    'explicitly off' => ['false', 'production', 'https://doku.test', false],
    'explicitly on' => ['true', 'local', 'http://doku.test', true],
]);

test('an empty SESSION_SECURE_COOKIE falls back to the automatic choice', function (string $value, string $env, string $url, bool $secure) {
    $backup = [$_SERVER['SESSION_SECURE_COOKIE'] ?? null, $_SERVER['APP_ENV'] ?? null, $_SERVER['APP_URL'] ?? null];
    $_SERVER['SESSION_SECURE_COOKIE'] = $value;
    $_SERVER['APP_ENV'] = $env;
    $_SERVER['APP_URL'] = $url;

    try {
        $config = require config_path('session.php');
        expect($config['secure'])->toBe($secure);
    } finally {
        foreach (['SESSION_SECURE_COOKIE', 'APP_ENV', 'APP_URL'] as $i => $key) {
            if ($backup[$i] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $backup[$i];
            }
        }
    }
})->with('secure cookie');

test('.env.example no longer ships an empty SESSION_SECURE_COOKIE line', function () {
    expect(file_get_contents(base_path('.env.example')))->not->toMatch('/^SESSION_SECURE_COOKIE=\s*$/m');
});
