<?php

use App\Models\RackCatalogItem;
use App\Models\Role;
use App\Models\User;

function adminUser(): User
{
    $role = Role::factory()->create(['id' => Role::IS_ADMIN]);

    return User::factory()->create(['role_id' => $role->id]);
}

test('Migration legt die Standard-Katalogelemente an', function () {
    expect(RackCatalogItem::count())->toBe(11);
    expect(RackCatalogItem::where('name', 'Blindplatte 3 HE')->value('height_units'))->toBe(3);
});

test('Liste zeigt die Katalogelemente', function () {
    $this->actingAs(adminUser());

    $this->get('/admin/rackcatalogitem')
        ->assertStatus(200)
        ->assertSee('Patchfeld 24 Port')
        ->assertSee('Rack-Katalog');
});

test('Anlegen speichert und leitet zur Liste', function () {
    $this->actingAs(adminUser());

    $this->post('/admin/rackcatalogitem/create', [
        'name' => 'Lüftereinheit 1 HE',
        'height_units' => 1,
        'sort_order' => 200,
        'appearance' => 'blank',
    ])->assertRedirect('/admin/rackcatalogitem');

    $this->assertDatabaseHas('rack_catalog_items', [
        'name' => 'Lüftereinheit 1 HE',
        'height_units' => 1,
        'sort_order' => 200,
        'appearance' => 'blank',
    ]);
});

test('Anlegen scheitert ohne Bezeichnung und bei ungültiger Höhe', function () {
    $this->actingAs(adminUser());
    $vorher = RackCatalogItem::count();

    $this->post('/admin/rackcatalogitem/create', ['height_units' => 1, 'appearance' => 'blank'])
        ->assertSessionHasErrors('name');

    $this->post('/admin/rackcatalogitem/create', ['name' => 'Zu hoch', 'height_units' => 43, 'appearance' => 'blank'])
        ->assertSessionHasErrors('height_units');

    $this->post('/admin/rackcatalogitem/create', ['name' => 'Zu niedrig', 'height_units' => 0, 'appearance' => 'blank'])
        ->assertSessionHasErrors('height_units');

    // Unbekannte Darstellung wird abgelehnt - sie steuert, welche Zeichnung rendert
    $this->post('/admin/rackcatalogitem/create', ['name' => 'Krude', 'height_units' => 1, 'appearance' => 'raumschiff'])
        ->assertSessionHasErrors('appearance');

    expect(RackCatalogItem::count())->toBe($vorher);
});

test('Bezeichnung muss eindeutig sein, der eigene Datensatz aber erlaubt', function () {
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();

    // Doppelte Bezeichnung wird abgelehnt
    $this->post('/admin/rackcatalogitem/create', ['name' => 'Rangierfeld', 'height_units' => 1, 'appearance' => 'blank'])
        ->assertSessionHasErrors('name');

    // Eigener Datensatz mit unveraendertem Namen speichern geht
    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", [
        'name' => 'Rangierfeld',
        'height_units' => 2,
        'appearance' => 'cablering',
    ])->assertRedirect('/admin/rackcatalogitem');

    expect($eintrag->fresh()->height_units)->toBe(2);
});

test('Bearbeiten ändert die Daten', function () {
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'LWL-Patchfeld')->firstOrFail();

    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", [
        'name' => 'LWL-Patchfeld 24 Port',
        'height_units' => 2,
        'sort_order' => 25,
        'appearance' => 'patchpanel',
    ])->assertRedirect('/admin/rackcatalogitem');

    expect($eintrag->fresh()->name)->toBe('LWL-Patchfeld 24 Port');
    expect($eintrag->fresh()->height_units)->toBe(2);
});

test('Löschen entfernt den Eintrag', function () {
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Steckdosenleiste (PDU)')->firstOrFail();

    $this->delete("/admin/rackcatalogitem/{$eintrag->id}")
        ->assertRedirect('/admin/rackcatalogitem');

    $this->assertDatabaseMissing('rack_catalog_items', ['id' => $eintrag->id]);
});

test('Nicht-Admins kommen an den Katalog nicht heran', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    $eintrag = RackCatalogItem::first();

    $this->get('/admin/rackcatalogitem')->assertStatus(403);
    $this->post('/admin/rackcatalogitem/create', ['name' => 'X', 'height_units' => 1, 'appearance' => 'blank'])->assertStatus(403);
    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", ['name' => 'Y', 'height_units' => 1, 'appearance' => 'blank'])->assertStatus(403);
    $this->delete("/admin/rackcatalogitem/{$eintrag->id}")->assertStatus(403);

    expect($eintrag->fresh()->name)->not->toBe('Y');
});
