<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\Einladung;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function alsBenutzerverwaltung(): User
{
    $nutzer = userWithPermissions(['admin_user']);
    test()->actingAs($nutzer);

    return $nutzer;
}

function einladungsdaten(array $abweichend = []): array
{
    return array_merge([
        'name' => 'Neue Kollegin',
        'username' => 'neue.kollegin',
        'email' => 'neue.kollegin@example.test',
        'role_id' => Role::first()->id,
        'customer_id' => null,
        'einladen' => '1',
    ], $abweichend);
}

test('ein eingeladener Benutzer entsteht ohne Kennwort vom Administrator', function () {
    Notification::fake();
    alsBenutzerverwaltung();

    // Kein password-Feld: Der Administrator soll gar keins vergeben koennen.
    $this->post(route('admin.user.store'), einladungsdaten())
        ->assertRedirect(route('admin.user.index'));

    $neu = User::where('username', 'neue.kollegin')->first();

    expect($neu)->not->toBeNull();

    Notification::assertSentTo($neu, Einladung::class);
});

test('das gesetzte Zufallskennwort ist keins, mit dem man hereinkommt', function () {
    Notification::fake();
    alsBenutzerverwaltung();

    $this->post(route('admin.user.store'), einladungsdaten());

    $neu = User::where('username', 'neue.kollegin')->first();

    // Die Spalte ist NOT NULL - leer lassen geht nicht. Aber es darf nichts
    // sein, was jemand erraten oder erwarten koennte.
    expect($neu->password)->not->toBeEmpty()
        ->and(Hash::check('', $neu->password))->toBeFalse()
        ->and(Hash::check('password', $neu->password))->toBeFalse();
});

test('ohne E-Mail-Adresse gibt es keine Einladung', function () {
    Notification::fake();
    alsBenutzerverwaltung();

    $this->post(route('admin.user.store'), einladungsdaten(['email' => null]))
        ->assertSessionHasErrors('email');

    expect(User::where('username', 'neue.kollegin')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('ohne Einladung bleibt das Kennwort Pflicht', function () {
    alsBenutzerverwaltung();

    $daten = einladungsdaten(['einladen' => '0']);

    $this->post(route('admin.user.store'), $daten)->assertSessionHasErrors('password');
});

test('der Link führt auf ein Formular, in dem der Benutzer sein Kennwort vergibt', function () {
    Notification::fake();
    alsBenutzerverwaltung();

    $this->post(route('admin.user.store'), einladungsdaten());
    $neu = User::where('username', 'neue.kollegin')->first();

    $token = null;
    Notification::assertSentTo($neu, Einladung::class, function ($benachrichtigung) use (&$token, $neu) {
        // Den Token aus dem Link ziehen, den der Benutzer wirklich bekommt.
        $nachricht = $benachrichtigung->toMail($neu);
        preg_match('#/einladung/([^?]+)#', $nachricht->actionUrl, $treffer);
        $token = $treffer[1] ?? null;

        return $token !== null;
    });

    // Der Link ist fuer den Eingeladenen, nicht fuer den Administrator: Die
    // Route liegt hinter "guest", ein Angemeldeter wird weggeschickt. Genau
    // richtig - sonst setzte der Administrator am eigenen Rechner eben doch
    // das Kennwort des neuen Benutzers.
    $this->post('/logout');

    $this->post(route('einladung.speichern'), [
        'token' => $token,
        'username' => $neu->username,
        'password' => 'Selbst-Gewaehlt-2026',
        'password_confirmation' => 'Selbst-Gewaehlt-2026',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Selbst-Gewaehlt-2026', $neu->fresh()->password))->toBeTrue();

    // Und damit kommt er auch wirklich herein.
    $this->post('/login', ['username' => $neu->username, 'password' => 'Selbst-Gewaehlt-2026']);
    $this->assertAuthenticatedAs($neu->fresh());
});

test('ein geratener Token setzt kein Kennwort', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'geraten@example.test'])->save();
    $nutzer->refresh();
    $altesKennwort = $nutzer->password;

    $this->post(route('einladung.speichern'), [
        'token' => 'geraten',
        'username' => $nutzer->username,
        'password' => 'Fremd-Gesetzt-2026',
        'password_confirmation' => 'Fremd-Gesetzt-2026',
    ])->assertSessionHasErrors('username');

    expect($nutzer->fresh()->password)->toBe($altesKennwort);
});

test('der Einladungslink gilt eine Woche, nicht eine Stunde', function () {
    // Eine Stunde reicht fuer "ich habe mein Kennwort vergessen". Eine
    // Einladung geht an jemanden, der vielleicht im Urlaub ist.
    expect(config('auth.passwords.einladung.expire'))->toBe(60 * 24 * 7)
        ->and(config('auth.passwords.users.expire'))->toBe(60);
});

test('ein Administrator kann die Einladung erneut schicken', function () {
    Notification::fake();
    alsBenutzerverwaltung();

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'wieder@example.test'])->save();

    $this->post(route('admin.user.einladung', $nutzer))
        ->assertRedirect(route('admin.user.edit', $nutzer));

    Notification::assertSentTo($nutzer->fresh(), Einladung::class);
});

test('ohne Adresse sagt das erneute Einladen, woran es liegt', function () {
    Notification::fake();
    alsBenutzerverwaltung();

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => null])->save();

    $this->post(route('admin.user.einladung', $nutzer))->assertSessionHasErrors('einladung');

    Notification::assertNothingSent();
});

test('ohne das Recht admin_user lädt niemand ein', function () {
    Notification::fake();
    $this->actingAs(userWithPermissions([]));

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'fremd@example.test'])->save();

    $this->post(route('admin.user.einladung', $nutzer))->assertForbidden();

    Notification::assertNothingSent();
});

test('das Einladungsformular ist gedrosselt', function () {
    $daten = [
        'token' => 'geraten',
        'username' => 'gibtesnicht',
        'password' => 'Irgendwas-Langes-2026',
        'password_confirmation' => 'Irgendwas-Langes-2026',
    ];

    foreach (range(1, 5) as $ignoriert) {
        $this->post(route('einladung.speichern'), $daten);
    }

    $this->post(route('einladung.speichern'), $daten)->assertStatus(429);
});

// --- Kennwort vergessen: dieselbe Maschinerie -------------------------------

test('Kennwort vergessen fragt nach dem Benutzernamen, nicht nach der E-Mail', function () {
    // Angemeldet wird sich in dieser Anwendung mit dem Benutzernamen - das
    // Formular fragte nach der E-Mail, der Controller nach dem Benutzernamen.
    // Der Weg war damit gar nicht gangbar.
    $this->get('/forgot-password')
        ->assertSee('name="username"', false)
        ->assertDontSee('name="email"', false);
});

test('Kennwort vergessen läuft nicht in einen Fehler, wenn keine Adresse hinterlegt ist', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => null])->save();

    $this->post('/forgot-password', ['username' => $nutzer->username])
        ->assertStatus(302)
        ->assertSessionHasErrors('username');
});

test('mit Adresse geht der Link hinaus', function () {
    Notification::fake();

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'vergessen@example.test'])->save();

    $this->post('/forgot-password', ['username' => $nutzer->username]);

    Notification::assertSentTo(
        $nutzer->fresh(),
        ResetPassword::class
    );
});

test('das Zurücksetzen-Formular fragt ebenfalls nach dem Benutzernamen', function () {
    // Der Broker schluesselt seine Token ueber die Adresse - ohne eine
    // hinterlegte laesst sich gar keiner anlegen. Genau deshalb faengt
    // PasswordResetLinkController diesen Fall vorher ab.
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['email' => 'zuruecksetzen@example.test'])->save();

    $token = Password::broker()->createToken($nutzer);

    $this->get('/reset-password/'.$token)
        ->assertSee('name="username"', false)
        ->assertSee('name="token"', false);
});

test('die Anmeldeseite bestätigt das gesetzte Kennwort', function () {
    // Ohne diese Rueckmeldung stand der Eingeladene vor einer leeren Maske und
    // wusste nicht, ob sein Kennwort ueberhaupt angekommen ist.
    $this->withSession(['status' => 'Kennwort gesetzt. Sie können sich jetzt anmelden.'])
        ->get('/login')
        ->assertSee('Kennwort gesetzt');
});
