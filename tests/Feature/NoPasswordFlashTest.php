<?php

// Security audit hardening note: "password" and "password_confirmation" had
// been taken out of the flash exclusions, so every failed validation put
// the typed password in plain text into the session (and old() wrote it
// back into the page).

test('a failed validation does not put passwords into the session', function () {
    $this->actingAs(adminUser());

    $this->from(route('admin.user.create'))->post(route('admin.user.store'), [
        // name missing: the request fails validation and flashes its input
        'username' => 'neu',
        'password' => 'Geheim-Flash-2026',
        'password_confirmation' => 'Geheim-Flash-2026',
        'bmcPassword' => 'BMC-Flash-2026',
    ])->assertSessionHasErrors('name');

    $old = session()->getOldInput();

    expect($old)->toHaveKey('username')
        ->and($old)->not->toHaveKey('password')
        ->and($old)->not->toHaveKey('password_confirmation')
        // Every secret column is excluded as well, not only "password".
        ->and($old)->not->toHaveKey('bmcPassword');
});

test('a password field is never filled back in from old input', function () {
    $this->actingAs(adminUser());

    $this->withSession(['_old_input' => ['password' => 'Geheim-Flash-2026', 'username' => 'neu']])
        ->get(route('admin.user.create'))
        ->assertOk()
        ->assertDontSee('Geheim-Flash-2026')
        ->assertSee('neu');
});
