<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Ein Benutzer mit genau diesen Rechten - und sonst keinen.
 */
function nutzerMitRechten(array $namen): User
{
    $rolle = Role::factory()->create();

    foreach ($namen as $name) {
        $recht = Permission::where('name', $name)->first()
            ?? tap(new Permission)->forceFill(['name' => $name, 'description' => $name])->save();

        $rolle->assignPermission(Permission::where('name', $name)->first());
    }

    return User::factory()->create(['role_id' => $rolle->id]);
}

test('eine Rolle mit nur einem Admin-Recht kommt nur an diesen Bereich', function () {
    $this->actingAs(nutzerMitRechten(['admin_activity']));

    // Das ist der Fall, um den es geht: eine zweite Technikergruppe, die das
    // Protokoll sehen darf, aber keine Benutzer anlegt.
    $this->get(route('admin.activity.index'))->assertOk();

    $this->get(route('admin.user.index'))->assertForbidden();
    $this->get(route('admin.role.index'))->assertForbidden();
    $this->get(route('admin.customer.index'))->assertForbidden();
    $this->get(route('admin.setting.index'))->assertForbidden();
    $this->get(route('admin.papierkorb'))->assertForbidden();
});

test('ohne jedes Admin-Recht bleibt der ganze Bereich zu', function () {
    $this->actingAs(nutzerMitRechten(['server_viewAny']));

    foreach ([
        'admin.dashboard', 'admin.activity.index', 'admin.user.index',
        'admin.role.index', 'admin.customer.index', 'admin.setting.index',
        'admin.papierkorb', 'admin.operatingsystem.index', 'admin.service.index',
    ] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

test('der Admin darf alles, auch ohne angehakte Rechte', function () {
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $rolle->permissions()->detach();

    $this->actingAs(User::factory()->create(['role_id' => $rolle->id]));

    // Die Absicherung gegen das Aussperren: Wer in der Rollenverwaltung
    // versehentlich alles abwaehlt, kommt trotzdem noch hinein.
    $this->get(route('admin.role.index'))->assertOk();
    $this->get(route('admin.user.index'))->assertOk();
    $this->get(route('admin.activity.index'))->assertOk();
});

test('mehrere Rechte lassen sich frei zusammenstellen', function () {
    $this->actingAs(nutzerMitRechten(['admin_trash', 'admin_activity', 'admin_operatingsystem']));

    $this->get(route('admin.papierkorb'))->assertOk();
    $this->get(route('admin.activity.index'))->assertOk();
    $this->get(route('admin.operatingsystem.index'))->assertOk();
    // Support-Ende gehoert zu den Betriebssystemen.
    $this->get(route('admin.eol.index'))->assertOk();

    $this->get(route('admin.user.index'))->assertForbidden();
    $this->get(route('admin.service.index'))->assertForbidden();
});

test('die Kataloge teilen sich ein Recht', function () {
    $this->actingAs(nutzerMitRechten(['admin_catalog']));

    $this->get(route('admin.service.index'))->assertOk();
    $this->get(route('admin.mailboxprovider.index'))->assertOk();
    $this->get(route('admin.rackcatalogitem.index'))->assertOk();

    // Betriebssysteme sind ein eigener Menuepunkt und ein eigenes Recht.
    $this->get(route('admin.operatingsystem.index'))->assertForbidden();
});

test('die Fernwartungs-Suche haengt am Recht, nicht an der Rolle', function () {
    // Vorher: nur Rolle 10. Eine zweite Technikergruppe kam nicht hinein.
    $this->actingAs(nutzerMitRechten(['remote_search']));
    $this->get(route('search.remote'))->assertOk();

    $this->actingAs(nutzerMitRechten(['admin_activity']));
    $this->get(route('search.remote'))->assertForbidden();
});

test('das Menue zeigt nur die erlaubten Eintraege', function () {
    $this->actingAs(nutzerMitRechten(['admin_trash']));

    $antwort = $this->get(route('admin.papierkorb'));

    $antwort->assertSee('Papierkorb');
    // Ein Menuepunkt, der beim Klick 403 liefert, ist schlechter als keiner.
    $antwort->assertDontSee(route('admin.user.index'));
    $antwort->assertDontSee(route('admin.role.index'));
    $antwort->assertDontSee(route('admin.customer.index'));
});

test('die Rollenverwaltung zeigt die Admin-Rechte als eigenen Block', function () {
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $this->actingAs(User::factory()->create(['role_id' => $rolle->id]));

    $antwort = $this->get(route('admin.role.create'));

    $antwort->assertSee('Admin-Bereich');
    $antwort->assertSee('Gilt für die ganze Installation, nicht für einen einzelnen Kunden.');
    $antwort->assertSee('Papierkorb ueber alle Kunden');
});

test('jedes Admin-Recht hat einen Eintrag in der Rechtetabelle', function () {
    foreach (array_keys(array_merge(config('custom.admin_permissions'), config('custom.extra_permissions'))) as $name) {
        // Ohne Zeile in der Tabelle liesse sich das Recht in der
        // Rollenverwaltung nicht vergeben - das Gate liefe dann immer ins Leere.
        expect(Permission::where('name', $name)->exists())
            ->toBeTrue("Recht {$name} fehlt in der Tabelle");
    }
});

test('der Admin gilt nicht als Kunde', function () {
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $rolle->id]);

    // Die Rollen-Gates fragen nach der Rolle, nicht nach einem Recht. Als
    // Gate::before pauschal true lieferte, galt der Admin als "Kunde nur
    // lesen" - und der Neu-Knopf verschwand aus jeder Liste, weil er hinter
    // @cannot('isCustomerR') steht.
    expect(Gate::forUser($admin)->allows('isCustomerR'))->toBeFalse();
    expect(Gate::forUser($admin)->allows('isCustomer'))->toBeFalse();
    expect(Gate::forUser($admin)->allows('isCustomerRW'))->toBeFalse();

    expect(Gate::forUser($admin)->allows('isAdmin'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('admin_user'))->toBeTrue();
});

test('die Benutzerliste bietet Anlegen und Loeschen an', function () {
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $rolle->id]);
    $anderer = User::factory()->create(['role_id' => $rolle->id, 'name' => 'Zweiter Zugang']);

    $antwort = $this->actingAs($admin)->get(route('admin.user.index'));

    $antwort->assertOk();
    // Auf das Formularziel geprueft, nicht auf die blosse Adresse: Die
    // Bearbeiten-URL enthaelt dieselbe Zeichenfolge und endet nur auf /edit.
    $antwort->assertSee('action="'.route('admin.user.destroy', $anderer).'"', false);

    // Sich selbst kann niemand entfernen - sonst stuende man vor einer
    // Anmeldemaske ohne Konto.
    $antwort->assertDontSee('action="'.route('admin.user.destroy', $admin).'"', false);
});
