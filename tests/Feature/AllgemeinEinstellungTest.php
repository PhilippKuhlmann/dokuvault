<?php

use App\Livewire\AdminAllgemein;
use App\Livewire\ObjektListe;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
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

    $this->get(route('admin.general.index'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Sprache, Anmeldehinweis und Seitengrößen
|--------------------------------------------------------------------------
*/

test('ohne Einstellung gelten Sprache, Seitengröße und Hinweis aus der Konfiguration', function () {
    expect(Setting::sprache())->toBe(config('app.locale'))
        ->and(Setting::seiteListe())->toBe(config('custom.seiten.liste'))
        ->and(Setting::seiteAdmin())->toBe(config('custom.seiten.admin'))
        ->and(Setting::anmeldeHinweis())->toBe('');
});

/*
|--------------------------------------------------------------------------
| Sprache
|--------------------------------------------------------------------------
*/

test('die Sprache der Installation greift, wenn der Browser nichts Passendes verlangt', function () {
    Setting::setzen(Setting::APP_LOCALE, 'en');

    // Vor der Korrektur an SetLocale kam diese Stufe nie zum Zug:
    // getPreferredLanguage() liefert bei unbekannter Browsersprache die erste
    // aus der Liste - also "de" - statt null. Die Einstellung waere wirkungslos
    // geblieben, ohne dass es jemandem auffaellt.
    $this->withHeaders(['Accept-Language' => 'fr-FR,fr'])->get('/login')->assertOk();

    expect(app()->getLocale())->toBe('en');
});

test('der Browser schlägt die Sprache der Installation', function () {
    Setting::setzen(Setting::APP_LOCALE, 'en');

    $this->withHeaders(['Accept-Language' => 'de-DE,de'])->get('/login')->assertOk();

    expect(app()->getLocale())->toBe('de');
});

test('eine nicht angebotene Sprache wird verworfen', function () {
    // Der Wert kaeme sonst ungeprueft aus der Datenbank in App::setLocale().
    Setting::setzen(Setting::APP_LOCALE, 'kl');

    expect(Setting::sprache())->toBe(config('app.locale'));
});

/*
|--------------------------------------------------------------------------
| Hinweis auf der Anmeldeseite
|--------------------------------------------------------------------------
*/

test('der Hinweis steht auf der Anmeldeseite', function () {
    Setting::setzen(Setting::ANMELDE_HINWEIS, 'Bei Fragen: 0800 123456');

    $this->get('/login')->assertSee('Bei Fragen: 0800 123456');
});

test('ohne Hinweis steht dort nichts', function () {
    $this->get('/login')->assertOk()->assertDontSee('border-t border-gray-200 pt-4 text-center', false);
});

test('der Hinweis kommt escaped heraus', function () {
    // Die Anmeldeseite ist die eine Seite, die jeder erreicht - auch ohne
    // Zugang. Ein Feld, das dort HTML einschleusen kann, waere der schlechteste
    // denkbare Ort dafuer.
    Setting::setzen(Setting::ANMELDE_HINWEIS, '<script>window.beweis=1</script>');

    $antwort = $this->get('/login');

    $antwort->assertDontSee('<script>window.beweis=1</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

/*
|--------------------------------------------------------------------------
| Zeilen je Seite
|--------------------------------------------------------------------------
*/

test('die eingestellte Seitengröße wirkt in den Adminlisten', function () {
    Setting::setzen(Setting::SEITE_ADMIN, 5);

    $rolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    User::factory()->count(8)->create(['role_id' => $rolle->id]);
    $admin = User::factory()->create(['role_id' => $rolle->id]);

    $this->actingAs($admin);

    // Am Paginator selbst: Der Blaetter-Link haengt am Aussehen der
    // Paginierungsvorlage, die Seitengroesse nicht.
    $liste = $this->get('/admin/user')->assertOk()->viewData('users');

    expect($liste->perPage())->toBe(5)
        ->and($liste->count())->toBe(5)
        ->and($liste->hasMorePages())->toBeTrue();
});

test('die eingestellte Seitengröße wirkt in den Kundenlisten', function () {
    Setting::setzen(Setting::SEITE_LISTE, 5);

    $customer = Customer::factory()->create();
    $nutzer = adminNutzer();
    $this->actingAs($nutzer);

    Site::factory()->count(7)->create(['customer_id' => $customer->id]);

    // Am Paginator, nicht am Blaetter-Link: Livewire blaettert per wire:click,
    // in der Ausgabe steht kein "page=2".
    $eintraege = Livewire::test(ObjektListe::class, ['typ' => 'site', 'customer' => $customer])
        ->viewData('eintraege');

    expect($eintraege->perPage())->toBe(5)
        ->and($eintraege->count())->toBe(5)
        ->and($eintraege->hasMorePages())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Die Einstellungsseite
|--------------------------------------------------------------------------
*/

test('Sprache, Hinweis und Seitengrößen speichern beim Ändern', function () {
    $this->actingAs(adminNutzer());

    Livewire::test(AdminAllgemein::class)
        ->set('sprache', 'en')
        ->set('anmeldeHinweis', 'Support: it@example.test')
        ->set('seiteListe', 50)
        ->set('seiteAdmin', 10)
        ->assertHasNoErrors();

    expect(Setting::sprache())->toBe('en')
        ->and(Setting::anmeldeHinweis())->toBe('Support: it@example.test')
        ->and(Setting::seiteListe())->toBe(50)
        ->and(Setting::seiteAdmin())->toBe(10);
});

test('eine erfundene Sprache wird abgewiesen', function () {
    $this->actingAs(adminNutzer());

    Livewire::test(AdminAllgemein::class)
        ->set('sprache', 'kl')
        ->assertHasErrors(['sprache']);
});

test('ein zu langer Hinweis wird abgewiesen', function () {
    $this->actingAs(adminNutzer());

    // Es ist ein Satz, kein Aushang.
    Livewire::test(AdminAllgemein::class)
        ->set('anmeldeHinweis', str_repeat('a', 201))
        ->assertHasErrors(['anmeldeHinweis']);
});

test('eine Seite mit einer Zeile wird abgewiesen', function () {
    $this->actingAs(adminNutzer());

    Livewire::test(AdminAllgemein::class)
        ->set('seiteListe', 1)
        ->assertHasErrors(['seiteListe']);

    expect(Setting::seiteListe())->toBe(config('custom.seiten.liste'));
});
