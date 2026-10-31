<?php

/**
 * Eloquent ruft bootX() und initializeX() nur auf, wenn X genau der
 * Trait-Name ist. Passt der Name nicht, passiert nichts - kein Fehler, keine
 * Warnung, nur eine Eigenschaft, die ploetzlich nicht mehr gecastet wird.
 *
 * Genau das ist beim Umbenennen von HatBeschaffung -> HasProcurement
 * passiert: initializeHatBeschaffung() blieb stehen, warranty_until kam als
 * String aus der Datenbank und das Dashboard fiel um.
 */
test('jeder boot- und initialize-Hook traegt den Namen seines Traits', function () {
    $dateien = array_merge(
        glob(app_path('*/Concerns/*.php')),
        glob(app_path('*/*/Concerns/*.php')),
    );

    expect($dateien)->not->toBeEmpty('Keine Trait-Dateien gefunden - der Test prueft nichts.');

    $funde = [];

    foreach ($dateien as $datei) {
        $trait = basename($datei, '.php');
        $quelle = file_get_contents($datei);

        preg_match_all('/function (boot|initialize)([A-Za-z0-9_]+)\s*\(/', $quelle, $treffer, PREG_SET_ORDER);

        foreach ($treffer as [, $art, $name]) {
            if ($name !== $trait) {
                $funde[] = "$trait: {$art}{$name}() statt {$art}{$trait}()";
            }
        }
    }

    expect($funde)->toBe([], 'Hooks, die Eloquent nie aufruft: '.implode(', ', $funde));
});
