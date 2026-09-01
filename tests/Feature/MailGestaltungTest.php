<?php

use App\Models\Setting;
use App\Models\User;
use App\Notifications\Einladung;

function gerenderteEinladung(User $nutzer): string
{
    return (new Einladung('probe-token'))->toMail($nutzer)->render();
}

test('die Mail trägt die Farben der Anwendung', function () {
    $html = gerenderteEinladung(userWithPermissions([]));

    // Kopfband in cerulean-950, Knopf in cerulean-600 - dieselben Toene wie in
    // der Oberflaeche. Vorher war beides das Schwarzgrau des Geruests.
    expect($html)->toContain('#122748')
        ->toContain('#1f73d6')
        ->not->toContain('#18181b');
});

test('ohne eigenes Logo steht der Name der Anwendung im Kopf', function () {
    // Das eingebaute Zeichen ist ein SVG, und SVG zeigen die meisten
    // Mailprogramme gar nicht erst an. Ein Name, der immer ankommt, ist
    // besser als ein Bild, das es meistens nicht tut.
    $html = gerenderteEinladung(userWithPermissions([]));

    expect($html)->toContain(Setting::appName())
        ->not->toContain('branding.logo')
        ->not->toContain('/logo/');
});

test('mit eigenem Logo steht es im Kopf', function () {
    Setting::updateOrCreate(
        ['key' => Setting::logoSchluessel('login')],
        ['value' => 'branding/eigenes-logo.png']
    );

    $html = gerenderteEinladung(userWithPermissions([]));

    expect($html)->toContain(route('branding.logo', 'login'));
});

test('der Kopf zeigt den eingestellten Namen, nicht den aus der Konfiguration', function () {
    // Die Installation laesst sich umbenennen - die Vorlage des Geruests
    // haette weiterhin config('app.name') gezeigt.
    Setting::updateOrCreate(['key' => Setting::APP_NAME], ['value' => 'Netzdoku Nord']);

    expect(gerenderteEinladung(userWithPermissions([])))->toContain('Netzdoku Nord');
});

test('der Fußtext ist deutsch und behauptet keine Rechte', function () {
    $html = gerenderteEinladung(userWithPermissions([]));

    expect($html)->toContain('automatisch verschickt')
        ->not->toContain('All rights reserved');
});

test('der Hinweis unter dem Knopf steht auf Deutsch', function () {
    // Der Satz kommt aus dem Framework-Template - dafuer gibt es lang/de.json.
    app()->setLocale('de');

    expect(gerenderteEinladung(userWithPermissions([])))
        ->toContain('Adresszeile Ihres Browsers')
        ->not->toContain('having trouble clicking');
});
