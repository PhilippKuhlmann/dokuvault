<?php

use App\Models\User;
use App\Support\ZweiteStufe;
use Illuminate\Support\Facades\Hash;
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

/*
 * Die zweite Stufe und die Einladung sind beim Umbau auf x-anmeldeblatt
 * gewandert - und ausgerechnet sie hatte niemand auf "rendert ueberhaupt"
 * geprueft. Die Einladung hatte gar keinen Test, und von der zweiten Stufe war
 * nur der Fall abgedeckt, in dem sie zur Anmeldung zurueckschickt.
 *
 * Beide brauchen einen Zustand und passen deshalb nicht in die Liste oben.
 */
test('die Einladung rendert ihr Blatt', function () {
    $antwort = $this->get(route('einladung.formular', [
        'token' => 'ein-beliebiger-token',
        'username' => 'neue.kollegin',
    ]));

    // Der Benutzername aus der Adresse steht im Feld: Wer der Einladung folgt,
    // soll ihn nicht abtippen muessen.
    $antwort->assertStatus(200)->assertSee('neue.kollegin', false);
});

test('die zweite Stufe rendert ihr Blatt', function () {
    $geheimnis = app(ZweiteStufe::class)->geheimnisErzeugen();

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill([
        'password' => Hash::make('Ein-Gutes-Kennwort-2026'),
        'two_factor_secret' => $geheimnis,
        'two_factor_recovery_codes' => app(ZweiteStufe::class)->wiederherstellungscodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026'])
        ->assertRedirect(route('two-factor.login'));

    $this->get(route('two-factor.login'))->assertStatus(200);
});

/*
 * Der Grund fuer diesen Test: Genau das war auseinandergelaufen. Die Anmeldung
 * bekam den Netzplan, die zweite Stufe und die Einladung zogen nach - und
 * "Kennwort vergessen", "neues Kennwort" und die Kennwortabfrage blieben auf
 * der alten Huelle stehen. Auffallen konnte das nur dem, der zufaellig
 * hinklickte.
 *
 * Eine neue Gast-Seite ohne x-anmeldeblatt ist ab jetzt ein roter Test und
 * keine Entdeckung in drei Monaten.
 */
test('jede Gast-Seite traegt dieselbe Huelle', function () {
    $ohneHuelle = [];

    foreach (glob(resource_path('views/auth/*.blade.php')) as $datei) {
        // Der Unterstrich sagt: Teilstueck, kein eigenes Blatt (_netzplan).
        if (str_starts_with(basename($datei), '_')) {
            continue;
        }

        if (! str_contains(file_get_contents($datei), '<x-anmeldeblatt')) {
            $ohneHuelle[] = basename($datei);
        }
    }

    expect($ohneHuelle)->toBe([],
        'Diese Gast-Seiten stehen nicht auf x-anmeldeblatt und sehen damit aus '.
        'wie eine andere Software: '.implode(', ', $ohneHuelle));
});
