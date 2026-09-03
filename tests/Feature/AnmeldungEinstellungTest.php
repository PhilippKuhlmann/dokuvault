<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Requests\Auth\LoginRequest;
use App\Livewire\AdminSicherheit;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function sicherheitsAdmin(): User
{
    $rolle = Role::factory()->create(['id' => Role::IS_ADMIN]);

    return User::factory()->create(['role_id' => $rolle->id]);
}

test('ohne Einstellung gilt die Konfiguration', function () {
    expect(Setting::anmeldungVersuche())->toBe(config('custom.anmeldung.versuche'))
        ->and(Setting::anmeldungSperreMinuten())->toBe(config('custom.anmeldung.sperre_minuten'))
        ->and(Setting::anmeldungHerkunft())->toBe(config('custom.anmeldung.versuche_je_herkunft'))
        ->and(Setting::sitzungMinuten())->toBe((int) config('session.lifetime'))
        ->and(Setting::rememberTage())->toBe((int) config('custom.remember_days'));
});

/*
|--------------------------------------------------------------------------
| Die Wirkung
|--------------------------------------------------------------------------
*/

test('die eingestellte Zahl entscheidet, wann gesperrt wird', function () {
    Setting::setzen(Setting::ANMELDUNG_VERSUCHE, 2);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Richtiges-Kennwort-2026')])->save();

    // Zwei Fehlversuche - der dritte Zugriff ist schon gesperrt, auch mit dem
    // richtigen Kennwort.
    foreach (range(1, 2) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    expect(session('errors')?->first('username'))->toContain('Zu viele');
    $this->assertGuest();
});

test('eine höhere Zahl lässt den vierten Versuch noch durch', function () {
    // Die Gegenprobe: Mit der Vorgabe von fuenf waere hier gesperrt worden,
    // wenn die Einstellung nichts bewirkte.
    Setting::setzen(Setting::ANMELDUNG_VERSUCHE, 10);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Richtiges-Kennwort-2026')])->save();

    foreach (range(1, 6) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    $this->assertAuthenticated();
});

test('Anmeldung und zweite Stufe folgen derselben Quelle', function () {
    // Die Invariante: Beide Zahlen standen einzeln im Code, die zweite mit dem
    // Kommentar "wie bei der Anmeldung selbst". Ein Kommentar haelt zwei
    // Zahlen nicht zusammen.
    Setting::setzen(Setting::ANMELDUNG_VERSUCHE, 3);
    Setting::setzen(Setting::ANMELDUNG_SPERRE, 7);

    $login = new ReflectionClass(LoginRequest::class);
    $zweite = new ReflectionClass(TwoFactorChallengeController::class);

    // Keine eigenen Zahlen mehr - weder hier noch dort.
    expect($login->getConstants())->not->toHaveKey('VERSUCHE_JE_KONTO')
        ->and($login->getConstants())->not->toHaveKey('SPERRE')
        ->and($zweite->getConstants())->not->toHaveKey('VERSUCHE')
        ->and($zweite->getConstants())->not->toHaveKey('SPERRE');

    expect(Setting::anmeldungVersuche())->toBe(3)
        ->and(Setting::anmeldungSperreSekunden())->toBe(7 * 60);
});

test('die zweite Stufe sperrt nach der eingestellten Zahl', function () {
    Setting::setzen(Setting::ANMELDUNG_VERSUCHE, 2);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill([
        'password' => Hash::make('Richtiges-Kennwort-2026'),
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
    ]);

    foreach (range(1, 2) as $ignoriert) {
        $this->post('/two-factor-challenge', ['code' => '000000']);
    }

    $this->post('/two-factor-challenge', ['code' => '000000']);

    expect(session('errors')?->first('code'))->toContain('Zu viele');
});

test('die Sitzungsdauer aus den Einstellungen schlägt die .env', function () {
    Setting::setzen(Setting::SITZUNG_MINUTEN, 45);

    // Der Provider legt sie beim Hochfahren in die Konfiguration - StartSession
    // liest sie erst danach, als Middleware.
    $this->app->register(AppServiceProvider::class, true);

    expect(Setting::sitzungMinuten())->toBe(45)
        ->and((int) config('session.lifetime'))->toBe(45);
});

test('„Angemeldet bleiben“ folgt der eingestellten Zahl', function () {
    Setting::setzen(Setting::REMEMBER_TAGE, 7);

    $this->app->register(AppServiceProvider::class, true);

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Richtiges-Kennwort-2026')])->save();

    $antwort = $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Richtiges-Kennwort-2026',
        'remember' => 'on',
    ]);

    // Am Cookie selbst, nicht an einer Zahl im Speicher: Das Cookie ist das,
    // was der Browser behaelt, und nur seine Frist entscheidet.
    $keks = collect($antwort->headers->getCookies())
        ->first(fn ($k) => str_starts_with($k->getName(), 'remember_web'));

    expect($keks)->not->toBeNull()
        ->and($keks->getExpiresTime())->toBeGreaterThan(now()->addDays(6)->timestamp)
        ->and($keks->getExpiresTime())->toBeLessThan(now()->addDays(8)->timestamp);
});

test('ohne lesbare Einstellungen bleibt die Frist bei 30 Tagen, nicht bei 400', function () {
    // Der Provider laeuft auch dort, wo es die Tabelle noch nicht gibt - beim
    // ersten "composer install", vor der ersten Migration. Faellt das Setzen
    // dann aus, gilt Laravels Vorgabe von 400 Tagen, und ein gestohlenes
    // Notebook waere ein Dauerzugang. Deshalb steht der Aufruf ausserhalb des
    // try; dieser Test haelt ihn dort.
    Schema::drop('settings');

    $this->app->register(AppServiceProvider::class, true);

    $dauer = (new ReflectionMethod(Auth::guard('web'), 'getRememberDuration'));
    $dauer->setAccessible(true);

    expect($dauer->invoke(Auth::guard('web')))->toBe(60 * 24 * (int) config('custom.remember_days'));
});

/*
|--------------------------------------------------------------------------
| Die Einstellungsseite
|--------------------------------------------------------------------------
*/

test('die Seite speichert beim Ändern, ohne Knopf', function () {
    $this->actingAs(sicherheitsAdmin());

    Livewire::test(AdminSicherheit::class)
        ->set('versuche', 3)
        ->set('sperre', 30)
        ->set('herkunft', 50)
        ->set('sitzungMinuten', 240)
        ->set('rememberTage', 14)
        ->set('sitzungSchliessen', true)
        ->assertHasNoErrors();

    expect(Setting::anmeldungVersuche())->toBe(3)
        ->and(Setting::anmeldungSperreMinuten())->toBe(30)
        ->and(Setting::anmeldungHerkunft())->toBe(50)
        ->and(Setting::sitzungMinuten())->toBe(240)
        ->and(Setting::rememberTage())->toBe(14)
        ->and(Setting::sitzungBeimSchliessen())->toBeTrue();
});

test('null Versuche wäre eine Anmeldung, die niemanden durchlässt', function () {
    $this->actingAs(sicherheitsAdmin());

    Livewire::test(AdminSicherheit::class)
        ->set('versuche', 0)
        ->assertHasErrors(['versuche']);

    expect(Setting::anmeldungVersuche())->toBe(config('custom.anmeldung.versuche'));
});

test('eine Sitzung unter fünf Minuten wird abgewiesen', function () {
    $this->actingAs(sicherheitsAdmin());

    // Kuerzer meldet die Anwendung jemanden waehrend des Tippens ab.
    Livewire::test(AdminSicherheit::class)
        ->set('sitzungMinuten', 1)
        ->assertHasErrors(['sitzungMinuten']);
});

test('ohne das Recht kommt niemand auf die Seite', function () {
    $this->actingAs(userWithPermissions([]));

    $this->get(route('admin.security.index'))->assertForbidden();
});
