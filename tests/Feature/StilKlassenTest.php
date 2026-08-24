<?php

/**
 * Tailwind erzeugt nur Klassen, die es beim Bauen im Quelltext gefunden hat.
 * Wer eine View aendert und nicht neu baut, bekommt kein Fehlerbild - die Regel
 * fehlt einfach, und die Darstellung faellt still auf etwas anderes zurueck.
 *
 * Genau das ist passiert: Die ausgewaehlte Fernwartungsloesung trug
 * "dark:bg-cerulean-900/20", diese Klasse fehlte im gebauten CSS, und damit
 * blieb im Dunkelmodus der helle Hintergrund "bg-cerulean-50" stehen - heller
 * Text auf hellem Grund, Kontrast 1,02:1.
 *
 * Der Test prueft die Klassen mit Deckkraft-Angabe. Sie sind der Fallstrick,
 * weil jede Stufe (/10, /20, /30) eine eigene Regel braucht.
 */
test('jede Farbklasse mit Deckkraft steht auch im gebauten CSS', function () {
    $css = collect(glob(public_path('build/assets/*.css')))
        ->sortByDesc(fn ($d) => filemtime($d))
        ->first();

    expect($css)->not->toBeNull('Kein gebautes CSS gefunden - "npm run build" ausfuehren.');

    $inhalt = file_get_contents($css);
    $fehlend = [];

    // Rekursiv statt mit glob: "**" ist in PHP nur ein Verzeichnis tief, und
    // mehrere glob-Listen mit "+" zu verbinden ist eine Vereinigung nach
    // Schluesseln - dabei fallen genau die tief liegenden Views heraus.
    $views = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $eintrag) {
        if (str_ends_with($eintrag->getFilename(), '.blade.php')) {
            $views[] = $eintrag->getPathname();
        }
    }

    expect(count($views))->toBeGreaterThan(200, 'Zu wenige Views gefunden - stimmt der Pfad?');

    foreach ($views as $datei) {
        preg_match_all(
            '/(?:^|[\s"\'])((?:dark:)?(?:hover:)?(?:bg|text|border|ring|from|to|via)-[a-z-]+-\d{2,3}\/\d{1,3})/',
            file_get_contents($datei),
            $treffer
        );

        foreach (array_unique($treffer[1]) as $klasse) {
            // So steht die Klasse im CSS: Doppelpunkt und Schraegstrich escaped.
            $gesucht = str_replace([':', '/'], ['\:', '\/'], $klasse);

            if (! str_contains($inhalt, $gesucht)) {
                $fehlend[] = $klasse.'  ('.str_replace(resource_path('views/'), '', $datei).')';
            }
        }
    }

    expect(array_unique($fehlend))->toBe([],
        'Im CSS fehlen: '.implode(' | ', array_unique($fehlend)).' — "npm run build" ausfuehren und public/build mit committen.');
});

/**
 * Der Kartenkoerper laeuft in CSS-Spalten (x-card: columns-1 lg:columns-2
 * xl:columns-3). Eine Tabelle darin schrumpft nicht unter ihre Mindestbreite -
 * steht auf der Beschriftungsspalte "whitespace-nowrap", ist diese Mindestbreite
 * die volle Textbreite, und die Tabelle laeuft in die Nachbarspalte und aus der
 * Karte heraus.
 *
 * Genau das war zu sehen: In der Serverliste stand "10.10.30.7Hersteller" -
 * die IP-Tabelle lag ueber der Hardware-Tabelle daneben.
 */
test('die Karten-Tabellen halten ihre Beschriftungsspalte umbrechbar', function () {
    $komponenten = [
        'components/minitablecard.blade.php',
        'components/ipcard.blade.php',
        'components/credentialscard.blade.php',
        'cluster/_karte.blade.php',
    ];

    foreach ($komponenten as $datei) {
        $inhalt = file_get_contents(resource_path('views/'.$datei));

        // Nur die Tabellenzellen, nicht der erklaerende Kommentar darueber.
        preg_match_all('/<td[^>]*>/', $inhalt, $zellen);

        foreach ($zellen[0] as $zelle) {
            // Nicht expect()->not->toContain(): Das nimmt variadische
            // Suchbegriffe, keine Meldung - ein zusaetzlicher Text waere ein
            // zweiter Begriff und die Erwartung damit immer erfuellt.
            expect(str_contains($zelle, 'whitespace-nowrap'))->toBeFalse(
                "In {$datei} steht whitespace-nowrap in einer Tabellenzelle - ".
                'damit laeuft die Tabelle aus ihrer CSS-Spalte heraus.');
        }
    }
});
