<?php

use App\Models\Computer;
use App\Models\Concerns\HatBeschaffung;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

test('jede Hardware-Tabelle hat die Beschaffungsspalten', function () {
    // Der Filter ist derselbe wie in der Migration: Wo eine Seriennummer
    // erfasst wird, ist beschaffte Hardware dokumentiert - und dort muss auch
    // die Garantie hingehoeren.
    $ohne = [];

    foreach (config('custom.trashables') as $slug => [$klasse, $bezeichnung]) {
        $tabelle = (new $klasse)->getTable();

        if (! Schema::hasColumn($tabelle, 'serialNumber')) {
            continue;
        }

        foreach (['purchase_date', 'warranty_until', 'eol_date', 'supplier'] as $spalte) {
            if (! Schema::hasColumn($tabelle, $spalte)) {
                $ohne[] = "$tabelle.$spalte";
            }
        }

        if (! in_array(HatBeschaffung::class, class_uses_recursive($klasse), true)) {
            $ohne[] = "$klasse ohne HatBeschaffung";
        }
    }

    expect($ohne)->toBe([]);
});

test('das Anlegen speichert Kaufdatum, Garantie und Lieferant', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['server_create', 'server_viewAny']));

    $this->post(route('server.store', $customer), [
        'site_id' => $site->id,
        'name' => 'SRV-Beschaffung',
        'form_factor' => 'rack',
        'full_depth' => 1,
        'height_units' => 1,
        'operating_system_id' => $os->id,
        'purchase_date' => '2024-03-15',
        'warranty_until' => '2027-03-14',
        'eol_date' => '2031-03-14',
        'supplier' => 'Bechtle AG',
    ]);

    $server = Server::where('name', 'SRV-Beschaffung')->firstOrFail();

    // Ohne die Regel im Request faellt das Feld aus validated() heraus und wird
    // stillschweigend nicht gespeichert - genau das prueft dieser Test.
    expect($server->purchase_date?->format('Y-m-d'))->toBe('2024-03-15');
    expect($server->warranty_until?->format('Y-m-d'))->toBe('2027-03-14');
    expect($server->eol_date?->format('Y-m-d'))->toBe('2031-03-14');
    expect($server->supplier)->toBe('Bechtle AG');
});

test('die Geraeteliste zeigt die Garantie mit Restlaufzeit', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['server_viewAny']));

    Server::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id,
        'name' => 'SRV-Garantie',
        'warranty_until' => now()->addDays(10)->format('Y-m-d'),
        'supplier' => 'Bechtle AG',
    ]);

    $this->get(route('server.index', $customer))
        ->assertSee('Beschaffung')
        ->assertSee('Bechtle AG')
        // Ein Datum allein liesse jeden selbst rechnen.
        ->assertSee('in 10 Tagen');
});

test('abgelaufene Garantie wird als abgelaufen ausgewiesen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $server = Server::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id,
        'warranty_until' => now()->subDays(30)->format('Y-m-d'),
    ]);

    expect($server->garantieAbgelaufen())->toBeTrue();
    expect($server->garantieTage())->toBeLessThan(0);

    $this->get(route('server.index', $customer))->assertSee('abgelaufen');
});

test('das Dashboard sammelt ablaufende Garantien ueber alle Geraetearten', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions([
        'server_viewAny', 'computer_viewAny', 'licensesoftware_viewAny', 'certificate_viewAny',
    ]));

    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
        'name' => 'SRV-Bald', 'warranty_until' => now()->addDays(20)->format('Y-m-d'),
    ]);
    Computer::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
        'name' => 'PC-Abgelaufen', 'warranty_until' => now()->subDays(5)->format('Y-m-d'),
    ]);
    // Weit in der Zukunft: gehoert nicht auf die Karte.
    Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
        'name' => 'SRV-Lange', 'warranty_until' => now()->addYears(3)->format('Y-m-d'),
    ]);

    $this->get(route('customer.dashboard', $customer))
        ->assertSee('Ablaufende Garantien')
        ->assertSee('SRV-Bald')
        ->assertSee('PC-Abgelaufen')
        ->assertDontSee('SRV-Lange');
});

test('ohne Recht auf die Geraeteart erscheint sie nicht auf der Karte', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Computer::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id,
        'name' => 'PC-Verborgen', 'warranty_until' => now()->addDays(5)->format('Y-m-d'),
    ]);

    // Nur Server sehen duerfen, nicht Computer: Sonst stuenden auf dem
    // Dashboard Geraete, deren Liste sich nicht oeffnen laesst.
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('customer.dashboard', $customer))
        ->assertSee('Ablaufende Garantien')
        ->assertDontSee('PC-Verborgen');
});
