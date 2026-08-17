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
    $this->actingAs(userWithPermissions(['site_viewAny']));
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

    // isCustomer haengt an der Rollen-ID (98 lesend, 99 schreibend), nicht an
    // einer gesetzten customer_id - die Rolle muss also genau diese ID tragen.
    $rolle = Role::factory()->create(['id' => 99]);
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
