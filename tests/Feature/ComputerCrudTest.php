<?php

use App\Livewire\ObjektFormular;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Site;
use Livewire\Livewire;

function customerWithSiteAndOs(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    return [$customer, $site, $os];
}

test('Computer im Modal anlegen speichert den Datensatz', function () {
    $this->actingAs(userWithPermissions(['computer_create']));
    [$customer, $site, $os] = customerWithSiteAndOs();

    $response = imModal('computer', $customer, [
        'site_id' => $site->id,
        'name' => 'PC-Test',
        'manufacturer' => 'Dell',
        'operating_system_id' => $os->id,
    ]);

    // Das Modal leitet nicht um, es schliesst sich - die Liste aktualisiert
    // sich ueber ein Ereignis.
    $response->assertHasNoErrors();
    $this->assertDatabaseHas('computers', [
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'PC-Test',
        'manufacturer' => 'Dell',
    ]);
});

test('Computer anlegen scheitert ohne Pflichtfelder', function () {
    $this->actingAs(userWithPermissions(['computer_create']));
    [$customer, $site, $os] = customerWithSiteAndOs();

    imModal('computer', $customer, [
        'site_id' => $site->id,
        // name fehlt (required)
        'operating_system_id' => $os->id,
    ])->assertHasErrors('form.name');

    expect(Computer::count())->toBe(0);
});

test('Computer bearbeiten (update) ändert die Daten', function () {
    $this->actingAs(userWithPermissions(['computer_update']));
    [$customer, $site, $os] = customerWithSiteAndOs();

    $computer = Computer::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'Alt',
        'operating_system_id' => $os->id,
    ]);

    imModalBearbeiten('computer', $customer, $computer, [
        'site_id' => $site->id,
        'name' => 'Neu',
        'operating_system_id' => $os->id,
    ])->assertHasNoErrors();

    expect($computer->fresh()->name)->toBe('Neu');
});

test('Computer im Modal loeschen entfernt den Datensatz', function () {
    $this->actingAs(userWithPermissions(['computer_update', 'computer_delete']));
    [$customer, $site, $os] = customerWithSiteAndOs();

    $computer = Computer::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'Weg',
        'operating_system_id' => $os->id,
    ]);

    imModalLoeschen('computer', $customer, $computer);

    $this->assertSoftDeleted('computers', ['id' => $computer->id]);
});

test('Computer-Liste zeigt vorhandene Geräte', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));
    [$customer, $site, $os] = customerWithSiteAndOs();

    Computer::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'SichtbarerPC',
        'operating_system_id' => $os->id,
    ]);

    $this->get("/{$customer->slug}/computer")
        ->assertStatus(200)
        ->assertSee('SichtbarerPC');
});

test('ohne Berechtigung kein Anlegen möglich', function () {
    // Sehen darf er, anlegen nicht - ohne jedes Recht liesse sich die
    // Komponente gar nicht erst aufbauen, und der Test wuerde nur zeigen,
    // dass nichts geht.
    $this->actingAs(userWithPermissions(['computer_viewAny']));
    [$customer, $site, $os] = customerWithSiteAndOs();

    // Das Modal prueft das Recht beim Oeffnen: schon "neu" bricht ab.
    Livewire::test(ObjektFormular::class, ['typ' => 'computer', 'customer' => $customer])
        ->call('neu')
        ->assertForbidden();

    expect(Computer::count())->toBe(0);
});
