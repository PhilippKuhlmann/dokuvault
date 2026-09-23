<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

/*
 * Der in der Seitenleiste gewählte Standort (session('site')) muss die
 * Gerätelisten filtern. Seit die Listen über App\Livewire\ObjektListe laufen,
 * wurde er dort nicht mehr angewandt - die Liste zeigte alle Geräte des Kunden,
 * egal welcher Standort gewählt war.
 */
test('die Geräteliste zeigt nur Geräte des gewählten Standorts', function () {
    $customer = Customer::factory()->create();
    $hamburg = Site::factory()->create(['customer_id' => $customer->id]);
    $muenchen = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    Server::create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'name' => 'SRV-HAMBURG', 'operating_system_id' => $os->id,
    ]);
    Server::create([
        'customer_id' => $customer->id, 'site_id' => $muenchen->id,
        'name' => 'SRV-MUENCHEN', 'operating_system_id' => $os->id,
    ]);

    $this->actingAs(userWithPermissions(['server_viewAny']));

    // Ohne Standort ("all"): beide.
    $this->withSession(['site' => 'all'])->get("/{$customer->slug}/server")
        ->assertSee('SRV-HAMBURG')
        ->assertSee('SRV-MUENCHEN');

    // Hamburg gewählt: nur Hamburg.
    $this->withSession(['site' => $hamburg->id])->get("/{$customer->slug}/server")
        ->assertSee('SRV-HAMBURG')
        ->assertDontSee('SRV-MUENCHEN');
});

test('ein fremder Standort filtert nicht (fällt auf alle zurück)', function () {
    $customer = Customer::factory()->create();
    $fremderKunde = Customer::factory()->create();
    $fremderStandort = Site::factory()->create(['customer_id' => $fremderKunde->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    Server::create([
        'customer_id' => $customer->id, 'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'name' => 'SRV-EIGEN', 'operating_system_id' => $os->id,
    ]);

    $this->actingAs(userWithPermissions(['server_viewAny']));

    // Ein Standort aus einem anderen Kunden darf die Liste nicht leerfiltern.
    $this->withSession(['site' => $fremderStandort->id])->get("/{$customer->slug}/server")
        ->assertSee('SRV-EIGEN');
});

test('ein aus der globalen Suche markierter Eintrag erscheint trotz anderem Standortfilter', function () {
    // Der Treffer der globalen Suche soll unabhängig vom in der Seitenleiste
    // gewählten Standort angesprungen werden - sonst versteckt der Filter genau
    // das Gerät, zu dem man springen wollte.
    $customer = Customer::factory()->create();
    $hamburg = Site::factory()->create(['customer_id' => $customer->id]);
    $muenchen = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    $srvHamburg = Server::create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'name' => 'SRV-HAMBURG', 'operating_system_id' => $os->id,
    ]);

    $this->actingAs(userWithPermissions(['server_viewAny']));

    // München gewählt, aber der Hamburger Server ist der Treffer.
    $this->withSession(['site' => $muenchen->id])
        ->get("/{$customer->slug}/server?highlight={$srvHamburg->id}")
        ->assertSee('SRV-HAMBURG')
        ->assertSee('data-highlight', false);

    // Without highlight the site filter applies as before: Munich hides Hamburg.
    $this->withSession(['site' => $muenchen->id])
        ->get("/{$customer->slug}/server")
        ->assertDontSee('SRV-HAMBURG');
});
