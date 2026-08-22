<?php

use App\Livewire\AdminApiToken;
use Livewire\Livewire;

test('ein Token laesst sich anlegen und wird genau einmal gezeigt', function () {
    $nutzer = userWithPermissions(['admin_apitoken']);
    $this->actingAs($nutzer);

    $test = Livewire::test(AdminApiToken::class)
        ->set('name', 'Agent Hamburg')
        ->call('anlegen');

    $klartext = $test->get('frischerToken');

    expect($klartext)->toBeString()->not->toBeEmpty();
    expect($nutzer->tokens()->count())->toBe(1);
    expect($nutzer->tokens()->first()->name)->toBe('Agent Hamburg');

    // Gespeichert wird nur der Hash - der Klartext steht nirgends in der
    // Tabelle. Wer ihn nicht mitnimmt, legt einen neuen an.
    $gespeichert = $nutzer->tokens()->first()->token;
    expect($klartext)->not->toContain($gespeichert);

    // Nach dem Verbergen ist er auch aus der Komponente weg.
    $test->call('verbergen')->assertSet('frischerToken', null);
});

test('ohne Bezeichnung entsteht kein Token', function () {
    $nutzer = userWithPermissions(['admin_apitoken']);
    $this->actingAs($nutzer);

    // Der Name ist beim Widerrufen das Einzige, woran sich ein Token erkennen
    // laesst - namenlos waere er wertlos.
    Livewire::test(AdminApiToken::class)
        ->set('name', '')
        ->call('anlegen')
        ->assertHasErrors('name');

    expect($nutzer->tokens()->count())->toBe(0);
});

test('ein Token laesst sich widerrufen', function () {
    $nutzer = userWithPermissions(['admin_apitoken']);
    $this->actingAs($nutzer);

    $nutzer->createToken('alt');
    $id = $nutzer->tokens()->first()->id;

    Livewire::test(AdminApiToken::class)->call('widerrufen', $id);

    expect($nutzer->tokens()->count())->toBe(0);
});

test('fremde Token lassen sich nicht widerrufen', function () {
    $fremder = userWithPermissions(['admin_apitoken']);
    $fremder->createToken('fremd');
    $fremdId = $fremder->tokens()->first()->id;

    $this->actingAs(userWithPermissions(['admin_apitoken']));

    // Die Id kommt aus dem Browser. Ohne den Umweg ueber die eigene Beziehung
    // liesse sich damit der Zugang eines anderen abschneiden.
    Livewire::test(AdminApiToken::class)->call('widerrufen', $fremdId);

    expect($fremder->tokens()->count())->toBe(1);
});

test('ohne das Recht bleibt die Seite zu', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));

    Livewire::test(AdminApiToken::class)->assertForbidden();
    $this->get(route('admin.apitoken'))->assertForbidden();
});

test('mit dem Recht steht der Menuepunkt da', function () {
    $this->actingAs(userWithPermissions(['admin_apitoken']));

    $this->get(route('admin.apitoken'))
        ->assertOk()
        ->assertSee('API-Token');
});

test('der Aufruf der Seite legt keinen Token an', function () {
    $nutzer = userWithPermissions(['admin_apitoken']);
    $this->actingAs($nutzer);

    // Vorher gab diese Adresse rohes JSON zurueck und erzeugte dabei jedes Mal
    // einen weiteren Token - ein Menuepunkt darauf haette beim Klicken Token
    // angelegt.
    $this->get(route('admin.apitoken'))->assertOk();
    $this->get(route('admin.apitoken'))->assertOk();

    expect($nutzer->tokens()->count())->toBe(0);
});
