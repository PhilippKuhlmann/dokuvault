<?php

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// adminNutzer() steht schon in FernwartungTest - Pest teilt Helfer testweit.

test('ohne eigenen Namen bleibt der Name aus der Konfiguration', function () {
    config(['app.name' => 'DokuVault']);

    expect(Setting::appName())->toBe('DokuVault');
});

test('ein eigener Name ersetzt den aus der Konfiguration', function () {
    config(['app.name' => 'DokuVault']);
    $this->actingAs(adminNutzer());

    $this->post(route('admin.allgemein.update'), ['app_name' => 'Musterfirma IT'])
        ->assertSessionHasNoErrors();

    expect(Setting::appName())->toBe('Musterfirma IT');

    // Abmelden zuerst: Die guest-Middleware leitet Angemeldete von /login weg.
    auth()->logout();
    $this->get('/login')->assertSee('Musterfirma IT');
});

test('ein leeres Namensfeld heisst zurueck zur Konfiguration, nicht leerer Name', function () {
    config(['app.name' => 'DokuVault']);
    $this->actingAs(adminNutzer());
    Setting::setzen(Setting::APP_NAME, 'Alter Name');

    $this->post(route('admin.allgemein.update'), ['app_name' => '']);

    expect(Setting::appName())->toBe('DokuVault');
});

test('jede der drei Stellen laesst sich einzeln mit einem Logo belegen', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());

    // Genau das ist der Fall: Anmeldeseite gross und breit, Kopfzeile schmal,
    // Favicon quadratisch - drei verschiedene Dateien.
    $this->post(route('admin.allgemein.update'), [
        'logo_login' => UploadedFile::fake()->image('gross.png', 400, 120),
        'logo_header' => UploadedFile::fake()->image('schmal.png', 200, 40),
        'logo_favicon' => UploadedFile::fake()->image('quadrat.png', 64, 64),
    ])->assertSessionHasNoErrors();

    foreach (Setting::LOGO_STELLEN as $stelle) {
        expect(Setting::logoPfad($stelle))->not->toBeNull("Stelle {$stelle} leer");
        Storage::disk('local')->assertExists(Setting::logoPfad($stelle));
    }

    // Drei verschiedene Dateien, nicht dreimal dieselbe.
    $pfade = collect(Setting::LOGO_STELLEN)->map(fn ($s) => Setting::logoPfad($s));
    expect($pfade->unique()->count())->toBe(3);
});

test('eine Stelle allein zu belegen laesst die anderen unberuehrt', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    $this->post(route('admin.allgemein.update'), ['logo_header' => UploadedFile::fake()->image('k.png')]);
    $kopfzeile = Setting::logoPfad('header');

    $this->post(route('admin.allgemein.update'), ['logo_login' => UploadedFile::fake()->image('a.png')]);

    expect(Setting::logoPfad('header'))->toBe($kopfzeile);
    expect(Setting::logoPfad('login'))->not->toBeNull();
    expect(Setting::logoPfad('favicon'))->toBeNull();
});

test('jede Stelle nutzt ihr eigenes Logo, nicht das der anderen', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    $this->post(route('admin.allgemein.update'), [
        'logo_login' => UploadedFile::fake()->image('a.png'),
        'logo_header' => UploadedFile::fake()->image('k.png'),
        'logo_favicon' => UploadedFile::fake()->image('f.png'),
    ]);

    // Kopfzeile und Favicon im angemeldeten Bereich.
    $this->get(route('admin.dashboard'))
        ->assertSee(route('branding.logo', 'header'), false)
        ->assertSee('rel="icon" href="'.route('branding.logo', 'favicon').'"', false)
        ->assertDontSee(route('branding.logo', 'login'), false);

    // Anmeldeseite nur ihr eigenes.
    auth()->logout();
    $this->get('/login')
        ->assertSee(route('branding.logo', 'login'), false)
        ->assertDontSee('"'.route('branding.logo', 'header').'"', false);
});

test('ein neues Logo laesst keine Leiche zurueck', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());

    $this->post(route('admin.allgemein.update'), ['logo_header' => UploadedFile::fake()->image('alt.png')]);
    $alt = Setting::logoPfad('header');

    $this->post(route('admin.allgemein.update'), ['logo_header' => UploadedFile::fake()->image('neu.png')]);

    Storage::disk('local')->assertMissing($alt);
    Storage::disk('local')->assertExists(Setting::logoPfad('header'));
});

test('ein Logo laesst sich einzeln wieder entfernen', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    $this->post(route('admin.allgemein.update'), [
        'logo_header' => UploadedFile::fake()->image('k.png'),
        'logo_login' => UploadedFile::fake()->image('a.png'),
    ]);
    $kopfzeile = Setting::logoPfad('header');

    $this->post(route('admin.allgemein.update'), ['entfernen_header' => '1']);

    expect(Setting::logoPfad('header'))->toBeNull();
    Storage::disk('local')->assertMissing($kopfzeile);
    // Die Anmeldeseite behaelt ihres.
    expect(Setting::logoPfad('login'))->not->toBeNull();
    $this->get(route('branding.logo', 'header'))->assertNotFound();
});

test('SVG wird abgelehnt', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());

    // Eine SVG-Datei darf Skript enthalten - von derselben Herkunft
    // ausgeliefert waere das ausfuehrbarer Code auf jeder Seite.
    $this->post(route('admin.allgemein.update'), [
        'logo_login' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('logo_login');

    expect(Setting::logoPfad('login'))->toBeNull();
});

test('eine unbekannte Stelle in der Adresse ergibt 404', function () {
    // Die Stelle kommt aus der Whitelist, nie roh aus der Adresse - sonst
    // waere der Einstellungs-Schluessel von aussen bestimmbar.
    $this->get('/logo/app_name')->assertNotFound();
    $this->get('/logo/../../.env')->assertNotFound();
});

test('das Logo geht ohne Anmeldung heraus, mit nosniff', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    $this->post(route('admin.allgemein.update'), ['logo_login' => UploadedFile::fake()->image('a.png')]);

    auth()->logout();
    $this->get(route('branding.logo', 'login'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('ohne eigene Logos bleibt ueberall das eingebaute Motiv stehen', function () {
    $this->actingAs(adminNutzer());

    // Mit Anfuehrungszeichen vergleichen: "/logo.svg" enthaelt "/logo" und
    // wuerde sonst als Treffer gelten.
    $this->get(route('admin.dashboard'))
        ->assertSee('logo.svg', false)
        ->assertDontSee('"'.route('branding.logo', 'header').'"', false);
});

test('ohne das Recht admin_setting bleibt die Seite zu', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('admin.allgemein.index'))->assertForbidden();
    $this->post(route('admin.allgemein.update'), ['app_name' => 'Fremd'])->assertForbidden();
});
