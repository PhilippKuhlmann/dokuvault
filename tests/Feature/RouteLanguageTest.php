<?php

use Illuminate\Support\Facades\Route;

/**
 * Adressen und Routennamen sind englisch. Deutsch bleibt in der Oberflaeche
 * (lang/de.json) und im Changelog, nicht in der URL.
 *
 * Ausgenommen sind Adressen, die es nur noch als Weiterleitung gibt: Ein
 * Lesezeichen oder ein Link in einer verschickten Mail soll nicht ins Leere
 * laufen. Sie stehen hier einzeln, damit niemand versehentlich eine neue
 * deutsche Adresse dazulegt.
 */
const ALTE_ADRESSEN = [
    'admin/papierkorb',          // -> admin/trash
    'admin/protokoll-historie',  // -> admin/log-retention
    'einladung/{token}',         // -> invitation/{token}, Links gelten eine Woche
];

const DEUTSCHE_WOERTER = [
    'kennwort', 'papierkorb', 'protokoll', 'einladung', 'frist', 'dienst',
    'loesch', 'anmeld', 'benutzer', 'einstellung', 'auslastung', 'statistik',
    'wachstum', 'nutzung', 'agenten', 'abbrechen', 'verwerfen', 'erneuern',
    'stufe', 'suche', 'verlauf', 'beschaffung', 'standort', 'objekt', 'datei',
    'bild', 'wiederherstellen', 'bestaetigen', 'aufraeumen', 'sicherung',
];

function deutscheWoerterIn(string $wert): array
{
    $treffer = [];

    foreach (DEUTSCHE_WOERTER as $wort) {
        if (str_contains(strtolower($wert), $wort)) {
            $treffer[] = $wort;
        }
    }

    return $treffer;
}

test('keine URL traegt ein deutsches Wort', function () {
    $funde = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (in_array($uri, ALTE_ADRESSEN, true)) {
            continue;
        }

        foreach (deutscheWoerterIn($uri) as $wort) {
            $funde[] = "$uri (\"$wort\")";
        }
    }

    expect($funde)->toBe([], 'Deutsche Adressen: '.implode(', ', $funde));
});

test('kein Routenname traegt ein deutsches Wort', function () {
    $funde = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName() ?? '';

        foreach (deutscheWoerterIn($name) as $wort) {
            $funde[] = "$name (\"$wort\")";
        }
    }

    expect($funde)->toBe([], 'Deutsche Routennamen: '.implode(', ', $funde));
});

test('ein alter Einladungslink landet auf der englischen Adresse', function () {
    // Eine verschickte Einladung gilt eine Woche. Wer sie nach dem Umbau
    // anklickt, soll das Formular sehen und nicht einen Fehler 404.
    $this->get('/einladung/irgendein-token')
        ->assertRedirect('/invitation/irgendein-token');
});
