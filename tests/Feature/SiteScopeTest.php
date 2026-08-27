<?php

use App\Models\Computer;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Site;

test('site_id eines fremden Kunden wird beim Anlegen abgelehnt (IDOR)', function () {
    $this->actingAs(userWithPermissions(['computer_create', 'computer_viewAny']));

    $customerA = Customer::factory()->create();
    $siteA = Site::factory()->create(['customer_id' => $customerA->id]);

    $customerB = Customer::factory()->create();
    $siteB = Site::factory()->create(['customer_id' => $customerB->id]);

    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    // Fremder Standort -> Validierungsfehler, nichts angelegt
    imModal('computer', $customerA, [
        'site_id' => $siteB->id,
        'name' => 'Hack',
        'operating_system_id' => $os->id,
    ])->assertHasErrors('form.site_id');

    expect(Computer::count())->toBe(0);

    // Eigener Standort -> erfolgreich
    imModal('computer', $customerA, [
        'site_id' => $siteA->id,
        'name' => 'OK',
        'operating_system_id' => $os->id,
    ])->assertHasNoErrors();

    expect(Computer::count())->toBe(1);
});

test('gespeicherter Standort eines fremden Kunden wird ignoriert (Liste bleibt gefüllt)', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));

    $customerA = Customer::factory()->create();
    $siteA = Site::factory()->create(['customer_id' => $customerA->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    $computer = Computer::create([
        'customer_id' => $customerA->id,
        'site_id' => $siteA->id,
        'name' => 'FindMich',
        'operating_system_id' => $os->id,
    ]);

    // Fremder Standort aus einem anderen Kunden in der Session (Stale-Zustand)
    $customerB = Customer::factory()->create();
    $siteB = Site::factory()->create(['customer_id' => $customerB->id]);
    session(['site' => $siteB->id]);

    // Der fremde Standort muss ignoriert werden -> Computer erscheint trotzdem
    $this->get("/{$customerA->slug}/computer")
        ->assertStatus(200)
        ->assertSee('FindMich');
});
