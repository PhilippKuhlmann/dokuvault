<?php

use App\Models\Setting;
use App\Notifications\Testmail;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function alsEinstellungsverwaltung(): void
{
    test()->actingAs(userWithPermissions(['admin_setting']));
}

function mailFormular(array $abweichend = []): array
{
    return array_merge([
        'mail_host' => 'smtp.example.com',
        'mail_port' => '587',
        'mail_username' => 'postfach@example.com',
        'mail_password' => 'Geheim-2026',
        'mail_encryption' => 'tls',
        'mail_from_address' => 'doku@example.com',
        'mail_from_name' => 'Netzdoku',
    ], $abweichend);
}

test('die Seite ist nur mit dem Recht admin_setting erreichbar', function () {
    $this->actingAs(userWithPermissions([]));
    $this->get(route('admin.mail.index'))->assertForbidden();

    nutzerWechseln(userWithPermissions(['admin_setting']));
    $this->get(route('admin.mail.index'))->assertStatus(200);
});

test('die Einstellungen werden gespeichert', function () {
    alsEinstellungsverwaltung();

    $this->patch(route('admin.mail.update'), mailFormular())
        ->assertRedirect(route('admin.mail.index'));

    expect(Setting::wert(Setting::MAIL_HOST))->toBe('smtp.example.com')
        ->and(Setting::wert(Setting::MAIL_PORT))->toBe('587')
        ->and(Setting::wert(Setting::MAIL_FROM_ADDRESS))->toBe('doku@example.com');
});

test('das Kennwort liegt verschlüsselt in der Einstellung', function () {
    // Die Werte gehen ueber einen Cache, und ein Kennwort, mit dem sich im
    // Namen der Firma Mail verschicken laesst, hat weder dort noch in einem
    // Datenbank-Abzug etwas im Klartext zu suchen.
    alsEinstellungsverwaltung();

    $this->patch(route('admin.mail.update'), mailFormular());

    $roh = DB::table('settings')->where('key', Setting::MAIL_PASSWORD)->value('value');

    expect($roh)->not->toBe('Geheim-2026')
        ->and(Setting::mailKennwort())->toBe('Geheim-2026');
});

test('ein leeres Kennwortfeld lässt das hinterlegte stehen', function () {
    // Sonst waere jedes Speichern der uebrigen Felder ein stiller Verlust.
    alsEinstellungsverwaltung();

    $this->patch(route('admin.mail.update'), mailFormular());
    $this->patch(route('admin.mail.update'), mailFormular([
        'mail_password' => '',
        'mail_host' => 'smtp.anders.example',
    ]));

    expect(Setting::mailKennwort())->toBe('Geheim-2026')
        ->and(Setting::wert(Setting::MAIL_HOST))->toBe('smtp.anders.example');
});

test('zum Entfernen gibt es einen eigenen Weg', function () {
    alsEinstellungsverwaltung();

    $this->patch(route('admin.mail.update'), mailFormular());
    $this->delete(route('admin.mail.kennwort'))->assertRedirect(route('admin.mail.index'));

    expect(Setting::mailKennwort())->toBeNull();
});

test('das Kennwort steht nie wieder auf der Seite', function () {
    alsEinstellungsverwaltung();
    $this->patch(route('admin.mail.update'), mailFormular());

    $this->get(route('admin.mail.index'))
        ->assertDontSee('Geheim-2026')
        ->assertSee('Ein Kennwort ist hinterlegt');
});

test('krumme Eingaben werden abgewiesen', function () {
    alsEinstellungsverwaltung();

    $this->patch(route('admin.mail.update'), mailFormular(['mail_port' => '99999']))
        ->assertSessionHasErrors('mail_port');

    $this->patch(route('admin.mail.update'), mailFormular(['mail_from_address' => 'keine-adresse']))
        ->assertSessionHasErrors('mail_from_address');

    $this->patch(route('admin.mail.update'), mailFormular(['mail_encryption' => 'irgendwas']))
        ->assertSessionHasErrors('mail_encryption');
});

test('gespeicherte Einstellungen gelten statt der Umgebung', function () {
    Setting::setzen(Setting::MAIL_HOST, 'smtp.aus-der-datenbank');
    Setting::setzen(Setting::MAIL_PORT, '2525');
    Setting::mailKennwortSetzen('Geheim-2026');
    Setting::setzen(Setting::MAIL_FROM_ADDRESS, 'doku@example.com');

    // Der Provider legt die Werte beim Hochfahren ueber die Konfiguration.
    // refreshApplication() ginge nicht: Die Testdatenbank liegt im
    // Arbeitsspeicher und stuerbe mit der Verbindung.
    (new AppServiceProvider($this->app))->boot();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.aus-der-datenbank')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.password'))->toBe('Geheim-2026')
        ->and(config('mail.from.address'))->toBe('doku@example.com');
});

test('ohne Server bleibt die Umgebung unangetastet', function () {
    // Wer nichts eintraegt, behaelt seine .env - so wie vor dieser Einstellung.
    $ausUmgebung = config('mail.mailers.smtp.host');

    Setting::setzen(Setting::MAIL_HOST, null);
    (new AppServiceProvider($this->app))->boot();

    expect(config('mail.mailers.smtp.host'))->toBe($ausUmgebung);
});

test('die Testmail geht an die angegebene Adresse', function () {
    Notification::fake();
    alsEinstellungsverwaltung();

    $this->post(route('admin.mail.test'), ['test_an' => 'probe@example.com'])
        ->assertRedirect(route('admin.mail.index'))
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(Testmail::class);
});

test('eine krumme Empfängeradresse wird abgewiesen', function () {
    Notification::fake();
    alsEinstellungsverwaltung();

    $this->post(route('admin.mail.test'), ['test_an' => 'keine-adresse'])
        ->assertSessionHasErrors('test_an');

    Notification::assertNothingSent();
});

test('scheitert der Versand, steht der Grund auf der Seite', function () {
    // Kein Mock, sondern ein echter Fehlschlag: Port 1 auf dem eigenen Rechner
    // nimmt niemand entgegen. "Verbindung fehlgeschlagen" allein hilft
    // niemandem - die Meldung des Servers gehoert auf die Seite.
    alsEinstellungsverwaltung();

    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.timeout' => 1,
    ]);

    $this->post(route('admin.mail.test'), ['test_an' => 'probe@example.com'])
        ->assertRedirect(route('admin.mail.index'))
        ->assertSessionHasErrors('test_an');

    expect(session('errors')->first('test_an'))->toContain('fehlgeschlagen');
});

test('"ohne Verschlüsselung" lässt sich speichern', function () {
    // Laravel behandelt einen leeren String wie "nicht vorhanden": Mit
    // required liess sich diese Wahl gar nicht treffen - und die Seite sah
    // aus, als waere nichts passiert.
    alsEinstellungsverwaltung();

    $this->patch(route('admin.mail.update'), mailFormular(['mail_encryption' => '']))
        ->assertSessionHasNoErrors();

    expect(Setting::wert(Setting::MAIL_ENCRYPTION))->toBe('');

    (new AppServiceProvider($this->app))->boot();

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

test('ein Fehler ist auf der Seite zu sehen', function () {
    alsEinstellungsverwaltung();

    // from(): Ein Validierungsfehler schickt zurueck, woher die Anfrage kam.
    // Ohne das landet der Test auf der Startseite und prueft nichts.
    $this->from(route('admin.mail.index'))
        ->followingRedirects()
        ->patch(route('admin.mail.update'), mailFormular(['mail_port' => '99999']))
        ->assertSee('Port');
});
