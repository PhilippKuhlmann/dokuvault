<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

// Security audit finding: invitations and "forgot password" shared one token
// table, so a reset link (one hour) also worked on /einladung for a week.

function invitationForm(User $user, string $token): array
{
    return [
        'token' => $token,
        'username' => $user->username,
        'password' => 'Ein-Neues-Kennwort-2026',
        'password_confirmation' => 'Ein-Neues-Kennwort-2026',
    ];
}

test('a forgot-password token does not work as an invitation', function () {
    $user = User::factory()->create(['email' => 'nutzer@example.test', 'invited_at' => now()]);
    $resetToken = Password::broker()->createToken($user);

    $this->post(route('einladung.speichern'), invitationForm($user, $resetToken))
        ->assertSessionHasErrors('username');

    expect(Hash::check('Ein-Neues-Kennwort-2026', $user->fresh()->password))->toBeFalse();
});

test('an invitation token still works', function () {
    $user = User::factory()->create(['email' => 'neu@example.test', 'invited_at' => now()]);
    $token = Password::broker('einladung')->createToken($user);

    $this->post(route('einladung.speichern'), invitationForm($user, $token))
        ->assertRedirect(route('login'));

    expect(Hash::check('Ein-Neues-Kennwort-2026', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->invited_at)->toBeNull();
});

test('an account without an open invitation is refused on /einladung', function () {
    $user = User::factory()->create(['email' => 'alt@example.test', 'invited_at' => null]);
    $token = Password::broker('einladung')->createToken($user);

    $this->post(route('einladung.speichern'), invitationForm($user, $token))
        ->assertSessionHasErrors('username');

    expect(Hash::check('Ein-Neues-Kennwort-2026', $user->fresh()->password))->toBeFalse();
});

test('the migration carries open invitations over, not resets of others', function () {
    DB::table('invitation_tokens')->delete();
    $invited = User::factory()->create(['email' => 'eingeladen@example.test', 'invited_at' => now()]);
    $other = User::factory()->create(['email' => 'andere@example.test', 'invited_at' => null]);
    DB::table('password_resets')->insert([
        ['email' => $invited->email, 'token' => Hash::make('EINLADUNG'), 'created_at' => now()],
        ['email' => $other->email, 'token' => Hash::make('RESET'), 'created_at' => now()],
    ]);

    $migration = require database_path('migrations/2026_10_26_130000_create_invitation_tokens_table.php');
    $migration->down();
    $migration->up();

    expect(DB::table('invitation_tokens')->pluck('email')->all())->toBe([$invited->email]);
});
