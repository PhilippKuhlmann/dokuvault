<?php

use App\Models\User;
use App\Support\Zeit;
use App\Support\ZweiteStufe;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/*
 * Gesperrt heisst an drei Stellen gesperrt. Jede einzeln geprueft, weil jede
 * fuer sich ausfallen kann und der Ausfall nicht auffaellt: Die Anmeldemaske
 * saehe weiter richtig aus, waehrend die alte Sitzung oder ein Script des
 * Gesperrten unveraendert weiterlaeuft.
 */

function gesperrterNutzer(string $kennwort = 'Ein-Gutes-Kennwort-2026'): User
{
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill([
        'password' => Hash::make($kennwort),
        'deactivated_at' => now(),
    ])->save();

    return $nutzer->fresh();
}

test('ein gesperrter Zugang kommt nicht durch die Anmeldung', function () {
    $nutzer = gesperrterNutzer();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Ein-Gutes-Kennwort-2026',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('die Meldung nennt den Grund statt falscher Zugangsdaten', function () {
    $nutzer = gesperrterNutzer();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Ein-Gutes-Kennwort-2026',
    ]);

    // Wer das richtige Kennwort hat, weiss ohnehin, dass es den Zugang gibt.
    // "Zugangsdaten falsch" schickte ihn nur los, ein Kennwort zu suchen, das
    // er gar nicht verloren hat.
    expect(session('errors')->get('username')[0])
        ->toContain('deaktiviert');
});

test('ein falsches Kennwort verraet die Sperre nicht', function () {
    $nutzer = gesperrterNutzer();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Falsch-Geraten-2026',
    ]);

    expect(session('errors')->get('username')[0])
        ->not->toContain('deaktiviert');
});

test('die laufende Sitzung endet beim naechsten Aufruf', function () {
    $nutzer = userWithPermissions([]);

    $this->actingAs($nutzer);
    $this->get(route('profile.edit'))->assertOk();

    // Gesperrt, waehrend er angemeldet ist - genau der Fall, fuer den es die
    // Middleware gibt.
    $nutzer->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('ein API-Token des Gesperrten wird abgewiesen', function () {
    $nutzer = gesperrterNutzer();

    Sanctum::actingAs($nutzer);

    $this->getJson('/api/user')->assertForbidden();
});

test('ein aktiver Zugang bleibt von alldem unberuehrt', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Ein-Gutes-Kennwort-2026')])->save();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Ein-Gutes-Kennwort-2026',
    ]);
    $this->assertAuthenticatedAs($nutzer->fresh());

    $this->get(route('profile.edit'))->assertOk();

    Sanctum::actingAs($nutzer->fresh());
    $this->getJson('/api/user')->assertOk();
});

// --- Bedienung -------------------------------------------------------------

test('ein Administrator sperrt und entsperrt ueber das Formular', function () {
    $verwalter = userWithPermissions(['admin_user']);
    $nutzer = userWithPermissions([]);

    $felder = fn (array $dazu = []) => array_merge([
        'name' => $nutzer->name,
        'username' => $nutzer->username,
        'role_id' => $nutzer->role_id,
    ], $dazu);

    $this->actingAs($verwalter)
        ->patch(route('admin.user.update', $nutzer), $felder(['deactivated' => '1']))
        ->assertRedirect();

    expect($nutzer->fresh()->istDeaktiviert())->toBeTrue();

    $this->actingAs($verwalter)
        ->patch(route('admin.user.update', $nutzer), $felder())
        ->assertRedirect();

    expect($nutzer->fresh()->istDeaktiviert())->toBeFalse()
        ->and($nutzer->fresh()->deactivated_at)->toBeNull();
});

test('der Zeitpunkt faengt beim zweiten Speichern nicht von vorne an', function () {
    $verwalter = userWithPermissions(['admin_user']);
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['deactivated_at' => now()->subDays(3)])->save();

    $vorher = $nutzer->fresh()->deactivated_at;

    $this->actingAs($verwalter)->patch(route('admin.user.update', $nutzer), [
        'name' => $nutzer->name,
        'username' => $nutzer->username,
        'role_id' => $nutzer->role_id,
        'deactivated' => '1',
    ]);

    // "seit wann" ist die Frage, die spaeter jemand stellt.
    expect($nutzer->fresh()->deactivated_at->timestamp)->toBe($vorher->timestamp);
});

test('den eigenen Zugang kann niemand sperren', function () {
    $verwalter = userWithPermissions(['admin_user']);

    $this->actingAs($verwalter)->patch(route('admin.user.update', $verwalter), [
        'name' => $verwalter->name,
        'username' => $verwalter->username,
        'role_id' => $verwalter->role_id,
        'deactivated' => '1',
    ])->assertSessionHasErrors('deactivated');

    // Sonst faehrt man sich selbst aus: Die Sitzung endet beim naechsten
    // Aufruf, und anmelden geht dann auch nicht mehr.
    expect($verwalter->fresh()->istDeaktiviert())->toBeFalse();
});

test('die Benutzerliste zeigt den gesperrten Zugang als solchen', function () {
    $gesperrt = gesperrterNutzer();

    $this->actingAs(userWithPermissions(['admin_user']))
        ->get(route('admin.user.index'))
        ->assertOk()
        ->assertSee('aria-label="'.__('Deaktiviert').'"', false)
        ->assertSee('aria-label="'.__('Aktiv').'"', false);

    expect($gesperrt->istDeaktiviert())->toBeTrue();
});

// --- Was der Pruefdurchlauf gefunden hat ------------------------------------

/*
 * Der abgewiesene Versuch darf nicht als Anmeldung im Protokoll stehen.
 *
 * Die Pruefung sass zuerst hinter Auth::attempt(). attempt() meldet bei
 * richtigem Kennwort an und feuert dabei das Login-Ereignis; daran haengt
 * AnmeldungProtokollieren. Das nachgeschobene logout() nahm die Sitzung
 * zurueck, den Protokolleintrag und last_login_at aber nicht - jeder Versuch
 * eines Gesperrten stand als erfolgreiche Anmeldung da. Ausgerechnet dieses
 * Protokoll ist der Grund, einen Zugang zu sperren statt ihn zu loeschen.
 */
test('der abgewiesene Versuch steht nicht als Anmeldung im Protokoll', function () {
    $nutzer = gesperrterNutzer();
    $nutzer->forceFill(['last_login_at' => null, 'last_login_ip' => null])->saveQuietly();

    $vorher = Activity::where('event', 'anmeldung')->count();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Ein-Gutes-Kennwort-2026',
    ]);

    expect(Activity::where('event', 'anmeldung')->count())->toBe($vorher)
        ->and($nutzer->fresh()->last_login_at)->toBeNull()
        ->and($nutzer->fresh()->last_login_ip)->toBeNull();
});

/*
 * Der zweite Eingang. Wer zwischen Kennwort und Einmalcode gesperrt wird, kam
 * bisher durch: Auth::login() in TwoFactorChallengeController fragte nicht
 * nach. Dass ZugangAktiv ihn beim naechsten Aufruf hinauswirft, ist kein
 * Ersatz - bis dahin haette er einen gueltigen "angemeldet bleiben"-Cookie,
 * einen verbrauchten Wiederherstellungscode und einen Eintrag "Angemeldet".
 */
test('die zweite Stufe laesst einen zwischenzeitlich Gesperrten nicht herein', function () {
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

    // Gesperrt, waehrend er vor der Codemaske sitzt.
    $nutzer->forceFill(['deactivated_at' => now()])->save();

    $codesVorher = count($nutzer->fresh()->two_factor_recovery_codes);
    $protokollVorher = Activity::where('event', 'anmeldung')->count();

    $this->post(route('two-factor.login'), ['code' => gueltigerCode($geheimnis)])
        ->assertRedirect(route('login'));

    $this->assertGuest();

    // Kein Code verbraucht, kein Eintrag "Angemeldet".
    expect(count($nutzer->fresh()->two_factor_recovery_codes))->toBe($codesVorher)
        ->and(Activity::where('event', 'anmeldung')->count())->toBe($protokollVorher);
});

test('die Codemaske selbst steht einem Gesperrten nicht mehr offen', function () {
    $geheimnis = app(ZweiteStufe::class)->geheimnisErzeugen();

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill([
        'password' => Hash::make('Ein-Gutes-Kennwort-2026'),
        'two_factor_secret' => $geheimnis,
        'two_factor_recovery_codes' => app(ZweiteStufe::class)->wiederherstellungscodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    $nutzer->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
});

/*
 * Nicht nur /api/user: Dort steht nur der eigene Datensatz. Die Daten liegen
 * in der grossen Gruppe - faellt 'aktiv' dort weg, blieben die Tests sonst
 * gruen, waehrend das Skript eines Gesperrten weiterliest.
 */
test('auch die Datenrouten der API weisen ein Token des Gesperrten ab', function () {
    $nutzer = gesperrterNutzer();

    Sanctum::actingAs($nutzer);

    $this->getJson('/api/customers')->assertForbidden();
});

/*
 * Livewire laeuft ueber /livewire/update und nicht ueber die Route der Seite.
 * Steht ZugangAktiv nicht in der web-Gruppe, bleibt dieser Weg lautlos offen,
 * waehrend gewoehnliche Seitenaufrufe richtig abgewiesen werden.
 */
test('auch ein Livewire-Aufruf kommt nicht mehr durch', function () {
    // Der Pfad aus der Routentabelle, nicht geraten: Livewire 4 haengt einen
    // Praefix aus dem Anwendungsschluessel davor ("livewire-3d760eac/update").
    // Fest verdrahtet liefe der Test in einen 404 - und waere dann gruen,
    // ohne je die Middleware erreicht zu haben.
    $pfad = collect(Route::getRoutes())
        ->first(fn ($r) => str_contains($r->uri(), 'livewire') && str_ends_with($r->uri(), '/update'))
        ?->uri();

    expect($pfad)->not->toBeNull('Livewires Update-Route nicht gefunden - Pfad geaendert?');

    $nutzer = userWithPermissions([]);
    $this->actingAs($nutzer);

    $nutzer->forceFill(['deactivated_at' => now()])->save();

    $this->post('/'.$pfad, [], ['X-Livewire' => 'true']);

    // Der Aufruf laeuft nicht ueber die Route der Seite, sondern ueber diese
    // eine. Stuende ZugangAktiv nicht in der web-Gruppe, bliebe der Weg
    // lautlos offen, waehrend gewoehnliche Seitenaufrufe abgewiesen werden.
    $this->assertGuest();
});

test('das Bearbeiten-Formular zeigt, seit wann gesperrt ist', function () {
    $gesperrt = gesperrterNutzer();
    $gesperrt->forceFill(['deactivated_at' => now()->subDays(4)])->save();

    $this->actingAs(userWithPermissions(['admin_user']))
        ->get(route('admin.user.edit', $gesperrt))
        ->assertOk()
        ->assertSee(__('gesperrt seit'))
        ->assertSee(Zeit::anzeigen($gesperrt->fresh()->deactivated_at, 'd.m.Y H:i'));
});
