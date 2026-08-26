<?php

use App\Models\Customer;
use App\Models\Rack;
use App\Models\Role;
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

                // trans_choice() ist ebenso ein Uebersetzungsaufruf - er steht
                // dort, wo die Mehrzahl von der Anzahl abhaengt ("1 Datei" /
                // "3 Dateien").
                preg_match_all("/trans_choice\('((?:[^'\\\\]|\\\\.)+)'/", $inhalt, $mehrzahl);
                $treffer[1] = array_merge($treffer[1], $mehrzahl[1]);
                $verwendet = array_merge($verwendet, $treffer[1]);

                // x-table.head :labels="['Bezeichnung', 'Höheneinheiten', ...]"
                // sind Spaltenueberschriften; die Komponente selbst schickt
                // jedes Element durch __(), der Aufruf steht also nicht woertlich
                // an der Stelle, an der die Beschriftung erscheint.
                preg_match_all('/:labels="\[(.*?)\]"/s', $inhalt, $labelBloecke);
                foreach ($labelBloecke[1] as $block) {
                    preg_match_all("/'([^']{1,60})'/", $block, $eintraege);
                    $verwendet = array_merge($verwendet, $eintraege[1]);
                }

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
        ->merge(array_values(config('custom.admin_list_titles', [])))
        // Beschriftung und Hinweis je Logo-Stelle.
        ->merge(collect(config('custom.branding_logos', []))->flatten())
        // Beschriftung je Dateiart.
        ->merge(collect(config('custom.file_arten', []))->map(fn ($a) => $a[0]))
        // Filter und Sortierungen der Listen (config/forms.php): Beschriftung,
        // Optionen und die Zeile "Alle" laufen erst zur Laufzeit durch __().
        ->merge(collect(config('forms'))->flatMap(fn ($typ) => collect($typ['filter'] ?? [])
            ->flatMap(fn ($f) => array_merge([$f['label'], $f['alle'] ?? null], array_values($f['optionen'] ?? [])))))
        ->merge(collect(config('forms'))->flatMap(fn ($typ) => collect($typ['sortierungen'] ?? [])->map(fn ($s) => $s[0])))
        // Beschriftung des Erzeuger-Knopfs je Typ. values(), weil merge()
        // nach Schluessel zusammenfuehrt: 'sshkey' kommt weiter unten in
        // trashables noch einmal vor und haette den Eintrag ueberschrieben.
        ->merge(collect(config('forms'))->map(fn ($typ) => $typ['erzeuger_label'] ?? null)->filter()->values())
        // Feldbeschriftungen und Platzhalter der Modale (config/forms.php):
        // Sie stehen nirgends als __('...') im Code, sondern laufen im
        // generischen Formular zur Laufzeit hindurch.
        ->merge(collect(config('forms'))->flatMap(fn ($typ) => collect($typ['felder'] ?? [])
            ->flatMap(fn ($f) => array_merge(
                [$f['label'] ?? null, $f['platzhalter'] ?? null],
                array_values($f['werte'] ?? [])
            ))))
        ->merge(array_values(config('custom.rack_appearances', [])))
        ->merge(array_values(config('custom.server_form_factors', [])))
        ->merge(array_values(config('custom.cluster_types', [])))
        ->merge(array_values(config('custom.firewall_form_factors', [])))
        // Fernwartung: Beschriftungen der Werkzeuge und ihrer Felder.
        ->merge(collect(config('custom.remote_tools', []))->flatMap(fn ($t) => [
            $t['label'] ?? null, $t['id_label'] ?? null, $t['password_label'] ?? null,
        ]))
        ->merge(array_values(config('custom.secret_field_labels', [])))
        ->merge(array_values(config('custom.server_depths', [])))
        ->merge(collect(config('custom.trashables', []))->map(fn ($t) => $t[1] ?? null))
        ->merge(collect(config('custom.rack_device_types', []))->map(fn ($t) => $t[1] ?? null))
        // Beschriftungen aus Model-Konstanten laufen ebenfalls erst zur
        // Laufzeit durch __() - etwa die Rackseiten.
        ->merge(array_values(Rack::SEITEN))
        ->filter()->all();

    $verwendet = array_unique(array_merge($verwendet, $ausConfig));
    $verwaist = array_values(array_diff($uebersetzt, $verwendet));

    // Verwaiste Eintraege sind kein Fehler zur Laufzeit, aber toter Ballast -
    // und meist der Rest einer umbenannten Beschriftung.
    expect($verwaist)->toBe([], 'Übersetzt, aber nirgends verwendet: '.implode(' | ', $verwaist));
});

test('das Erstaufnahme-Banner erscheint auf Englisch', function () {
    $customer = Customer::factory()->create();
    $nutzer = userWithPermissions(['site_create']);
    $nutzer->update(['locale' => 'en', 'customer_id' => null]);

    // Ohne offenen Durchlauf und mit wenig Bestand zeigt das Dashboard die
    // "Starten"-Variante des Banners - vorher stand dort deutscher Text, egal
    // welche Sprache eingestellt war, weil die Ternary-Strings nie durch
    // __() liefen.
    $this->actingAs($nutzer)->get(route('customer.dashboard', $customer))
        ->assertSee('Start initial survey')
        ->assertSee('The wizard asks for site, network, servers and more, one step at a time.')
        ->assertDontSee('Erstaufnahme starten');
});

test('Tabellenkopfzeilen erscheinen auf Englisch', function () {
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $nutzer = User::factory()->create(['role_id' => $rolle->id]);
    $nutzer->update(['locale' => 'en']);

    // x-table.head reichte die Beschriftungen bisher roh durch - im
    // Englischen stand "BEZEICHNUNG" statt "DESIGNATION" da.
    $this->actingAs($nutzer)->get(route('admin.rackcatalogitem.index'))
        ->assertSee('Order')
        ->assertDontSee('Reihenfolge');
});

test('Admin-Seitentitel erscheinen auf Englisch', function () {
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $nutzer = User::factory()->create(['role_id' => $rolle->id]);
    $nutzer->update(['locale' => 'en']);

    // $adminTitles gab seinen Wert bisher roh zurueck: Im Menue stand schon
    // "Rack catalogue", die Ueberschrift daneben blieb "Rack-Katalog".
    $this->actingAs($nutzer)->get(route('admin.rackcatalogitem.index'))
        ->assertSee('Rack catalogue')
        ->assertDontSee('Rack-Katalog');
});
