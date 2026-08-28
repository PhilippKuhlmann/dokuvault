<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('eine Rolle mit nur einem Admin-Recht kommt nur an diesen Bereich', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));

    // Das ist der Fall, um den es geht: eine zweite Technikergruppe, die das
    // Protokoll sehen darf, aber keine Benutzer anlegt.
    $this->get(route('admin.activity.index'))->assertOk();

    $this->get(route('admin.user.index'))->assertForbidden();
    $this->get(route('admin.role.index'))->assertForbidden();
    $this->get(route('admin.customer.index'))->assertForbidden();
    $this->get(route('admin.setting.index'))->assertForbidden();
    $this->get(route('admin.trash'))->assertForbidden();
});

test('ohne jedes Admin-Recht bleibt der ganze Bereich zu', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    foreach ([
        'admin.dashboard', 'admin.activity.index', 'admin.user.index',
        'admin.role.index', 'admin.customer.index', 'admin.setting.index',
        'admin.trash', 'admin.operatingsystem.index', 'admin.service.index',
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
    $this->actingAs(userWithPermissions(['admin_trash', 'admin_activity', 'admin_operatingsystem']));

    $this->get(route('admin.trash'))->assertOk();
    $this->get(route('admin.activity.index'))->assertOk();
    // Das Recht traegt die EOL-Auswertung, nicht die Betriebssystem-Liste:
    // Die steht im Menue unter "Auswahlmenues" und gehoert dorthin.
    $this->get(route('admin.eol.index'))->assertOk();

    $this->get(route('admin.operatingsystem.index'))->assertForbidden();
    $this->get(route('admin.user.index'))->assertForbidden();
    $this->get(route('admin.service.index'))->assertForbidden();
});

test('die Auswahlmenues teilen sich ein Recht', function () {
    $this->actingAs(userWithPermissions(['admin_catalog']));

    // Alle vier Zeilen des Menuepunkts "Auswahlmenues" - die
    // Betriebssystem-Liste steht dort neben Diensten und Mail-Anbietern.
    $this->get(route('admin.operatingsystem.index'))->assertOk();
    $this->get(route('admin.service.index'))->assertOk();
    $this->get(route('admin.mailboxprovider.index'))->assertOk();
    $this->get(route('admin.rackcatalogitem.index'))->assertOk();

    // Die EOL-Auswertung hat ihren eigenen Menuepunkt und ihr eigenes Recht.
    $this->get(route('admin.eol.index'))->assertForbidden();
});

test('die Fernwartungs-Suche haengt am Recht, nicht an der Rolle', function () {
    // Vorher: nur Rolle 10. Eine zweite Technikergruppe kam nicht hinein.
    $this->actingAs(userWithPermissions(['remote_search']));
    $this->get(route('search.remote'))->assertOk();

    $this->actingAs(userWithPermissions(['admin_activity']));
    $this->get(route('search.remote'))->assertForbidden();
});

test('das Menue zeigt nur die erlaubten Eintraege', function () {
    $this->actingAs(userWithPermissions(['admin_trash']));

    $antwort = $this->get(route('admin.trash'));

    $antwort->assertSee('Papierkorb');
    // Ein Menuepunkt, der beim Klick 403 liefert, ist schlechter als keiner.
    $antwort->assertDontSee(route('admin.user.index'));
    $antwort->assertDontSee(route('admin.role.index'));
    $antwort->assertDontSee(route('admin.customer.index'));
});

test('die Rollenverwaltung zeigt die Admin-Rechte als eigenen Block', function () {
    // Die Admin-Rechte legt der Seeder an - die Migration, die sie fuer
    // bestehende Installationen nachtrug, ist mit dem Zusammenfassen entfallen.
    $this->seed(PermissionRoleSeeder::class);

    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $this->actingAs(User::factory()->create(['role_id' => $rolle->id]));

    $antwort = $this->get(route('admin.role.create'));

    $antwort->assertSee('Admin-Bereich');
    $antwort->assertSee('Gilt für die ganze Installation, nicht für einen einzelnen Kunden.');
    $antwort->assertSee('Papierkorb über alle Kunden');
});

test('jedes Admin-Recht hat einen Eintrag in der Rechtetabelle', function () {
    // Die Admin-Rechte legt der Seeder an - die Migration, die sie fuer
    // bestehende Installationen nachtrug, ist mit dem Zusammenfassen entfallen.
    $this->seed(PermissionRoleSeeder::class);

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

test('wer Admin-Rechte hat, findet den Weg dorthin', function () {
    $this->actingAs(userWithPermissions(['admin_trash']));

    // Ohne diesen Eintrag muesste man /admin von Hand tippen: Der Admin landet
    // beim Anmelden dort, ein Techniker mit Admin-Rechten nicht.
    $this->get(route('customer.search'))->assertSee(route('admin.dashboard'));
});

test('ohne Admin-Rechte gibt es den Weg nicht', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('customer.search'))->assertDontSee(route('admin.dashboard'));
});

test('das Admin-Dashboard zeigt nur erreichbare Kacheln', function () {
    $this->actingAs(userWithPermissions(['admin_trash', 'admin_activity']));

    $antwort = $this->get(route('admin.dashboard'));

    $antwort->assertOk();
    // Eine Kachel, die beim Klick 403 liefert, ist schlechter als keine - und
    // die Zahl darauf verraet etwas ueber einen Bereich, den der Benutzer
    // nicht sehen soll.
    $antwort->assertSee(route('admin.activity.index'));
    $antwort->assertDontSee(route('admin.user.index'));
    $antwort->assertDontSee(route('admin.role.index'));
    $antwort->assertDontSee(route('admin.customer.index'));
});

test('kein sichtbarer Link fuehrt in ein Verboten', function () {
    // Die Probe, die den Fehler gefunden hat: Die Betriebssystem-Liste stand im
    // Menue unter "Auswahlmenues", haengt aber an einem anderen Recht - wer nur
    // admin_catalog hatte, sah den Link und bekam 403. Dasselbe bei zwei Links
    // auf dem Dashboard.
    //
    // Deshalb keine Liste von Paaren, sondern die Frage selbst: Was der
    // Benutzer sieht, muss er auch oeffnen koennen.
    foreach (array_keys(config('custom.admin_permissions')) as $recht) {
        $nutzer = userWithPermissions([$recht]);

        $html = $this->actingAs($nutzer)->get(route('admin.dashboard'))->getContent();
        preg_match_all('#href="[^"]*(/admin/[a-z\-]+)"#', $html, $treffer);

        foreach (array_unique($treffer[1]) as $ziel) {
            $this->actingAs($nutzer)->get($ziel)->assertStatus(200, "Recht {$recht}: {$ziel} ist sichtbar, aber gesperrt");
        }
    }
});

/**
 * Adressen sind englisch, auch wenn die Oberflaeche deutsch ist.
 *
 * Eine Adresse wie /admin/allgemein faellt zwischen lauter englischen Pfaden
 * auf und laesst sich in einer Anleitung schlecht zitieren. Deutsch gehoert
 * in die Beschriftung, nicht in die Adresse.
 */
test('keine Route hat eine deutsche Adresse', function () {
    $deutsch = ['allgemein', 'papierkorb', 'protokoll', 'assistent', 'einstellung', 'benutzer', 'suche', 'stelle', 'schrank'];

    $treffer = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        // Die frueheren Adressen bleiben als Weiterleitung stehen, damit ein
        // Lesezeichen nicht ins Leere laeuft.
        ->reject(fn ($uri) => in_array($uri, [
            'admin/papierkorb', 'admin/protokoll-historie', '{customer}/assistent',
        ], true))
        ->filter(fn ($uri) => collect($deutsch)->contains(fn ($w) => str_contains(strtolower($uri), $w)))
        ->values()
        ->all();

    expect($treffer)->toBe([], 'Deutsche Adressen: '.implode(' | ', $treffer));
});

test('die frueheren deutschen Adressen leiten auf die englischen weiter', function () {
    $this->actingAs(userWithPermissions(['admin_trash']));

    $this->get('/admin/papierkorb')->assertRedirect('/admin/trash');
    $this->get('/admin/protokoll-historie')->assertRedirect('/admin/log-retention');
});
