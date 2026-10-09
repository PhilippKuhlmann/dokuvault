<?php

use App\Livewire\AdminNotifications;
use App\Livewire\NotificationProfile;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ExpiryNotice;
use App\Support\ExpiringItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function expiryAdmin(array $attributes = []): User
{
    $role = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);

    return User::factory()->create(['role_id' => $role->id, 'email' => fake()->unique()->safeEmail()] + $attributes);
}

function certificateDue(Customer $customer, int $days, string $name = 'www.example.org'): Certificate
{
    return Certificate::factory()->create([
        'customer_id' => $customer->id,
        'name' => $name,
        'expiry_date' => now()->addDays($days)->toDateString(),
    ]);
}

beforeEach(function () {
    Notification::fake();
    Setting::setzen(Setting::FRIST_VERTRAEGE, 30);
});

test('only users who switched it on get the mail', function () {
    $on = expiryAdmin(['expiry_mail' => true]);
    $off = expiryAdmin(['expiry_mail' => false]);
    certificateDue(Customer::factory()->create(), 10);

    $this->artisan('expiry:notify')->assertSuccessful();

    Notification::assertSentTo($on, ExpiryNotice::class, fn ($n) => $n->items->count() === 1);
    Notification::assertNotSentTo($off, ExpiryNotice::class);
});

test('outside the warning period nothing is sent', function () {
    expiryAdmin(['expiry_mail' => true]);
    certificateDue(Customer::factory()->create(), 60);

    $this->artisan('expiry:notify')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a user bound to one customer only hears about that customer', function () {
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();
    certificateDue($own, 5, 'own.example.org');
    certificateDue($other, 5, 'other.example.org');

    $user = userWithPermissions(['certificate_viewAny']);
    $user->update(['customer_id' => $own->id, 'expiry_mail' => true, 'email' => 'kunde@example.org']);

    $this->artisan('expiry:notify')->assertSuccessful();

    Notification::assertSentTo($user, ExpiryNotice::class,
        fn ($n) => $n->items->pluck('name')->all() === ['own.example.org']);
});

test('kinds the role may not see stay out of the mail', function () {
    $customer = Customer::factory()->create();
    certificateDue($customer, 5);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'example.org',
        'expiry_date' => now()->addDays(5)->toDateString()]);

    $user = userWithPermissions(['domain_viewAny']);
    $user->update(['expiry_mail' => true, 'email' => 'kunde@example.org']);

    $this->artisan('expiry:notify')->assertSuccessful();

    Notification::assertSentTo($user, ExpiryNotice::class,
        fn ($n) => $n->items->pluck('name')->all() === ['example.org']);
});

test('each item is mailed once when due and once when expired', function () {
    $user = expiryAdmin(['expiry_mail' => true]);
    certificateDue(Customer::factory()->create(), 1);

    $this->artisan('expiry:notify');
    $this->artisan('expiry:notify');
    Notification::assertSentToTimes($user, ExpiryNotice::class, 1);

    $this->travel(3)->days();
    $this->artisan('expiry:notify');
    $this->artisan('expiry:notify');
    Notification::assertSentToTimes($user, ExpiryNotice::class, 2);
});

test('a renewed certificate is a new item', function () {
    $user = expiryAdmin(['expiry_mail' => true]);
    $cert = certificateDue(Customer::factory()->create(), 5);

    $this->artisan('expiry:notify');
    $cert->update(['expiry_date' => now()->addDays(20)->toDateString()]);
    $this->artisan('expiry:notify');

    Notification::assertSentToTimes($user, ExpiryNotice::class, 2);
});

test('a failed send is not recorded, so the next run tries again', function () {
    expiryAdmin(['expiry_mail' => true]);
    certificateDue(Customer::factory()->create(), 5);

    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
    $this->artisan('expiry:notify')->assertFailed();

    expect(DB::table('expiry_notices')->count())->toBe(0);
});

test('the admin chooses what the mail reports', function () {
    $this->actingAs(expiryAdmin());

    Livewire::test(AdminNotifications::class)
        ->set('kinds', ['domain', 'certificate'])
        ->assertHasNoErrors();

    expect(Setting::expiryMailKinds())->toBe(['certificate', 'domain']);

    $user = expiryAdmin(['expiry_mail' => true]);
    certificateDue(Customer::factory()->create(), 5);
    Setting::setzen(Setting::EXPIRY_MAIL_KINDS, 'domain');

    $this->artisan('expiry:notify');
    Notification::assertNotSentTo($user, ExpiryNotice::class);
});

test('the admin switches the mail on for a customer user', function () {
    $this->actingAs(expiryAdmin());
    $user = User::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    Livewire::test(AdminNotifications::class)
        ->assertSee($user->name)
        ->call('toggle', $user->id);

    expect($user->fresh()->expiry_mail)->toBeTrue();
});

test('the page belongs to the settings permission', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));

    $this->get(route('admin.notifications'))->assertForbidden();
});

test('every user can switch it in the profile', function () {
    $user = userWithPermissions([]);
    $user->update(['customer_id' => Customer::factory()->create()->id]);
    $this->actingAs($user);

    $this->get(route('profile.edit'))->assertOk()->assertSee(__('Ablaufende Einträge per Mail'));

    Livewire::test(NotificationProfile::class)->set('enabled', true);

    expect($user->fresh()->expiry_mail)->toBeTrue();
});

test('the mail renders with customer, link and date', function () {
    $user = expiryAdmin();
    $cert = certificateDue(Customer::factory()->create(['name' => 'Muster GmbH']), 3);

    $items = ExpiringItems::forUser($user, Setting::EXPIRY_KINDS);
    $html = (string) (new ExpiryNotice($items))->toMail($user)->render();

    expect($html)->toContain('Muster GmbH')
        ->toContain($cert->name)
        ->toContain(now()->addDays(3)->format('d.m.Y'))
        ->toContain('highlight='.$cert->id);
});
