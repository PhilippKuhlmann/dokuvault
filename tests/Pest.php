<?php

use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Legt einen authentifizierbaren Nutzer mit den angegebenen Permissions an
 * (ohne customer_id => Zugriff auf alle Kunden, wie ein Techniker).
 */
function userWithPermissions(array $names): User
{
    // Explizite hohe ID, damit die Rolle nie versehentlich die Admin- (1)
    // oder Techniker-Rolle (10) per Auto-Increment erwischt.
    $role = Role::factory()->create([
        'id' => (Role::max('id') ?? 0) + 100,
    ]);

    foreach ($names as $name) {
        // Vorhandene wiederverwenden: Seit die Rechte des Admin-Bereichs per
        // Migration entstehen, gibt es sie schon - ein zweites Anlegen bricht
        // am UNIQUE-Index auf permissions.name.
        $permission = Permission::where('name', $name)->first()
            ?? Permission::factory()->create(['name' => $name]);

        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * Einen Eintrag ueber das Modal anlegen oder bearbeiten.
 *
 * Seit Listen und Formulare als Livewire laufen, gibt es keine /create- und
 * /edit-Seiten mehr. Die Regeln kommen weiterhin aus demselben FormRequest -
 * geprueft wird also dieselbe Fachlichkeit, nur ueber den Weg, den es noch gibt.
 *
 * @param  array<string, mixed>  $werte
 */
function imModal(string $typ, Customer $customer, array $werte, ?int $id = null): Testable
{
    $formular = Livewire\Livewire::test(ObjektFormular::class, ['typ' => $typ, 'customer' => $customer]);

    $id === null
        ? $formular->call('neu')
        : $formular->call('bearbeiten', $typ, $id);

    foreach ($werte as $feld => $wert) {
        $formular->set("form.$feld", $wert);
    }

    return $formular->call('speichern');
}

/**
 * Einen vorhandenen Eintrag ueber das Modal bearbeiten.
 *
 * Anders als beim frueheren PATCH sind die uebrigen Felder dabei schon
 * gefuellt - das Modal laedt den Eintrag. Uebergeben wird also nur, was sich
 * aendert, so wie es auch ein Mensch taete.
 *
 * @param  array<string, mixed>  $werte
 */
function imModalBearbeiten(string $typ, Customer $customer, $objekt, array $werte): Testable
{
    return imModal($typ, $customer, $werte, $objekt->id);
}

/**
 * Das gerenderte Modal als HTML - fuer Zusicherungen, die frueher an der
 * /create- oder /edit-Seite hingen.
 */
function modalHtml(string $typ, Customer $customer, ?int $id = null): string
{
    $formular = Livewire\Livewire::test(ObjektFormular::class, ['typ' => $typ, 'customer' => $customer]);

    $id === null
        ? $formular->call('neu')
        : $formular->call('bearbeiten', $typ, $id);

    return $formular->html();
}

/**
 * Einen Eintrag ueber das Modal loeschen - der Weg, den es seit der
 * Umstellung auf Livewire noch gibt.
 */
function imModalLoeschen(string $typ, Customer $customer, $objekt): Testable
{
    return Livewire\Livewire::test(ObjektFormular::class, ['typ' => $typ, 'customer' => $customer])
        ->call('bearbeiten', $typ, $objekt->id)
        ->call('loeschen');
}

/**
 * Mitten in einem Test den angemeldeten Nutzer wechseln.
 *
 * actingAs() tauscht nur den Nutzer aus, nicht die Sitzung. Seit
 * AuthenticateSession in der Middleware-Gruppe "web" haengt (siehe
 * App\Http\Kernel), traegt die Sitzung aber den Kennwort-Hash des vorigen
 * Nutzers - der naechste Aufruf wuerde deshalb abgemeldet statt beantwortet.
 * Im Browser gibt es diesen Fall nicht: dort fuehrt jeder Nutzerwechsel ueber
 * Abmelden und Anmelden, und beides raeumt die Sitzung auf.
 */
function nutzerWechseln(User $nutzer): void
{
    test()->flushSession();
    test()->actingAs($nutzer);
}
