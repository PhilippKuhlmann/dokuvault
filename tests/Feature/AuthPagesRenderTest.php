<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('Gast-Auth-Seiten rendern ohne Fehler', function (string $uri) {
    $this->get($uri)->assertStatus(200);
})->with([
    '/login',
    '/forgot-password',
    '/reset-password/testtoken',
]);

// Die Selbstregistrierung ist entfernt: Nutzer legt ein Administrator an.
// /register faellt jetzt in die Kundenroute {customer} und landet fuer Gaeste
// bei der Anmeldung - eine Registrierungsseite gibt es nicht mehr.
test('es gibt keine Selbstregistrierung', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertRedirect('/login');

    $this->post('/register', [
        'name' => 'Eindringling',
        'username' => 'eindringling',
        'password' => 'Sehr-Geheim-2026',
        'password_confirmation' => 'Sehr-Geheim-2026',
    ]);

    expect(User::where('username', 'eindringling')->exists())->toBeFalse();
});

test('confirm-password rendert für angemeldete Nutzer', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/confirm-password')->assertStatus(200);
});
