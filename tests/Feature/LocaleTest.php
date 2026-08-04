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

test('mit englischer Sprache erscheint die Oberfläche auf Englisch', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->update(['locale' => 'en']);

    $this->actingAs($nutzer)->get('/profile')
        ->assertSee('Language')
        ->assertSee('Save')
        ->assertDontSee('Sprache')
        ->assertDontSee('Speichern');
});

test('auf Deutsch bleibt die Oberfläche deutsch', function () {
    $nutzer = userWithPermissions([]);
    $nutzer->update(['locale' => 'de']);

    $this->actingAs($nutzer)->get('/profile')
        ->assertSee('Sprache')
        ->assertSee('Speichern');
});

test('jede Zeichenkette in lang/en.json wird auch verwendet', function () {
    $uebersetzt = array_keys(json_decode(file_get_contents(base_path('lang/en.json')), true));

    // Alle __('...')-Aufrufe im Projekt einsammeln.
    $verwendet = [];
    foreach (['resources/views', 'app', 'config'] as $ordner) {
        $dateien = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($ordner)));
        foreach ($dateien as $datei) {
            if ($datei->isFile() && preg_match('/\.(php|blade\.php)$/', $datei->getFilename())) {
                $inhalt = file_get_contents($datei);
                preg_match_all("/__\('((?:[^'\\\\]|\\\\.)+)'/", $inhalt, $treffer);
                $verwendet = array_merge($verwendet, $treffer[1]);

                // Schluessel von :array="['Hersteller' => …]" und :groups sind
                // die Beschriftungen; sie laufen erst zur Laufzeit durch __()
                // (siehe x-minitablecard und x-pdf.section).
                preg_match_all('/:(?:array|groups)="\[(.*?)\]"/s', $inhalt, $bloecke);
                foreach ($bloecke[1] as $block) {
                    preg_match_all("/'([^']{2,60})'\s*=>/", $block, $schluessel);
                    $verwendet = array_merge($verwendet, $schluessel[1]);
                }
            }
        }
    }
    // Beschriftungen aus der Konfiguration laufen erst zur Laufzeit durch __().
    $ausConfig = collect(config('custom.wizard_steps'))
        ->flatMap(fn ($s) => array_merge(
            [$s['group'] ?? null, $s['label'] ?? null, $s['question'] ?? null],
            collect($s['fields'] ?? [])->flatMap(fn ($f) => array_merge(
                [$f['label'] ?? null],
                is_array($f['options'] ?? null) ? array_values($f['options']) : []
            ))->all()
        ))
        ->merge(array_values(config('custom.list_titles', [])))
        ->merge(array_values(config('custom.rack_appearances', [])))
        ->merge(array_values(config('custom.server_form_factors', [])))
        ->merge(array_values(config('custom.server_depths', [])))
        ->merge(collect(config('custom.trashables', []))->map(fn ($t) => $t[1] ?? null))
        ->merge(collect(config('custom.rack_device_types', []))->map(fn ($t) => $t[1] ?? null))
        ->filter()->all();

    $verwendet = array_unique(array_merge($verwendet, $ausConfig));
    $verwaist = array_values(array_diff($uebersetzt, $verwendet));

    // Verwaiste Eintraege sind kein Fehler zur Laufzeit, aber toter Ballast -
    // und meist der Rest einer umbenannten Beschriftung.
    expect($verwaist)->toBe([], 'Übersetzt, aber nirgends verwendet: '.implode(' | ', $verwaist));
});
