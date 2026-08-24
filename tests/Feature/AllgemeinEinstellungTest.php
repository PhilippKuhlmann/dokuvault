<?php

use App\Livewire\AdminAllgemein;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// adminNutzer() steht schon in FernwartungTest - Pest teilt Helfer testweit.

test('ohne eigenen Namen bleibt der Name aus der Konfiguration', function () {
    config(['app.name' => 'DokuVault']);

    expect(Setting::appName())->toBe('DokuVault');
});

test('der Name wird beim Tippen gespeichert, ohne Speichern-Knopf', function () {
    config(['app.name' => 'DokuVault']);
    $this->actingAs(adminNutzer());

    Livewire::test(AdminAllgemein::class)
        ->set('name', 'Musterfirma IT')
        ->assertHasNoErrors();

    // Kein weiterer Aufruf noetig - das Setzen allein hat gereicht.
    expect(Setting::appName())->toBe('Musterfirma IT');

    auth()->logout();
    $this->get('/login')->assertSee('Musterfirma IT');
});

test('ein leeres Namensfeld heisst zurueck zur Konfiguration, nicht leerer Name', function () {
    config(['app.name' => 'DokuVault']);
    $this->actingAs(adminNutzer());
    Setting::setzen(Setting::APP_NAME, 'Alter Name');

    Livewire::test(AdminAllgemein::class)->set('name', '');

    expect(Setting::appName())->toBe('DokuVault');
});

test('ein zu langer Name wird abgelehnt und nicht gespeichert', function () {
    $this->actingAs(adminNutzer());

    Livewire::test(AdminAllgemein::class)
        ->set('name', str_repeat('a', 61))
        ->assertHasErrors('name');

    expect(Setting::wert(Setting::APP_NAME))->toBeNull();
});

test('ein ausgewaehltes Logo ist damit schon gesetzt', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());

    Livewire::test(AdminAllgemein::class)
        ->set('logo_header', UploadedFile::fake()->image('k.png', 200, 40))
        ->assertHasNoErrors();

    expect(Setting::logoPfad('header'))->not->toBeNull();
    Storage::disk('local')->assertExists(Setting::logoPfad('header'));
});

test('jede der drei Stellen laesst sich einzeln belegen', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());

    // Anmeldeseite gross und breit, Kopfzeile schmal, Favicon quadratisch -
    // drei verschiedene Dateien.
    Livewire::test(AdminAllgemein::class)
        ->set('logo_login', UploadedFile::fake()->image('gross.png', 400, 120));
    Livewire::test(AdminAllgemein::class)
        ->set('logo_header', UploadedFile::fake()->image('schmal.png', 200, 40));
    Livewire::test(AdminAllgemein::class)
        ->set('logo_favicon', UploadedFile::fake()->image('quadrat.png', 64, 64));

    $pfade = collect(Setting::LOGO_STELLEN)->map(fn ($s) => Setting::logoPfad($s));

    expect($pfade->filter()->count())->toBe(3);
    expect($pfade->unique()->count())->toBe(3);
});

test('jede Stelle nutzt ihr eigenes Logo, nicht das der anderen', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    Livewire::test(AdminAllgemein::class)->set('logo_login', UploadedFile::fake()->image('a.png'));
    Livewire::test(AdminAllgemein::class)->set('logo_header', UploadedFile::fake()->image('k.png'));
    Livewire::test(AdminAllgemein::class)->set('logo_favicon', UploadedFile::fake()->image('f.png'));

    $this->get(route('admin.dashboard'))
        ->assertSee(route('branding.logo', 'header'), false)
        ->assertSee('rel="icon" href="'.route('branding.logo', 'favicon').'"', false)
        ->assertDontSee(route('branding.logo', 'login'), false);

    auth()->logout();
    $this->get('/login')
        ->assertSee(route('branding.logo', 'login'), false)
        ->assertDontSee('"'.route('branding.logo', 'header').'"', false);
});

test('ein neues Logo laesst keine Leiche zurueck', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    Livewire::test(AdminAllgemein::class)->set('logo_header', UploadedFile::fake()->image('alt.png'));
    $alt = Setting::logoPfad('header');

    Livewire::test(AdminAllgemein::class)->set('logo_header', UploadedFile::fake()->image('neu.png'));

    Storage::disk('local')->assertMissing($alt);
    Storage::disk('local')->assertExists(Setting::logoPfad('header'));
});

test('der Entfernen-Knopf raeumt Einstellung und Datei weg', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    Livewire::test(AdminAllgemein::class)->set('logo_header', UploadedFile::fake()->image('k.png'));
    Livewire::test(AdminAllgemein::class)->set('logo_login', UploadedFile::fake()->image('a.png'));
    $kopfzeile = Setting::logoPfad('header');

    // Ein Knopf, kein Haken mit Speichern danach.
    Livewire::test(AdminAllgemein::class)->call('entfernen', 'header');

    expect(Setting::logoPfad('header'))->toBeNull();
    Storage::disk('local')->assertMissing($kopfzeile);
    // Die Anmeldeseite behaelt ihres.
    expect(Setting::logoPfad('login'))->not->toBeNull();
});

test('SVG wird abgelehnt', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());

    // Eine SVG-Datei darf Skript enthalten - von derselben Herkunft
    // ausgeliefert waere das ausfuehrbarer Code auf jeder Seite.
    Livewire::test(AdminAllgemein::class)
        ->set('logo_login', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
        ->assertHasErrors('logo_login');

    expect(Setting::logoPfad('login'))->toBeNull();
});

test('eine unbekannte Stelle beim Entfernen ergibt 404', function () {
    $this->actingAs(adminNutzer());

    // Die Stelle kommt aus der Whitelist, nie roh aus der Anfrage - sonst
    // waere der Einstellungs-Schluessel von aussen bestimmbar.
    Livewire::test(AdminAllgemein::class)->call('entfernen', 'app_name')
        ->assertStatus(404);
});

test('eine unbekannte Stelle in der Adresse ergibt 404', function () {
    $this->get('/logo/app_name')->assertNotFound();
});

test('das Logo geht ohne Anmeldung heraus, mit nosniff', function () {
    Storage::fake('local');
    $this->actingAs(adminNutzer());
    Livewire::test(AdminAllgemein::class)->set('logo_login', UploadedFile::fake()->image('a.png'));

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
});
