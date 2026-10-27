<?php

use App\Http\Controllers\TwoFactorController;

// Security audit hardening note: confirming the second factor had no attempt
// limit - whoever held the session could guess the six digits for a pending
// secret.

test('confirming the second factor is throttled', function () {
    $user = userWithPermissions([]);
    $this->actingAs($user)->post(route('two-factor.begin'));
    $secret = session(TwoFactorController::IN_ARBEIT);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('two-factor.confirm'), ['code' => '000000']);
    }

    // Even the right code is turned away now - the limit is on attempts.
    $this->post(route('two-factor.confirm'), ['code' => gueltigerCode($secret)])
        ->assertStatus(429);

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('the two-factor URLs are English', function () {
    expect(route('two-factor.confirm', absolute: false))->toBe('/two-factor/confirm')
        ->and(route('two-factor.verwerfen', absolute: false))->toBe('/two-factor/cancel')
        ->and(route('two-factor.codes', absolute: false))->toBe('/two-factor/recovery-codes');
});
