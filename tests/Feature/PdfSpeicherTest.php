<?php

use App\Http\Controllers\CustomerController;
use App\Models\Customer;
use App\Models\Role;

/**
 * Die PDF-Ausgabe hebt das Speicherlimit fuer ihren eigenen Aufruf an.
 *
 * Auf der Demo endete "PDF erstellen" in einer Fehlerseite, lokal nicht:
 * DomPDF braucht fuer einen Kunden mit 26 Servern, 46 VMs und 53 Computern
 * 136 MB Spitze, ein PHP mit den ueblichen 128 MB bricht dabei ab.
 */
test('das Speicherlimit wird aus der ini-Schreibweise richtig gelesen', function (string $ini, float $erwartet) {
    $controller = new CustomerController(new Customer);
    $methode = (new ReflectionClass($controller))->getMethod('speicherGrenzeInBytes');
    $methode->setAccessible(true);

    // Die Angabe wird uebergeben statt am laufenden Prozess gesetzt: Ein
    // ini_set auf 128M mitten in der Suite bricht sie ab, weil dann schon mehr
    // belegt ist.
    //
    // "128M" numerisch zu vergleichen ergaebe 128 - also immer "zu klein" -
    // und "-1" (unbegrenzt) waere die kleinste Zahl von allen.
    expect($methode->invoke($controller, $ini))->toBe($erwartet);
})->with([
    ['128M', 134217728.0],
    ['256M', 268435456.0],
    ['1G', 1073741824.0],
    ['-1', INF],
    ['67108864', 67108864.0],
]);

test('Rollennamen sind der Reihe nach vergeben und koennen nicht kollidieren', function () {
    // roles.name ist unique, die Factory zog aber Personennamen aus einem
    // endlichen Vorrat. Auf der CI kam derselbe Name zweimal ("Edgar
    // Rudolph") und riss die ganze Suite mit.
    //
    // Der Zufall laesst sich nicht erzwingen - 2000 Ziehungen kollidierten im
    // Versuch nicht. Deshalb prueft der Test die Eigenschaft, die den Fall
    // ausschliesst: Jeder Name traegt eine laufende Nummer, zwei Rollen
    // koennen also gar nicht gleich heissen.
    $namen = collect(range(1, 50))->map(fn () => Role::factory()->create()->name);

    expect($namen->unique())->toHaveCount(50);

    foreach ($namen as $name) {
        expect($name)->toMatch('/^Rolle \d+ /');
    }
});
