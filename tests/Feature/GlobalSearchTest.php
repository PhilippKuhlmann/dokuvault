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

test('der Suchbegriff kommt weiterhin aus der URL', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));
    [$customer] = searchFixture();

    // #[Url] statt $queryString: ?search=... muss unveraendert greifen, sonst
    // brechen geteilte Links auf ein Suchergebnis.
    Livewire::withQueryParams(['search' => 'PC-Suchtest'])
        ->test(GlobalSearch::class)
        ->assertSet('search', 'PC-Suchtest')
        ->assertSee('PC-Suchtest');
});

/*
 * Je Objektart werden hoechstens zwanzig Treffer gezeigt. Vorher wurde dort
 * stillschweigend abgeschnitten: Wer den einundzwanzigsten Server suchte,
 * hielt ihn fuer nicht vorhanden. Der Hinweis ist die einzige Stelle, an der
 * das sichtbar wird - faellt er weg, faellt es niemandem auf.
 */
test('mehr als zwanzig Treffer einer Art sagen, dass sie gekürzt sind', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));

    $customer = Customer::factory()->create(['name' => 'Vielkunde']);
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    foreach (range(1, 21) as $nummer) {
        Computer::create([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'name' => 'PC-VIEL-'.str_pad($nummer, 2, '0', STR_PAD_LEFT),
            'operating_system_id' => $os->id,
        ]);
    }

    Livewire::test(GlobalSearch::class)
        ->set('search', 'PC-VIEL')
        ->assertSee(__('weitere vorhanden'))
        ->assertSee(__('Je Objektart höchstens zwanzig gezeigt — Suchbegriff verfeinern.'));
});

test('genau zwanzig Treffer behaupten nicht, es gäbe mehr', function () {
    $this->actingAs(userWithPermissions(['computer_viewAny']));

    $customer = Customer::factory()->create(['name' => 'Genaukunde']);
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    foreach (range(1, 20) as $nummer) {
        Computer::create([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'name' => 'PC-GENAU-'.str_pad($nummer, 2, '0', STR_PAD_LEFT),
            'operating_system_id' => $os->id,
        ]);
    }

    Livewire::test(GlobalSearch::class)
        ->set('search', 'PC-GENAU')
        ->assertSee('PC-GENAU-20')
        ->assertDontSee(__('weitere vorhanden'));
});
