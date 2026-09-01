<?php

use App\Http\Kernel;
use App\Models\User;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Der Zaehler, den LoginRequest je Konto und Herkunft fuehrt. */
function kontoSchluessel(string $benutzername): string
{
    return Str::transliterate(Str::lower($benutzername).'|127.0.0.1');
}

/**
 * Ein anmeldbarer Nutzer. Ueber userWithPermissions(), weil User::factory()
 * eine role_id ohne passende Rolle setzt - die Oberflaeche laesst sich damit
 * nicht rendern.
 */
function einNutzer(string $kennwort = 'Richtiges-Kennwort-2026'): User
{
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make($kennwort)])->save();

    return $nutzer->fresh();
}

function letzterFehler(): string
{
    return session('errors')?->first('username') ?? '';
}

// --- Bremse gegen Durchprobieren -------------------------------------------

test('nach fünf Fehlversuchen ist das Konto gesperrt - auch für das richtige Kennwort', function () {
    $nutzer = einNutzer();

    foreach (range(1, 5) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    // Selbst mit dem richtigen Kennwort: waehrend der Sperre geht nichts.
    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    expect(letzterFehler())->toContain('Zu viele');
    $this->assertGuest();
});

test('vier Fehlversuche sperren noch nicht', function () {
    $nutzer = einNutzer();

    foreach (range(1, 4) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    $this->assertAuthenticatedAs($nutzer);
});

test('die Sperre hält länger als eine Minute', function () {
    $nutzer = einNutzer();

    foreach (range(1, 5) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    // Der Laravel-Standard waeren 60 Sekunden - damit waeren 300 Versuche pro
    // Stunde und Konto moeglich.
    expect(RateLimiter::availableIn(kontoSchluessel($nutzer->username)))->toBeGreaterThan(60);
});

test('eine erfolgreiche Anmeldung leert beide Zähler', function () {
    $nutzer = einNutzer();

    foreach (range(1, 4) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    expect(RateLimiter::attempts(kontoSchluessel($nutzer->username)))->toBe(4);

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    expect(RateLimiter::attempts(kontoSchluessel($nutzer->username)))->toBe(0)
        ->and(RateLimiter::attempts('anmeldung|127.0.0.1'))->toBe(0);
});

test('ein Kennwort gegen viele Nutzernamen wird gebremst', function () {
    // Der Zaehler je Konto greift hier nie: jeder Nutzername bekommt einen
    // eigenen, frischen. Ohne den Zaehler je Herkunft koennte man so beliebig
    // lange ein gaengiges Kennwort gegen die ganze Nutzerliste probieren.
    foreach (range(1, 29) as $nummer) {
        $this->post('/login', ['username' => "niemand{$nummer}", 'password' => 'Sommer2026!']);
    }

    // Gegenprobe: bis hierher ist es der normale Fehlschlag, keine Sperre.
    expect(letzterFehler())->toBe(trans('auth.failed'));

    $this->post('/login', ['username' => 'niemand30', 'password' => 'Sommer2026!']);
    $this->post('/login', ['username' => 'niemand31', 'password' => 'Sommer2026!']);

    expect(letzterFehler())->toContain('Zu viele');
});

test('die Sperrmeldung ist auf Deutsch', function () {
    expect(trans('auth.throttle', ['seconds' => 900, 'minutes' => 15]))
        ->toContain('Zu viele Anmeldeversuche');
});

test('Kennwort vergessen ist gedrosselt', function () {
    foreach (range(1, 5) as $ignoriert) {
        $this->post('/forgot-password', ['username' => 'gibtesnicht'])->assertStatus(302);
    }

    $this->post('/forgot-password', ['username' => 'gibtesnicht'])->assertStatus(429);
});

test('Kennwort zurücksetzen ist gedrosselt', function () {
    $daten = [
        'token' => 'geraten',
        'username' => 'gibtesnicht',
        'password' => 'Neues-Kennwort-2026',
        'password_confirmation' => 'Neues-Kennwort-2026',
    ];

    foreach (range(1, 5) as $ignoriert) {
        $this->post('/reset-password', $daten);
    }

    $this->post('/reset-password', $daten)->assertStatus(429);
});

// --- Sitzungsverhalten ------------------------------------------------------

test('die Sitzungskennung wechselt bei der Anmeldung', function () {
    $nutzer = einNutzer();

    $this->get('/login');
    $alte = session()->getId();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    expect(session()->getId())->not->toBe($alte);
    $this->assertAuthenticatedAs($nutzer);
});

test('Abmelden beendet die Sitzung', function () {
    $nutzer = einNutzer();

    $this->actingAs($nutzer)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

test('eine Sitzung überlebt die Kennwortänderung ihres Nutzers nicht', function () {
    $nutzer = einNutzer();
    $alterHash = $nutzer->password;

    // Anderswo - vom Nutzer selbst oder vom Administrator - wird das Kennwort
    // geaendert. Diese Sitzung kennt noch den alten Hash.
    $nutzer->forceFill(['password' => Hash::make('Ganz-Neues-Kennwort-2026')])->save();

    $this->actingAs($nutzer)
        ->withSession(['password_hash_web' => $alterHash])
        ->get('/profile')
        ->assertRedirect('/login');
});

test('Gegenprobe: mit dem aktuellen Kennwort-Hash bleibt die Sitzung gültig', function () {
    $nutzer = einNutzer();

    $this->actingAs($nutzer)
        ->withSession(['password_hash_web' => $nutzer->password])
        ->get('/profile')
        ->assertStatus(200);
});

test('die eigene Sitzung überlebt die eigene Kennwortänderung', function () {
    $nutzer = einNutzer();
    $alterHash = $nutzer->password;

    $this->actingAs($nutzer)
        ->withSession(['password_hash_web' => $alterHash])
        ->put('/password', [
            'current_password' => 'Richtiges-Kennwort-2026',
            'password' => 'Ganz-Neues-Kennwort-2026',
            'password_confirmation' => 'Ganz-Neues-Kennwort-2026',
        ])
        ->assertSessionHasNoErrors('updatePassword');

    // Der Hash in der Sitzung wurde mitgezogen, sonst waere der Nutzer beim
    // naechsten Aufruf selbst ausgesperrt.
    expect(session('password_hash_web'))->not->toBe($alterHash)
        ->and(Hash::check('Ganz-Neues-Kennwort-2026', $nutzer->fresh()->password))->toBeTrue();
});

test('AuthenticateSession hängt in der Gruppe web und gilt damit auch für Livewire', function () {
    // Nur an einzelnen Routen wuerde die Bindung fuer /livewire/update fehlen -
    // und damit fuer fast alles, was diese Anwendung tut.
    $gruppe = (new ReflectionClass(Kernel::class))
        ->newInstanceWithoutConstructor();

    $eigenschaft = (new ReflectionClass($gruppe))->getProperty('middlewareGroups');
    $eigenschaft->setAccessible(true);

    expect($eigenschaft->getValue($gruppe)['web'])
        ->toContain(AuthenticateSession::class);
});

test('eine Anmeldung räumt einen fremden Kennwort-Hash aus der Sitzung', function () {
    // Sonst wirft AuthenticateSession den frisch Angemeldeten beim naechsten
    // Aufruf wieder hinaus - und der landet erneut hier: eine Anmeldeschleife.
    $nutzer = einNutzer();

    $this->withSession(['password_hash_web' => Hash::make('etwas-ganz-anderes')])
        ->post('/login', [
            'username' => $nutzer->username,
            'password' => 'Richtiges-Kennwort-2026',
        ]);

    $this->assertAuthenticatedAs($nutzer);

    $this->get('/profile')->assertStatus(200);
});
