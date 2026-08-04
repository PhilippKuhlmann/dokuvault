<?php

use App\Models\User;

test('ohne Einstellung entscheidet die Browsersprache', function () {
    $this->withHeaders(['Accept-Language' => 'en-US,en'])->get('/login')->assertOk();
    expect(app()->getLocale())->toBe('en');

    $this->withHeaders(['Accept-Language' => 'de-DE,de'])->get('/login')->assertOk();
    expect(app()->getLocale())->toBe('de');
});

test('eine nicht angebotene Browsersprache fällt auf die Vorgabe zurück', function () {
    $this->withHeaders(['Accept-Language' => 'fr-FR,fr'])->get('/login')->assertOk();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

test('die Sitzungssprache greift auch ohne Anmeldung', function () {
    $this->post('/locale/en')->assertRedirect();

    $this->get('/login')->assertOk();
    expect(app()->getLocale())->toBe('en');
});

test('eine unbekannte Sprache ergibt 404 und ändert nichts', function () {
    $this->post('/locale/kl')->assertNotFound();

    expect(session('locale'))->toBeNull();
    $this->withHeaders(['Accept-Language' => 'de-DE,de'])->get('/login');
    expect(app()->getLocale())->toBe('de');
});

test('die Einstellung des Benutzers schlägt Sitzung und Browser', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->update(['locale' => 'de']);

    $this->post('/locale/en');
    $this->actingAs($nutzer)->withHeaders(['Accept-Language' => 'en-US,en'])->get('/login');

    expect(app()->getLocale())->toBe('de');
});

test('das Profil speichert die Sprache', function () {
    $nutzer = userWithPermissions([]);

    $this->actingAs($nutzer)->patch('/profile', [
        'name' => $nutzer->name, 'email' => 'test@example.test', 'locale' => 'en',
    ])->assertSessionHasNoErrors();

    expect($nutzer->fresh()->locale)->toBe('en');
});

test('eine leere Auswahl bedeutet Browsersprache, nicht Leerstring', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->update(['locale' => 'en']);

    $this->actingAs($nutzer)->patch('/profile', [
        'name' => $nutzer->name, 'email' => 'test@example.test', 'locale' => '',
    ]);

    expect($nutzer->fresh()->locale)->toBeNull();
});

test('eine unbekannte Sprache im Profil wird abgelehnt', function () {
    $nutzer = userWithPermissions([]);

    $this->actingAs($nutzer)->patch('/profile', [
        'name' => $nutzer->name, 'email' => 'test@example.test', 'locale' => 'kl',
    ])->assertSessionHasErrors('locale');
});

test('in der Demo lässt sich das Profil eines vordefinierten Zugangs nicht ändern', function () {
    config(['app.demo' => true]);
    $admin = userWithPermissions([]);
    $admin->update(['username' => 'admin', 'name' => 'Vorher']);

    $this->actingAs($admin)->patch('/profile', [
        'name' => 'Gekapert', 'email' => 'gekapert@example.test', 'locale' => 'en',
    ]);

    // Der geteilte Zugang der Demo bleibt unangetastet - auch die Sprache,
    // die sonst fuer alle uebrigen Besucher mit umspringen wuerde.
    expect($admin->fresh()->name)->toBe('Vorher');
    expect($admin->fresh()->locale)->toBeNull();
});

test('der Sprachumschalter steht auf jeder Seite', function () {
    $this->get('/login')->assertSee('locale/en', false);

    $this->actingAs(userWithPermissions([]))
        ->get('/profile')->assertSee('locale/en', false);
});

test('locale kommt nur aus der erlaubten Liste in die Anwendung', function () {
    // Direkt in der Datenbank vorbeigeschmuggelt, etwa aus einem alten Stand.
    $nutzer = userWithPermissions([]);
    User::where('id', $nutzer->id)->update(['locale' => 'xx']);

    $this->actingAs($nutzer->fresh())
        ->withHeaders(['Accept-Language' => 'de-DE,de'])->get('/login');

    expect(app()->getLocale())->toBe('de');
});
