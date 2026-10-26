<?php

use App\Models\User;
use App\Notifications\EmailChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

// Security audit findings: app tokens survived a password change, a password
// reset and an admin 2FA reset; and the email (where resets go) could be
// changed without the current password.

const PROTECTION_PASSWORD = 'Ein-Gutes-Kennwort-2026';

function protectedUser(array $permissions = ['server_viewAny']): User
{
    $user = userWithPermissions($permissions);
    $user->forceFill(['password' => Hash::make(PROTECTION_PASSWORD), 'email' => 'alt@example.test'])->save();

    return $user->fresh();
}

function appToken(User $user): string
{
    return $user->createToken('App iPhone', ['mobil'], now()->addDays(30))->plainTextToken;
}

/** Status of an app request with this token - fresh guards, like a new request from the phone. */
function appStatus(string $token): int
{
    app('auth')->forgetGuards();
    $status = test()->withToken($token)->getJson('/api/mobile/me')->status();

    // The token request leaves sanctum as the default guard in this test
    // process; a browser request afterwards needs the web guard again.
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    return $status;
}

// --- App tokens ---------------------------------------------------------------

test('changing the own password ends the app sign-ins', function () {
    $user = protectedUser();
    $token = appToken($user);
    expect(appStatus($token))->toBe(200);

    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => PROTECTION_PASSWORD,
        'password' => 'Ein-Neues-Kennwort-2026',
        'password_confirmation' => 'Ein-Neues-Kennwort-2026',
    ])->assertRedirect();

    expect(Hash::check('Ein-Neues-Kennwort-2026', $user->fresh()->password))->toBeTrue()
        ->and(appStatus($token))->toBe(401);
});

test('a password reset by mail ends the app sign-ins', function () {
    $user = protectedUser();
    $token = appToken($user);

    $this->post(route('password.store'), [
        'token' => Password::broker()->createToken($user),
        'username' => $user->username,
        'email' => $user->email,
        'password' => 'Ein-Neues-Kennwort-2026',
        'password_confirmation' => 'Ein-Neues-Kennwort-2026',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('Ein-Neues-Kennwort-2026', $user->fresh()->password))->toBeTrue()
        ->and(appStatus($token))->toBe(401);
});

test('an admin 2FA reset (lost phone) ends the app sign-ins', function () {
    $user = protectedUser();
    $user->forceFill(['two_factor_secret' => 'GEHEIM', 'two_factor_confirmed_at' => now()])->save();
    $token = appToken($user);

    $this->actingAs(adminUser())->delete(route('admin.user.two-factor', $user))->assertRedirect();

    expect(appStatus($token))->toBe(401);
});

test('requiring 2FA from someone without it ends the app sign-ins', function () {
    $user = protectedUser();
    $token = appToken($user);

    $user->update(['two_factor_required' => true]);

    expect(appStatus($token))->toBe(401);
});

test('requiring 2FA from someone who has it keeps the app signed in', function () {
    $user = protectedUser();
    $user->forceFill(['two_factor_secret' => 'GEHEIM', 'two_factor_confirmed_at' => now()])->save();
    $token = appToken($user);

    $user->update(['two_factor_required' => true]);

    expect(appStatus($token))->toBe(200);
});

test('other changes keep the app signed in', function () {
    $user = protectedUser();
    $token = appToken($user);

    $user->update(['name' => 'Neuer Name', 'locale' => 'en']);

    expect(appStatus($token))->toBe(200);
});

test('integration tokens of the admin area are not app sign-ins and stay', function () {
    $user = protectedUser();
    $integration = $user->createToken('Monitoring')->plainTextToken;

    $user->update(['password' => Hash::make('Ein-Neues-Kennwort-2026')]);

    app('auth')->forgetGuards();
    $this->withToken($integration)->getJson('/api/user')->assertOk();
});

test('a token still around when 2FA is required gets a 403, not data', function () {
    // Defence in depth: a path that sets the requirement without the model
    // event (raw update, import) must not leave the API open.
    $user = protectedUser();
    $token = appToken($user);
    $user->forceFill(['two_factor_required' => true])->saveQuietly();

    expect(appStatus($token))->toBe(403);

    app('auth')->forgetGuards();
    $this->withToken($user->createToken('Monitoring')->plainTextToken)->getJson('/api/user')->assertForbidden();
});

// --- Email change -------------------------------------------------------------

test('the email cannot be changed without the current password', function () {
    Notification::fake();
    $user = protectedUser();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => 'angreifer@example.test',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => 'angreifer@example.test', 'current_password' => 'falsch',
    ])->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->toBe('alt@example.test');
    Notification::assertNothingSent();
});

test('with the current password the email changes and the old address is told', function () {
    Notification::fake();
    $user = protectedUser();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => 'neu@example.test', 'current_password' => PROTECTION_PASSWORD,
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('neu@example.test');
    Notification::assertSentOnDemand(EmailChanged::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'alt@example.test');
});

test('name and language change without a password', function () {
    Notification::fake();
    $user = protectedUser();

    $this->actingAs($user)->patch(route('profile.update'), [
        // Same address, other spelling: not a change of the reset target.
        'name' => 'Anders', 'email' => 'ALT@example.test', 'locale' => 'en',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Anders');
    Notification::assertNothingSent();
});
