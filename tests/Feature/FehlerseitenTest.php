<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Fehlerseiten statt der von Laravel.
 *
 * Gemeldet: Die 404 blieb hell, obwohl die Anwendung auf dunkel stand. Die
 * eingebaute Seite bringt ihr eigenes CSS mit und schaltet ueber
 * prefers-color-scheme - also nach dem Betriebssystem. Die Anwendung schaltet
 * ueber die Klasse "dark" aus localStorage.
 */
test('die Fehlerseite folgt der Einstellung der Anwendung, nicht dem System', function () {
    $inhalt = $this->actingAs(userWithPermissions([]))
        ->get('/gibt-es-nicht')->assertNotFound()->getContent();

    // Dasselbe Skript wie in den Layouts - daran haengt die ganze Umschaltung.
    expect($inhalt)->toContain("localStorage.getItem('color-theme')");
    expect($inhalt)->toContain("classList.add('dark')");

    // Die Farben kommen aus dem CSS der Anwendung, nicht aus einem eigenen
    // <style> mit @media-Abfrage.
    expect($inhalt)->toContain('dark:bg-gray-900');
    expect(str_contains($inhalt, 'prefers-color-scheme:dark'))
        ->toBeFalse('Die Seite entscheidet nach dem System statt nach der Anwendung.');
});

test('jede Fehlerseite nennt ihren Code und einen Weg zurueck', function () {
    foreach ([403, 404, 419, 500, 503] as $code) {
        $inhalt = view("errors.$code")->render();

        expect($inhalt)->toContain((string) $code);
        // Zwei Wege hinaus: zur Startseite und dahin, wo man herkam.
        expect($inhalt)->toContain('history.back()');
        expect($inhalt)->toContain('href="'.url('/').'"');
    }
});

test('die Fehlerseite kommt ohne Einstellungstabelle aus', function () {
    // Ein 500er kann von einer kaputten Datenbank kommen. Holte die Seite den
    // eigenen Anwendungsnamen ungeschuetzt, risse sie derselbe Fehler mit -
    // und statt einer Meldung saehe man gar nichts.
    Schema::drop('settings');

    $inhalt = view('errors.500')->render();

    expect($inhalt)->toContain('500');
    expect($inhalt)->toContain(config('app.name'));
});

test('eine entfernte Adresse fuehrt auf die eigene 404', function () {
    $customer = Customer::factory()->create();

    // Der Weg, auf dem der Fehler auffiel: eine Adresse, die es seit dem
    // Aufraeumen der alten Seiten nicht mehr gibt.
    $this->actingAs(userWithPermissions(['server_viewAny']))
        ->get("/{$customer->slug}/server/create")
        ->assertNotFound()
        ->assertSee('Seite nicht gefunden');
});

test('jede Fehlerseite bringt einen Spruch aus ihrer Liste mit', function () {
    foreach (config('custom.fehler_sprueche') as $code => $sprueche) {
        $inhalt = view("errors.$code")->render();

        $getroffen = collect($sprueche)->filter(fn ($s) => str_contains($inhalt, e($s)));

        expect($getroffen)->toHaveCount(1, "Fehlerseite $code zeigt keinen oder mehrere Sprüche.");
    }
});

test('der Spruch wechselt, statt immer derselbe zu sein', function () {
    // Zwanzig Ziehungen aus drei Sprüchen: dass dabei immer derselbe kommt,
    // ist praktisch ausgeschlossen - und genau das waere der Fehler.
    $gesehen = collect(range(1, 20))
        ->map(fn () => view('errors.404')->render())
        ->map(fn ($html) => collect(config('custom.fehler_sprueche')[404])
            ->first(fn ($s) => str_contains($html, e($s))))
        ->unique();

    expect($gesehen->count())->toBeGreaterThan(1);
});
