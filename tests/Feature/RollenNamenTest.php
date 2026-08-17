<?php

test('Rollennamen sind der Reihe nach vergeben und koennen nicht kollidieren', function () {
    // roles.name ist unique, die Factory zog aber Personennamen aus einem
    // endlichen Vorrat. Auf der CI kam derselbe Name zweimal ("Edgar
    // Rudolph") und riss die ganze Suite mit.
    //
    // Der Zufall laesst sich nicht erzwingen - 2000 Ziehungen kollidierten im
    // Versuch nicht. Deshalb prueft der Test die Eigenschaft, die den Fall
    // ausschliesst: Jeder Name traegt eine laufende Nummer, zwei Rollen
    // koennen also gar nicht gleich heissen.
    $namen = collect(range(1, 50))->map(fn () => App\Models\Role::factory()->create()->name);

    expect($namen->unique())->toHaveCount(50);

    foreach ($namen as $name) {
        expect($name)->toMatch('/^Rolle \d+ /');
    }
});
