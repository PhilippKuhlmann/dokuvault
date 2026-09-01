<?php

use App\Livewire\AdminAllgemein;
use App\Models\Setting;
use App\Support\Zeit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('ohne Einstellung gilt die Konfiguration', function () {
    expect(Zeit::zone())->toBe(config('app.timezone'));
});

test('die eingestellte Zone verschiebt die Anzeige', function () {
    Setting::setzen(Setting::APP_TIMEZONE, 'Europe/Berlin');

    // 16:00 UTC ist im Sommer 18:00 in Berlin.
    $zeitpunkt = Carbon::parse('2026-07-01 16:00:00', 'UTC');

    expect(Zeit::anzeigen($zeitpunkt))->toBe('01.07.2026 18:00');
});

test('gespeichert wird weiter in UTC', function () {
    // Der Kern der Sache: Würde app.timezone umgestellt, schriebe die
    // Anwendung ab dann lokale Zeiten in dieselben Spalten, in denen bereits
    // UTC steht - zwei Zeitzonen in einer Spalte, ohne Merkmal, welche Zeile
    // welche ist.
    Setting::setzen(Setting::APP_TIMEZONE, 'Europe/Berlin');

    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['last_login_at' => Carbon::parse('2026-07-01 16:00:00', 'UTC')])->save();

    $roh = DB::table('users')->where('id', $nutzer->id)->value('last_login_at');

    expect($roh)->toStartWith('2026-07-01 16:00')
        ->and(Zeit::anzeigen($nutzer->fresh()->last_login_at))->toBe('01.07.2026 18:00');
});

test('ein fehlender Zeitpunkt bekommt einen lesbaren Ersatz', function () {
    expect(Zeit::anzeigen(null, 'd.m.Y H:i', 'noch nie'))->toBe('noch nie')
        ->and(Zeit::anzeigen(null))->toBe('');
});

test('eine unbekannte Zone wird nicht übernommen', function () {
    Setting::setzen(Setting::APP_TIMEZONE, 'Mars/Olympus');

    expect(Zeit::zone())->toBe(config('app.timezone'));
});

test('die Auswahl enthält UTC und Berlin', function () {
    expect(Zeit::auswahl())->toContain('UTC')->toContain('Europe/Berlin');
});

test('die Benutzerliste zeigt die Zeit in der eingestellten Zone', function () {
    Setting::setzen(Setting::APP_TIMEZONE, 'Europe/Berlin');

    $nutzer = userWithPermissions(['admin_user']);
    $nutzer->forceFill(['last_login_at' => Carbon::parse('2026-07-01 16:00:00', 'UTC')])->save();

    $this->actingAs($nutzer->fresh());

    $this->get(route('admin.user.index'))->assertSee('01.07.2026 18:00');
});

test('die Zeitzone lässt sich im Adminbereich umstellen', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire\Livewire::test(AdminAllgemein::class)
        ->set('zeitzone', 'Europe/Berlin')
        ->assertHasNoErrors();

    expect(Setting::wert(Setting::APP_TIMEZONE))->toBe('Europe/Berlin');
});

test('eine erfundene Zone wird abgewiesen', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire\Livewire::test(AdminAllgemein::class)
        ->set('zeitzone', 'Mars/Olympus')
        ->assertHasErrors('zeitzone');
});

test('"Angemeldet bleiben" hält nicht mehr fünf Jahre', function () {
    // Der Laravel-Standard ist praktisch unbegrenzt (fuenf Jahre). Ein
    // gestohlenes Notebook waere damit ein Dauerzugang. Geprueft am Cookie
    // selbst, nicht an einer Einstellung: Nur das Cookie entscheidet.
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make('Ein-Gutes-Kennwort-2026')])->save();

    $antwort = $this->post('/login', [
        'username' => $nutzer->username,
        'password' => 'Ein-Gutes-Kennwort-2026',
        'remember' => 'on',
    ]);

    $kekse = collect($antwort->headers->getCookies())
        ->filter(fn ($keks) => str_starts_with($keks->getName(), 'remember_web'));

    expect($kekse)->not->toBeEmpty();

    $tage = (int) round(($kekse->first()->getExpiresTime() - time()) / 86400);

    expect($tage)->toBe((int) config('custom.remember_days'))
        ->and($tage)->toBeLessThanOrEqual(90);
});
