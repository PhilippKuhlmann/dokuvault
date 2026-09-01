<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorController;
use App\Support\ZweiteStufe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

function stufe(): ZweiteStufe
{
    return app(ZweiteStufe::class);
}

/** Ein gültiger Code zum gegebenen Geheimnis - so, wie ihn die App zeigen würde. */
function gueltigerCode(string $geheimnis): string
{
    return (new Google2FA)->getCurrentOtp($geheimnis);
}

function nutzerMitZweiterStufe(string $kennwort = 'Ein-Gutes-Kennwort-2026'): array
{
    $geheimnis = stufe()->geheimnisErzeugen();
    $codes = stufe()->wiederherstellungscodes();

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill([
        'password' => Hash::make($kennwort),
        'two_factor_secret' => $geheimnis,
        'two_factor_recovery_codes' => $codes,
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$nutzer->fresh(), $geheimnis, $codes];
}

// --- Einrichtung im Profil --------------------------------------------------

test('die Einrichtung zeigt QR-Code und Geheimnis zum Abtippen', function () {
    $nutzer = userWithPermissions([]);

    $this->actingAs($nutzer)->post(route('two-factor.begin'));

    $geheimnis = session(TwoFactorController::IN_ARBEIT);
    expect($geheimnis)->not->toBeNull();

    $antwort = $this->get(route('profile.edit'));

    $antwort->assertStatus(200)
        ->assertSee('<svg', false)          // der QR-Code
        ->assertSee($geheimnis);            // und dasselbe zum Abtippen

    // Noch ist nichts scharf: ein Geheimnis, das die App vielleicht nie
    // uebernommen hat, darf niemanden aussperren.
    expect($nutzer->fresh()->hatZweiteStufe())->toBeFalse();
});

test('erst ein stimmender Code schaltet sie ein', function () {
    $nutzer = userWithPermissions([]);
    $this->actingAs($nutzer)->post(route('two-factor.begin'));
    $geheimnis = session(TwoFactorController::IN_ARBEIT);

    $this->post(route('two-factor.confirm'), ['code' => gueltigerCode($geheimnis)])
        ->assertRedirect();

    expect($nutzer->fresh()->hatZweiteStufe())->toBeTrue()
        ->and($nutzer->fresh()->two_factor_secret)->toBe($geheimnis);
});

test('ein falscher Code schaltet nichts ein', function () {
    $nutzer = userWithPermissions([]);
    $this->actingAs($nutzer)->post(route('two-factor.begin'));

    $this->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code', null, 'zweiteStufe');

    expect($nutzer->fresh()->hatZweiteStufe())->toBeFalse();
});

test('beim Einschalten gibt es Wiederherstellungscodes - genau einmal', function () {
    $nutzer = userWithPermissions([]);
    $this->actingAs($nutzer)->post(route('two-factor.begin'));

    $this->post(route('two-factor.confirm'), ['code' => gueltigerCode(session(TwoFactorController::IN_ARBEIT))]);

    expect(session('zweite-stufe-codes'))->toHaveCount(ZweiteStufe::CODES);

    // Beim naechsten Aufruf der Seite stehen sie nicht mehr da.
    $this->get(route('profile.edit'))->assertDontSee(session('zweite-stufe-codes')[0] ?? 'nichts');
});

test('Abschalten verlangt das Kennwort', function () {
    [$nutzer] = nutzerMitZweiterStufe();

    $this->actingAs($nutzer)->delete(route('two-factor.destroy'), ['password' => 'falsch'])
        ->assertSessionHasErrors('password', null, 'zweiteStufeAus');

    expect($nutzer->fresh()->hatZweiteStufe())->toBeTrue();

    $this->delete(route('two-factor.destroy'), ['password' => 'Ein-Gutes-Kennwort-2026'])
        ->assertRedirect();

    expect($nutzer->fresh()->hatZweiteStufe())->toBeFalse();
});

// --- Anmeldung --------------------------------------------------------------

test('mit zweiter Stufe reicht das Kennwort nicht', function () {
    [$nutzer] = nutzerMitZweiterStufe();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026'])
        ->assertRedirect(route('two-factor.login'));

    // Zwischen Kennwort und Code ist niemand angemeldet.
    $this->assertGuest();
    expect(session(TwoFactorChallengeController::WARTET))->toBe($nutzer->id);
});

test('der Einmalcode schliesst die Anmeldung ab', function () {
    [$nutzer, $geheimnis] = nutzerMitZweiterStufe();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);
    $this->post(route('two-factor.login'), ['code' => gueltigerCode($geheimnis)]);

    $this->assertAuthenticatedAs($nutzer);
    expect(session(TwoFactorChallengeController::WARTET))->toBeNull();
});

test('ein falscher Einmalcode meldet niemanden an', function () {
    [$nutzer] = nutzerMitZweiterStufe();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);
    $this->post(route('two-factor.login'), ['code' => '123456'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('ein Wiederherstellungscode geht auch - aber nur einmal', function () {
    [$nutzer, , $codes] = nutzerMitZweiterStufe();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);
    $this->post(route('two-factor.login'), ['code' => $codes[0]]);

    $this->assertAuthenticatedAs($nutzer);
    expect($nutzer->fresh()->two_factor_recovery_codes)->toHaveCount(ZweiteStufe::CODES - 1);

    // Derselbe Zettel ein zweites Mal: nein.
    $this->post('/logout');
    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);
    $this->post(route('two-factor.login'), ['code' => $codes[0]])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('ohne begonnene Anmeldung führt die Codeabfrage zurück zur Anmeldung', function () {
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    $this->post(route('two-factor.login'), ['code' => '123456'])->assertRedirect(route('login'));
});

test('der Einmalcode lässt sich nicht durchprobieren', function () {
    [$nutzer, $geheimnis] = nutzerMitZweiterStufe();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    foreach (range(1, 5) as $ignoriert) {
        $this->post(route('two-factor.login'), ['code' => '000000']);
    }

    // Sechs Ziffern sind eine Million Moeglichkeiten - ohne Bremse in Ruhe
    // durchzuprobieren. Selbst der richtige Code wird jetzt abgewiesen.
    $this->post(route('two-factor.login'), ['code' => gueltigerCode($geheimnis)]);

    expect(session('errors')->first('code'))->toContain('Zu viele');
    $this->assertGuest();
});

test('ohne zweite Stufe bleibt die Anmeldung wie sie war', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Ein-Gutes-Kennwort-2026')])->save();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    $this->assertAuthenticatedAs($nutzer);
});

// --- Aufbewahrung -----------------------------------------------------------

test('Geheimnis und Wiederherstellungscodes liegen verschlüsselt', function () {
    [$nutzer, $geheimnis, $codes] = nutzerMitZweiterStufe();

    $roh = DB::table('users')->where('id', $nutzer->id)->first();

    expect($roh->two_factor_secret)->not->toBe($geheimnis)
        ->and($roh->two_factor_recovery_codes)->not->toContain($codes[0])
        ->and($nutzer->fresh()->two_factor_secret)->toBe($geheimnis);
});

test('das Geheimnis kann nicht ins Protokoll geraten', function () {
    // Wer das Geheimnis liest, kann jeden Code erzeugen. Im Aktivitäts-
    // protokoll hätte es dieselbe Wirkung wie ein Kennwort im Klartext.
    expect(config('custom.secret_columns'))
        ->toContain('two_factor_secret')
        ->toContain('two_factor_recovery_codes');

    // Aber es ist kein Kennwort - im Kennwortverlauf hätte es nichts zu suchen.
    expect(config('custom.non_password_secrets'))
        ->toContain('two_factor_secret')
        ->toContain('two_factor_recovery_codes');
});

// --- Verlorenes Telefon -----------------------------------------------------

test('ein Administrator kann die zweite Stufe zurücksetzen', function () {
    [$nutzer] = nutzerMitZweiterStufe();
    $admin = userWithPermissions(['admin_user']);

    $this->actingAs($admin)
        ->delete(route('admin.user.two-factor', $nutzer))
        ->assertRedirect(route('admin.user.edit', $nutzer));

    expect($nutzer->fresh()->hatZweiteStufe())->toBeFalse();

    // Und es steht im Protokoll, wer es war.
    // Mit Ereignisnamen: Ohne einen steht in der Protokollspalte nur "—", und
    // filtern liesse sich der Eintrag auch nicht.
    expect(Activity::latest('id')->first())
        ->description->toBe('Zweite Stufe zurückgesetzt')
        ->event->toBe('zweite_stufe');
});

test('ohne das Recht admin_user geht das nicht', function () {
    [$nutzer] = nutzerMitZweiterStufe();

    $this->actingAs(userWithPermissions([]))
        ->delete(route('admin.user.two-factor', $nutzer))
        ->assertForbidden();

    expect($nutzer->fresh()->hatZweiteStufe())->toBeTrue();
});

test('ein fremder Nutzer kann die zweite Stufe eines anderen nicht abschalten', function () {
    // Die Profilroute kennt nur den eigenen Zugang - es gibt gar keinen
    // Parameter, über den ein fremder hineinkäme.
    [$opfer] = nutzerMitZweiterStufe();
    [$angreifer] = nutzerMitZweiterStufe();

    $this->actingAs($angreifer)
        ->delete(route('two-factor.destroy'), ['password' => 'Ein-Gutes-Kennwort-2026']);

    expect($opfer->fresh()->hatZweiteStufe())->toBeTrue()
        ->and($angreifer->fresh()->hatZweiteStufe())->toBeFalse();
});

// --- Der Administrator verlangt sie ----------------------------------------

test('wer sie einrichten muss, kommt nur bis zum eigenen Profil', function () {
    $nutzer = userWithPermissions(['server_viewAny']);
    $nutzer->forceFill(['two_factor_required' => true])->save();

    $this->actingAs($nutzer);

    // Mit Sprungmarke auf den Abschnitt, um den es geht.
    $ziel = route('profile.edit').'#zweite-stufe';

    $this->get(route('customer.search'))->assertRedirect($ziel);
    $this->get(route('admin.dashboard'))->assertRedirect($ziel);

    // Das Profil selbst muss offen bleiben - dort steht der Weg hinaus.
    $this->get(route('profile.edit'))->assertStatus(200);
});

test('nach der Einrichtung ist der Weg wieder frei', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['two_factor_required' => true])->save();

    $this->actingAs($nutzer)->post(route('two-factor.begin'));
    $this->post(route('two-factor.confirm'), ['code' => gueltigerCode(session(TwoFactorController::IN_ARBEIT))]);

    expect($nutzer->fresh()->mussZweiteStufeEinrichten())->toBeFalse();

    $this->get(route('customer.search'))->assertStatus(200);
});

test('verlangt heisst: der Benutzer kann sie nicht abschalten', function () {
    [$nutzer] = nutzerMitZweiterStufe();
    $nutzer->forceFill(['two_factor_required' => true])->save();

    $this->actingAs($nutzer->fresh())
        ->delete(route('two-factor.destroy'), ['password' => 'Ein-Gutes-Kennwort-2026'])
        ->assertSessionHasErrors('password', null, 'zweiteStufeAus');

    expect($nutzer->fresh()->hatZweiteStufe())->toBeTrue();
});

test('Livewire bleibt offen, damit das Profil bedienbar ist', function () {
    // Die Seiten dahinter sind ohnehin gesperrt - man kommt nicht hin. Aber
    // Sprachumschaltung und Formulare auf dem Profil laufen darueber.
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['two_factor_required' => true])->save();

    $this->actingAs($nutzer)->post('/locale/en')->assertRedirect();
});

test('ein Administrator setzt und löst die Pflicht über das Formular', function () {
    $admin = userWithPermissions(['admin_user']);
    $kunde = userWithPermissions([]);

    $daten = [
        'name' => $kunde->name,
        'username' => $kunde->username,
        'email' => null,
        'role_id' => $kunde->role_id,
        'customer_id' => null,
        'two_factor_required' => '1',
    ];

    $this->actingAs($admin)->patch(route('admin.user.update', $kunde), $daten);
    expect($kunde->fresh()->two_factor_required)->toBeTrue();

    // Der Haken fehlt, wenn er nicht gesetzt ist - der Browser schickt ihn gar
    // nicht mit. Ohne prepareForValidation liesse sich die Pflicht nie wieder
    // loeschen.
    unset($daten['two_factor_required']);

    $this->patch(route('admin.user.update', $kunde), $daten);
    expect($kunde->fresh()->two_factor_required)->toBeFalse();
});

test('auf der Demo wird niemand gezwungen', function () {
    // Dort teilen sich alle Besucher einen Zugang: Der erste, der eine App
    // verbindet, sperrt alle uebrigen aus.
    config(['app.demo' => true]);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['two_factor_required' => true])->save();

    config(['custom.demo_protected_users' => [$nutzer->username]]);

    expect($nutzer->fresh()->mussZweiteStufeEinrichten())->toBeFalse();

    $this->actingAs($nutzer->fresh())->get(route('customer.search'))->assertStatus(200);
});
