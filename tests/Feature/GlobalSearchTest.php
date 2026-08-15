<?php

use App\Livewire\GlobalSearch;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function searchFixture(): array
{
    $customer = Customer::factory()->create(['name' => 'Suchkunde']);
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    $computer = Computer::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'PC-Suchtest',
        'serialNumber' => 'SN-FINDME-01',
        'operating_system_id' => $os->id,
    ]);

    // Adressen stehen nur noch im Block "Weitere IP-Adressen".
    $computer->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.99.88.77']);

    return [$customer, $computer];
}

test('findet Geräte nach Name, IP und Seriennummer', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));
    [$customer] = searchFixture();

    foreach (['PC-Suchtest', '10.99.88.77', 'SN-FINDME-01'] as $term) {
        Livewire::test(GlobalSearch::class)
            ->set('search', $term)
            ->assertSee('PC-Suchtest')
            ->assertSee('Suchkunde');
    }
});

test('Typ ohne viewAny-Permission erscheint nicht', function () {
    $this->actingAs(userWithPermissions([])); // keine Rechte
    searchFixture();

    Livewire::test(GlobalSearch::class)
        ->set('search', 'PC-Suchtest')
        ->assertDontSee('PC-Suchtest');
});

test('Kunden-Nutzer sieht nur Objekte des eigenen Kunden', function () {
    [$customerA] = searchFixture();

    // Nutzer gehört zu einem ANDEREN Kunden
    $customerB = Customer::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => 'computer_viewAny']);
    $role->permissions()->attach($permission->id);
    $user = User::factory()->create(['role_id' => $role->id, 'customer_id' => $customerB->id]);
    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'PC-Suchtest')
        ->assertDontSee('PC-Suchtest');
});

test('die Treffer lassen sich ohne die View pruefen', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));
    [$customer] = searchFixture();

    // groups() ist eine computed property - abrufbar ohne Rendern.
    $groups = Livewire::test(GlobalSearch::class)->set('search', 'PC-Suchtest')->get('groups');

    expect($groups)->toHaveCount(1);
    expect($groups->first()['slug'])->toBe('computer');
    expect($groups->first()['results']->pluck('name')->all())->toBe(['PC-Suchtest']);

    // Unter zwei Zeichen wird gar nicht gesucht.
    expect(Livewire::test(GlobalSearch::class)->set('search', 'P')->get('groups'))->toBeEmpty();
});
