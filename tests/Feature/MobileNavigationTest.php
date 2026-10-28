<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Server;
use App\Models\Site;
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

test('customer accounts see the global search on mobile too, but not the customer search', function () {
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

    // Same rule as in the header: only the customer search is internal.
    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    expect($inhalt)->toContain(route('search.global'));
    expect($inhalt)->not->toContain(route('customer.search'));
    expect($inhalt)->not->toContain(route('search.remote'));
});

test('a customer account gets home and global search, but not the customer search', function () {
    // Roles are created in the admin area with running ids. While isCustomer
    // asked for id 98 or 99, the gate was false in every real installation.
    // The customer search stays hidden (one customer only); home and the
    // global search, which stays within the customer, are there.
    $customer = Customer::factory()->create();
    $rolle = Role::factory()->create(['name' => 'Kunde lesend']);
    $rolle->permissions()->attach(Permission::factory()->create(['name' => 'site_viewAny'])->id);

    expect($rolle->id)->not->toBe(98)->and($rolle->id)->not->toBe(99);

    $this->actingAs(User::factory()->create([
        'role_id' => $rolle->id,
        'customer_id' => $customer->id,
    ]));

    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    expect($inhalt)->toContain('href="'.route('customer.dashboard', $customer).'"');
    expect($inhalt)->toContain(route('search.global'));
    expect($inhalt)->not->toContain(route('search.remote'));
    expect($inhalt)->not->toContain(route('customer.search'));
    // The search page has its own header: home leads back to the dashboard.
    $suche = $this->get(route('search.global'))->assertOk()->getContent();
    expect($suche)->toContain('href="'.route('customer.dashboard', $customer).'"');
    expect($suche)->not->toContain(route('customer.search'));
});

test('a customer account with remote_search only finds its own devices', function () {
    $eigener = Customer::factory()->create(['name' => 'Eigen GmbH']);
    $fremder = Customer::factory()->create(['name' => 'Fremd GmbH']);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    foreach ([[$eigener, 'SRV-EIGEN'], [$fremder, 'SRV-FREMD']] as [$kunde, $name]) {
        Server::factory()->create([
            'customer_id' => $kunde->id,
            'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
            'operating_system_id' => $os->id,
            'name' => $name, 'remoteID' => '123456789', 'remotePassword' => 'geheim',
        ]);
    }

    $rolle = Role::factory()->create(['name' => 'Kunde Fernwartung']);
    $rolle->permissions()->attach(Permission::factory()->create(['name' => 'remote_search'])->id);
    // The search lists device names, so it also needs the right to see them.
    foreach (['server_viewAny', 'vm_viewAny'] as $recht) {
        $rolle->permissions()->attach((Permission::where('name', $recht)->first() ?? Permission::factory()->create(['name' => $recht]))->id);
    }
    $this->actingAs(User::factory()->create(['role_id' => $rolle->id, 'customer_id' => $eigener->id]));

    expect($this->get(route('customer.dashboard', $eigener))->getContent())->toContain(route('search.remote'));

    // Matches both by name - only the own one may come back.
    $this->get(route('search.remote', ['search' => 'SRV']))->assertOk()
        ->assertSee('SRV-EIGEN')
        ->assertDontSee('SRV-FREMD')
        ->assertDontSee('Fremd GmbH');
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
