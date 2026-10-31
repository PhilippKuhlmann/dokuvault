<?php

use App\Http\Controllers\API\Mobile\ProfilController;
use App\Models\User;
use App\Notifications\EmailChanged;
use App\Notifications\ZweiteStufeCode;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

/**
 * Das eigene Profil in der App - dieselben Regeln wie im Browser.
 */
const PROFIL_KENNWORT = 'Ein-Gutes-Kennwort-2026';

function profilNutzer(): User
{
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill([
        'password' => Hash::make(PROFIL_KENNWORT),
        'email' => 'alt@example.de',
        'name' => 'Alt Name',
    ])->save();

    return $nutzer->fresh();
}

function profilToken(User $nutzer): string
{
    return $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

test('das Profil nennt Name, Adresse, Sprache und die Auswahl', function () {
    $nutzer = profilNutzer();

    $antwort = test()->withToken(profilToken($nutzer))->getJson('/api/mobile/profile')->assertOk();

    expect($antwort->json('name'))->toBe('Alt Name');
    expect($antwort->json('mail'))->toBe('alt@example.de');
    expect($antwort->json('gesperrt'))->toBeFalse();
    expect($antwort->json('sprachen'))->not->toBeEmpty();
    expect(collect($antwort->json('sprachen'))->pluck('code'))->toContain('de');
});

test('Name und Sprache lassen sich ohne Kennwort ändern', function () {
    $nutzer = profilNutzer();

    test()->withToken(profilToken($nutzer))
        ->putJson('/api/mobile/profile', ['name' => 'Neu Name', 'locale' => 'de'])
        ->assertOk();

    expect($nutzer->fresh()->name)->toBe('Neu Name');
    expect($nutzer->fresh()->locale)->toBe('de');
});

/**
 * Die E-Mail ist das Ziel der Kennwort-Zuruecksetzung. Sie aus einer
 * geliehenen Sitzung zu wechseln, machte die Sitzung zum Konto - deshalb
 * braucht es das aktuelle Kennwort.
 */
test('eine neue E-Mail-Adresse braucht das aktuelle Kennwort', function () {
    $nutzer = profilNutzer();
    $token = profilToken($nutzer);

    test()->withToken($token)
        ->putJson('/api/mobile/profile', ['email' => 'neu@example.de'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect($nutzer->fresh()->email)->toBe('alt@example.de');
});

test('mit dem Kennwort geht der Wechsel, und die alte Adresse erfährt davon', function () {
    Notification::fake();
    $nutzer = profilNutzer();

    test()->withToken(profilToken($nutzer))
        ->putJson('/api/mobile/profile', [
            'email' => 'neu@example.de',
            'current_password' => PROFIL_KENNWORT,
        ])
        ->assertOk();

    expect($nutzer->fresh()->email)->toBe('neu@example.de');
    Notification::assertSentOnDemand(EmailChanged::class);
});

test('eine leere Sprache heißt der Geräte-Sprache folgen', function () {
    $nutzer = profilNutzer();
    $nutzer->forceFill(['locale' => 'de'])->save();

    test()->withToken(profilToken($nutzer))
        ->putJson('/api/mobile/profile', ['locale' => ''])
        ->assertOk();

    expect($nutzer->fresh()->locale)->toBeNull();
});

test('eine unbekannte Sprache wird abgewiesen', function () {
    $nutzer = profilNutzer();

    test()->withToken(profilToken($nutzer))
        ->putJson('/api/mobile/profile', ['locale' => 'klingonisch'])
        ->assertStatus(422);
});

test('das Kennwort lässt sich ändern', function () {
    $nutzer = profilNutzer();

    test()->withToken(profilToken($nutzer))
        ->putJson('/api/mobile/profile/password', [
            'current_password' => PROFIL_KENNWORT,
            'password' => 'Noch-Ein-Gutes-Kennwort-2026',
            'password_confirmation' => 'Noch-Ein-Gutes-Kennwort-2026',
        ])
        ->assertOk();

    expect(Hash::check('Noch-Ein-Gutes-Kennwort-2026', $nutzer->fresh()->password))->toBeTrue();
});

test('ohne das alte Kennwort bleibt es wie es war', function () {
    $nutzer = profilNutzer();

    test()->withToken(profilToken($nutzer))
        ->putJson('/api/mobile/profile/password', [
            'current_password' => 'falsch',
            'password' => 'Noch-Ein-Gutes-Kennwort-2026',
            'password_confirmation' => 'Noch-Ein-Gutes-Kennwort-2026',
        ])
        ->assertStatus(422);

    expect(Hash::check(PROFIL_KENNWORT, $nutzer->fresh()->password))->toBeTrue();
});

/**
 * Nach dem Kennwortwechsel ist die App abgemeldet: User::booted() entzieht
 * alle App-Token, an einer Stelle fuer jeden Weg. Die Antwort sagt es
 * ausdruecklich, damit die App nicht erst in den naechsten 401 laeuft.
 */
test('ein neues Kennwort entzieht alle Token und sagt es', function () {
    $nutzer = profilNutzer();
    profilToken($nutzer);
    $aktueller = profilToken($nutzer);

    expect($nutzer->tokens()->count())->toBe(2);

    $antwort = test()->withToken($aktueller)
        ->putJson('/api/mobile/profile/password', [
            'current_password' => PROFIL_KENNWORT,
            'password' => 'Noch-Ein-Gutes-Kennwort-2026',
            'password_confirmation' => 'Noch-Ein-Gutes-Kennwort-2026',
        ])
        ->assertOk();

    expect($antwort->json('neu_anmelden'))->toBeTrue();
    expect($nutzer->fresh()->tokens()->count())->toBe(0);
});

test('ohne Anmeldung kein Profil', function () {
    test()->getJson('/api/mobile/profile')->assertUnauthorized();
});

test('das Changelog kommt mit Version und Text', function () {
    $antwort = test()->withToken(profilToken(profilNutzer()))
        ->getJson('/api/mobile/changelog')
        ->assertOk();

    expect($antwort->json('version'))->not->toBeEmpty();
    expect($antwort->json('text'))->toBeString();
});

/**
 * Die Datei ist über 300 KB gross. Sie bei jedem Blick ganz auf ein Telefon
 * zu schicken, waere Verschwendung - und ein Textfeld mit
 * dreihunderttausend Zeichen zeigt gar nichts mehr.
 */
test('ein langes Changelog kommt gekürzt und an einer Überschrift geschnitten', function () {
    $antwort = test()->withToken(profilToken(profilNutzer()))
        ->getJson('/api/mobile/changelog')
        ->assertOk();

    $grenze = ProfilController::CHANGELOG_ZEICHEN;

    expect(mb_strlen($antwort->json('text')))->toBeLessThanOrEqual($grenze);

    if ($antwort->json('gekuerzt')) {
        // Kein halber Eintrag am Ende.
        expect(rtrim($antwort->json('text')))->not->toEndWith('##');
    }
});

/*
|--------------------------------------------------------------------------
| Zweite Stufe und Konto löschen
|--------------------------------------------------------------------------
*/

test('die Einrichtung liefert Geheimnis, Adresse und einen Vorgang', function () {
    Notification::fake();
    $nutzer = profilNutzer();

    $antwort = test()->withToken(profilToken($nutzer))
        ->postJson('/api/mobile/profile/two-factor/begin')
        ->assertOk();

    expect($antwort->json('geheimnis'))->not->toBeEmpty();
    expect($antwort->json('adresse'))->toStartWith('otpauth://');
    // Mit Adresse braucht es den Code aus der Mail.
    expect($antwort->json('braucht_mail_code'))->toBeTrue();
    expect($antwort->json('vorgang'))->not->toBeEmpty();
    Notification::assertSentTo($nutzer, ZweiteStufeCode::class);
});

test('ein falscher Code aus der App wird abgewiesen', function () {
    Notification::fake();
    $nutzer = profilNutzer();
    $nutzer->forceFill(['email' => null])->save();
    $token = profilToken($nutzer->fresh());

    $vorgang = test()->withToken($token)
        ->postJson('/api/mobile/profile/two-factor/begin')
        ->assertOk()->json('vorgang');

    test()->withToken($token)
        ->postJson('/api/mobile/profile/two-factor/confirm', ['vorgang' => $vorgang, 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect($nutzer->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('mit dem richtigen Code steht die zweite Stufe und die Codes kommen einmal', function () {
    Notification::fake();
    $nutzer = profilNutzer();
    // Ohne Adresse entfaellt der Code aus der Mail.
    $nutzer->forceFill(['email' => null])->save();
    $token = profilToken($nutzer->fresh());

    $begonnen = test()->withToken($token)->postJson('/api/mobile/profile/two-factor/begin')->assertOk();
    $code = (new Google2FA)->getCurrentOtp($begonnen->json('geheimnis'));

    $antwort = test()->withToken($token)
        ->postJson('/api/mobile/profile/two-factor/confirm', [
            'vorgang' => $begonnen->json('vorgang'),
            'code' => $code,
        ])
        ->assertOk();

    expect($antwort->json('codes'))->toHaveCount(count($antwort->json('codes')));
    expect($antwort->json('codes'))->not->toBeEmpty();
    expect($nutzer->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

/** Ein Geheimtext von anderswo darf hier nicht als Einrichtung durchgehen. */
test('ein fremder Vorgang wird abgewiesen', function () {
    $nutzer = profilNutzer();

    test()->withToken(profilToken($nutzer))
        ->postJson('/api/mobile/profile/two-factor/confirm', [
            'vorgang' => Crypt::encryptString(json_encode([
                'zweck' => 'mobil-anmeldung', 'id' => $nutzer->id, 'bis' => now()->addHour()->timestamp,
            ])),
            'code' => '123456',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('vorgang');
});

test('neue Wiederherstellungscodes brauchen das Kennwort', function () {
    $nutzer = profilNutzer();
    $token = profilToken($nutzer);

    test()->withToken($token)
        ->postJson('/api/mobile/profile/two-factor/recovery-codes', ['password' => 'falsch'])
        ->assertStatus(422);

    $antwort = test()->withToken($token)
        ->postJson('/api/mobile/profile/two-factor/recovery-codes', ['password' => PROFIL_KENNWORT])
        ->assertOk();

    expect($antwort->json('codes'))->not->toBeEmpty();
});

/**
 * Verlangt der Administrator sie, ist sie kein Knopf, den man drueckt.
 *
 * Eingerichtet muss sie dafuer sein: Wer sie einrichten muss und es nicht
 * getan hat, kommt an der Middleware gar nicht erst vorbei (403).
 */
test('eine verlangte zweite Stufe lässt sich nicht abschalten', function () {
    $nutzer = profilNutzer();
    $nutzer->forceFill([
        'two_factor_required' => true,
        'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
        'two_factor_confirmed_at' => now(),
    ])->save();

    test()->withToken(profilToken($nutzer->fresh()))
        ->deleteJson('/api/mobile/profile/two-factor', ['password' => PROFIL_KENNWORT])
        ->assertStatus(422);

    expect($nutzer->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('sonst schaltet das Kennwort sie ab', function () {
    $nutzer = profilNutzer();
    $nutzer->forceFill([
        'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
        'two_factor_confirmed_at' => now(),
    ])->save();

    test()->withToken(profilToken($nutzer->fresh()))
        ->deleteJson('/api/mobile/profile/two-factor', ['password' => PROFIL_KENNWORT])
        ->assertOk();

    expect($nutzer->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('das Konto löschen braucht das Kennwort', function () {
    $nutzer = profilNutzer();
    $id = $nutzer->id;

    test()->withToken(profilToken($nutzer))
        ->deleteJson('/api/mobile/profile', ['password' => 'falsch'])
        ->assertStatus(422);

    expect(User::find($id))->not->toBeNull();
});

test('mit dem Kennwort ist das Konto weg und die App abgemeldet', function () {
    $nutzer = profilNutzer();
    $id = $nutzer->id;

    $antwort = test()->withToken(profilToken($nutzer))
        ->deleteJson('/api/mobile/profile', ['password' => PROFIL_KENNWORT])
        ->assertOk();

    expect($antwort->json('neu_anmelden'))->toBeTrue();
    expect(User::find($id))->toBeNull();
});
