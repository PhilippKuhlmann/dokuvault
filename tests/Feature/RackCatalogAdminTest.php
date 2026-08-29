<?php

use App\Livewire\RackEditor;
use App\Livewire\RackKatalogVorschau;
use App\Models\Customer;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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
        'full_depth' => 1,
        'sort_order' => 200,
        'appearance' => 'blank',
    ])->assertRedirect('/admin/rackcatalogitem');

    $this->assertDatabaseHas('rack_catalog_items', [
        'name' => 'Lüftereinheit 1 HE',
        'height_units' => 1,
        'full_depth' => 1,
        'sort_order' => 200,
        'appearance' => 'blank',
    ]);
});

test('Anlegen scheitert ohne Bezeichnung und bei ungültiger Höhe', function () {
    $this->actingAs(adminUser());
    $vorher = RackCatalogItem::count();

    $this->post('/admin/rackcatalogitem/create', ['height_units' => 1, 'full_depth' => 1, 'appearance' => 'blank'])
        ->assertSessionHasErrors('name');

    // Acht Hoeheneinheiten sind die Grenze - ein Einbau, den es groesser
    // nicht gibt und den die Vorschau nicht mehr zeigen koennte.
    $this->post('/admin/rackcatalogitem/create', ['name' => 'Zu hoch', 'height_units' => 9, 'full_depth' => 1, 'appearance' => 'blank'])
        ->assertSessionHasErrors('height_units');

    $this->post('/admin/rackcatalogitem/create', ['name' => 'Zu niedrig', 'height_units' => 0, 'full_depth' => 1, 'appearance' => 'blank'])
        ->assertSessionHasErrors('height_units');

    // Unbekannte Darstellung wird abgelehnt - sie steuert, welche Zeichnung rendert
    $this->post('/admin/rackcatalogitem/create', ['name' => 'Krude', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'raumschiff'])
        ->assertSessionHasErrors('appearance');

    expect(RackCatalogItem::count())->toBe($vorher);
});

test('Bezeichnung muss eindeutig sein, der eigene Datensatz aber erlaubt', function () {
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();

    // Doppelte Bezeichnung wird abgelehnt
    $this->post('/admin/rackcatalogitem/create', ['name' => 'Rangierfeld', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'blank'])
        ->assertSessionHasErrors('name');

    // Eigener Datensatz mit unveraendertem Namen speichern geht
    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", [
        'name' => 'Rangierfeld',
        'height_units' => 2,
        'full_depth' => 1,
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
        'full_depth' => 1,
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
    $this->post('/admin/rackcatalogitem/create', ['name' => 'X', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'blank'])->assertStatus(403);
    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", ['name' => 'Y', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'blank'])->assertStatus(403);
    $this->delete("/admin/rackcatalogitem/{$eintrag->id}")->assertStatus(403);

    expect($eintrag->fresh()->name)->not->toBe('Y');
});

test('genau acht Höheneinheiten sind noch erlaubt', function () {
    $this->actingAs(adminUser());

    $this->post('/admin/rackcatalogitem/create', [
        'name' => 'Fachboden 8 HE', 'height_units' => 8, 'full_depth' => 1, 'appearance' => 'shelf',
    ])->assertSessionHasNoErrors();

    expect(RackCatalogItem::where('name', 'Fachboden 8 HE')->value('height_units'))->toBe(8);
});

// --- Eigenes Bild ---

test('ein hochgeladenes Bild wird abgelegt und am Eintrag vermerkt', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());

    $this->post('/admin/rackcatalogitem/create', [
        'name' => 'Bandlaufwerk', 'height_units' => 2, 'full_depth' => 1, 'appearance' => 'blank',
        'image' => UploadedFile::fake()->image('front.png'),
    ])->assertRedirect('/admin/rackcatalogitem');

    $eintrag = RackCatalogItem::where('name', 'Bandlaufwerk')->firstOrFail();

    expect($eintrag->image_path)->toStartWith(RackCatalogItem::BILDORDNER.'/');
    Storage::disk('local')->assertExists($eintrag->image_path);
});

test('ein neues Bild ersetzt das alte und laesst keine Datei liegen', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('alt.png')->store(RackCatalogItem::BILDORDNER, 'local')]);
    $alt = $eintrag->image_path;

    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", [
        'name' => 'Rangierfeld', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'cablering',
        'image' => UploadedFile::fake()->image('neu.png'),
    ])->assertRedirect('/admin/rackcatalogitem');

    $neu = $eintrag->fresh()->image_path;

    expect($neu)->not->toBe($alt);
    Storage::disk('local')->assertExists($neu);
    // Ohne das Aufraeumen bliebe zu jedem Wechsel eine Datei liegen, die
    // niemand mehr findet.
    Storage::disk('local')->assertMissing($alt);
});

test('Bild entfernen loescht Datei und Spalte, die Zeichnung tritt zurueck an ihre Stelle', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('alt.png')->store(RackCatalogItem::BILDORDNER, 'local')]);
    $pfad = $eintrag->image_path;

    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", [
        'name' => 'Rangierfeld', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'cablering',
        'image_remove' => 1,
    ])->assertRedirect('/admin/rackcatalogitem');

    expect($eintrag->fresh()->image_path)->toBeNull();
    Storage::disk('local')->assertMissing($pfad);
});

test('ohne neues Bild und ohne Haken bleibt das vorhandene stehen', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('alt.png')->store(RackCatalogItem::BILDORDNER, 'local')]);
    $pfad = $eintrag->image_path;

    // Gegenprobe zu den beiden Tests darueber: Ein gewoehnliches Speichern
    // darf das Bild nicht anfassen.
    $this->patch("/admin/rackcatalogitem/{$eintrag->id}", [
        'name' => 'Rangierfeld', 'height_units' => 2, 'full_depth' => 1, 'appearance' => 'cablering',
    ])->assertRedirect('/admin/rackcatalogitem');

    expect($eintrag->fresh()->image_path)->toBe($pfad);
    Storage::disk('local')->assertExists($pfad);
});

test('SVG wird als Bild abgelehnt', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());

    // Eine SVG-Datei darf Skript enthalten; von derselben Herkunft
    // ausgeliefert waere das ausfuehrbarer Code in einer Dokumentation,
    // in der Kennwoerter stehen.
    $this->post('/admin/rackcatalogitem/create', [
        'name' => 'Boeses Feld', 'height_units' => 1, 'full_depth' => 1, 'appearance' => 'blank',
        'image' => UploadedFile::fake()->create('front.svg', 4, 'image/svg+xml'),
    ])->assertSessionHasErrors('image');

    expect(RackCatalogItem::where('name', 'Boeses Feld')->exists())->toBeFalse();
});

test('Loeschen des Eintrags nimmt die Bilddatei mit', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());
    $eintrag = RackCatalogItem::where('name', 'Steckdosenleiste (PDU)')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('pdu.png')->store(RackCatalogItem::BILDORDNER, 'local')]);
    $pfad = $eintrag->image_path;

    $this->delete("/admin/rackcatalogitem/{$eintrag->id}")->assertRedirect('/admin/rackcatalogitem');

    Storage::disk('local')->assertMissing($pfad);
});

// --- Auslieferung des Bildes ---

test('das Bild geht an jeden Angemeldeten heraus, auch ohne Adminrechte', function () {
    Storage::fake('local');
    $eintrag = RackCatalogItem::where('name', 'LWL-Patchfeld')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('lwl.png')->store(RackCatalogItem::BILDORDNER, 'local')]);

    // Ein Techniker ohne Adminrechte sieht Racks - und damit das Bild darin.
    $this->actingAs(userWithPermissions(['rack_viewAny']));

    $this->get(route('rackcatalogitem.image', $eintrag))
        ->assertStatus(200)
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('ohne hinterlegtes Bild antwortet die Adresse mit 404', function () {
    $this->actingAs(adminUser());
    $ohne = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();

    $this->get(route('rackcatalogitem.image', $ohne))->assertStatus(404);
});

test('ohne Anmeldung kommt niemand an das Bild', function () {
    Storage::fake('local');
    $eintrag = RackCatalogItem::where('name', 'Rangierfeld')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('x.png')->store(RackCatalogItem::BILDORDNER, 'local')]);

    $this->get(route('rackcatalogitem.image', $eintrag))->assertRedirect('/login');
});

// --- Bild im Schrank ---

test('ein eingebautes Katalogelement zeigt sein Bild statt der Zeichnung', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack mit Foto', 'height_units' => 10,
    ]);

    $eintrag = RackCatalogItem::where('name', 'Patchfeld 24 Port')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('pf.png')->store(RackCatalogItem::BILDORDNER, 'local')]);

    $rack->items()->create([
        'position' => 1, 'height_units' => 1, 'name' => $eintrag->name,
        'appearance' => $eintrag->appearance, 'rack_catalog_item_id' => $eintrag->id,
    ]);

    $html = view('rack._rackview', ['rack' => $rack->load('items.device', 'items.catalogItem')])->render();

    expect($html)->toContain(route('rackcatalogitem.image', $eintrag));
    // Keine gezeichnete Blende mehr: Das Foto tritt an ihre Stelle, es steht
    // nicht daneben.
    expect(substr_count($html, '<svg'))->toBe(0);
});

test('ohne Bild wird weiter gezeichnet', function () {
    // Gegenprobe zum Test darueber.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack ohne Foto', 'height_units' => 10,
    ]);

    $eintrag = RackCatalogItem::where('name', 'Patchfeld 24 Port')->firstOrFail();
    $rack->items()->create([
        'position' => 1, 'height_units' => 1, 'name' => $eintrag->name,
        'appearance' => $eintrag->appearance, 'rack_catalog_item_id' => $eintrag->id,
    ]);

    $html = view('rack._rackview', ['rack' => $rack->load('items.device', 'items.catalogItem')])->render();

    expect(substr_count($html, '<svg'))->toBe(1);
});

test('das Einbauen merkt sich den Katalogeintrag - nur fuer sein Bild', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack', 'height_units' => 10,
    ]);
    $eintrag = RackCatalogItem::where('name', 'Blindplatte 2 HE')->firstOrFail();

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeCatalog', $eintrag->id, 1);

    expect($rack->items()->first()->rack_catalog_item_id)->toBe($eintrag->id);
});

// --- Vorschau ---

test('die Vorschau zeichnet in der eingestellten Hoehe', function () {
    $this->actingAs(adminUser());

    $komponente = Livewire::test(RackKatalogVorschau::class, ['appearance' => 'patchpanel', 'he' => 1]);

    // Ein Patchfeld mit 2 HE hat zwei Portreihen, keine gestreckte - die
    // Zeichnung muss deshalb tatsaechlich neu entstehen.
    $einzeilig = $komponente->html();
    $zweizeilig = $komponente->dispatch('rack-vorschau', appearance: 'patchpanel', he: 2)->html();

    expect($einzeilig)->not->toBe($zweizeilig);
    expect($komponente->get('he'))->toBe(2);
});

test('die Vorschau nimmt weder eine unbekannte Darstellung noch eine zu grosse Hoehe an', function () {
    $this->actingAs(adminUser());

    Livewire::test(RackKatalogVorschau::class, ['appearance' => 'shelf', 'he' => 2])
        ->dispatch('rack-vorschau', appearance: 'raumschiff', he: 99)
        ->assertSet('appearance', 'shelf')
        ->assertSet('he', 8);
});

test('das PDF nimmt das Foto statt der gezeichneten Blende', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack PDF', 'height_units' => 10,
    ]);

    $eintrag = RackCatalogItem::where('name', 'Blindplatte 2 HE')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('blind.png')->store(RackCatalogItem::BILDORDNER, 'local')]);

    $item = $rack->items()->create([
        'position' => 3, 'height_units' => 2, 'name' => $eintrag->name,
        'appearance' => $eintrag->appearance, 'rack_catalog_item_id' => $eintrag->id,
    ]);

    $svgDir = storage_path('app/pdf-svg/test-'.uniqid());
    File::ensureDirectoryExists($svgDir);

    try {
        $html = view('pdf._rack', [
            'rack' => $rack->load('items.device', 'items.catalogItem'),
            'svgDir' => $svgDir,
        ])->render();

        // Das Foto liegt bereits als Datei vor; der Umweg ueber eine
        // SVG-Datei entfaellt und darf deshalb nicht entstehen.
        expect($html)->toContain(Storage::disk('local')->path($eintrag->image_path));
        expect(file_exists($svgDir.'/item-'.$item->id.'.svg'))->toBeFalse();
    } finally {
        File::deleteDirectory($svgDir);
    }
});
