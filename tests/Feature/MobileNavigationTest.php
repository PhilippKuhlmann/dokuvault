<?php

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Die Symbolreihe im Kopf (Dashboard, Kundensuche, Globale Suche, UTM,
 * Rustdesk) traegt "hidden md:flex" und verschwindet unterhalb von 768 px.
 * Ohne Ersatz kam man am Handy an keinen dieser Wege.
 */
test('die Seitenleiste fuehrt die Kopfzeilen-Wege fuer schmale Bildschirme', function () {
    // remote_search gehoert dazu: Der Verweis auf die Fernwartung steht seit
    // dem Rechte-Umbau hinter @can - ohne das Recht gibt es ihn nirgends.
    $this->actingAs(userWithPermissions(['site_viewAny', 'remote_search']));
    $customer = Customer::factory()->create();

    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    // Der Block traegt md:hidden - er ergaenzt die Kopfleiste, statt sie auf
    // grossen Bildschirmen zu verdoppeln.
    expect($inhalt)->toContain('md:hidden');

    foreach ([
        route('customer.search'),
        route('search.global'),
        route('search.remote'),
    ] as $ziel) {
        expect(substr_count($inhalt, 'href="'.$ziel.'"'))->toBeGreaterThanOrEqual(2, $ziel);
    }
});

test('Kunden sehen die internen Suchen auch am Handy nicht', function () {
    $customer = Customer::factory()->create();

    // isCustomer haengt an der gesetzten customer_id, nicht an einer festen
    // Rollen-Id. Die Rolle darf deshalb eine beliebige Nummer tragen - genau
    // darum ging es: Rollen entstehen im Adminbereich und bekommen dort
    // fortlaufende Nummern, nie 98 oder 99.
    $rolle = Role::factory()->create();
    $rolle->permissions()->attach(Permission::factory()->create(['name' => 'site_viewAny'])->id);

    $this->actingAs(User::factory()->create([
        'role_id' => $rolle->id,
        'customer_id' => $customer->id,
    ]));

    // Dieselbe Schranke wie in der Kopfleiste: @cannot('isCustomer').
    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    expect($inhalt)->not->toContain(route('search.global'));
    expect($inhalt)->not->toContain(route('search.remote'));
});

test('ein Kundennutzer sieht die internen Suchen auch mit einer frisch angelegten Rolle nicht', function () {
    // Der eigentliche Fall: Rollen entstehen im Adminbereich und bekommen dort
    // fortlaufende Nummern. Solange isCustomer nach der Id 98 oder 99 fragte,
    // war das Gate in jeder echten Installation falsch - der Kundennutzer bekam
    // Kundensuche und Fernwartung angeboten, beides fuehrte ins Leere.
    $customer = Customer::factory()->create();
    $rolle = Role::factory()->create(['name' => 'Kunde lesend']);
    $rolle->permissions()->attach(Permission::factory()->create(['name' => 'site_viewAny'])->id);

    expect($rolle->id)->not->toBe(98)->and($rolle->id)->not->toBe(99);

    $this->actingAs(User::factory()->create([
        'role_id' => $rolle->id,
        'customer_id' => $customer->id,
    ]));

    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    expect($inhalt)->not->toContain(route('search.global'));
    expect($inhalt)->not->toContain(route('search.remote'));
    expect($inhalt)->not->toContain(route('customer.search'));
});

test('ohne das Recht auf Fernwartung gibt es den Verweis nirgends', function () {
    // Gegenprobe zum ersten Test: Der Techniker sieht Kundensuche und globale
    // Suche, die Fernwartung aber nur mit remote_search - sonst zeigte das
    // Menue auf eine Seite, die mit 403 antwortet.
    $this->actingAs(userWithPermissions(['site_viewAny']));
    $customer = Customer::factory()->create();

    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    expect($inhalt)->toContain(route('search.global'));
    expect($inhalt)->not->toContain(route('search.remote'));
});
