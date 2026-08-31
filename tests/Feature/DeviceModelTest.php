<?php

use App\Livewire\ObjektFormular;
use App\Livewire\RackEditor;
use App\Livewire\RackKatalogVorschau;
use App\Models\Customer;
use App\Models\DeviceModel;
use App\Models\NetworkSwitch;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\Site;
use App\Models\Ups;
use Database\Seeders\DeviceModelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Geraetemodelle sind kundenuebergreifend.
 *
 * Der Anlass: Wer bei einem Kunden eine APC-USV fotografiert, soll das Bild
 * bei jedem weiteren Kunden wiedersehen, bei dem dieselbe USV steht. Deshalb
 * traegt die Tabelle keine customer_id, und deshalb wird ueber Hersteller und
 * Modell gefunden statt ueber eine Verweisspalte - die Felder stehen an jedem
 * Geraet ohnehin, und bestehende Geraete bekommen ihr Bild rueckwirkend.
 */
function usvModellMitBild(string $hersteller = 'APC', string $modell = 'Smart-UPS 1500', int $he = 2): DeviceModel
{
    return DeviceModel::create([
        'device_type' => 'ups',
        'manufacturer' => $hersteller,
        'model' => $modell,
        'height_units' => $he,
        'image_path' => UploadedFile::fake()->image('usv.png')->store(DeviceModel::BILDORDNER, 'local'),
    ]);
}

/** Kunde mit Rack und einer eingebauten USV. */
function kundeMitUsvImRack(string $hersteller, ?string $modell): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack', 'height_units' => 10,
    ]);
    $usv = Ups::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'USV-01', 'manufacturer' => $hersteller, 'model' => $modell,
    ]);
    $rack->items()->create([
        'position' => 1, 'height_units' => 1,
        'device_type' => Ups::class, 'device_id' => $usv->id,
    ]);

    return [$customer, $rack, $usv];
}

function rackHtml(Rack $rack): string
{
    return view('rack._rackview', ['rack' => $rack->load('items.device', 'items.catalogItem')])->render();
}

// --- Abgleich ---

test('Schreibweise spielt keine Rolle, der Geraetetyp schon', function () {
    DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500']);

    expect(DeviceModel::fuer('ups', ' apc ', 'smart-ups  1500'))->not->toBeNull();
    expect(DeviceModel::fuer('ups', 'APC', 'Smart-UPS 1500'))->not->toBeNull();

    // Ohne den Typ im Schluessel traefe ein Switch gleichen Namens denselben Eintrag.
    expect(DeviceModel::fuer('networkswitch', 'APC', 'Smart-UPS 1500'))->toBeNull();
    expect(DeviceModel::fuer('ups', 'APC', 'Back-UPS 700'))->toBeNull();
    expect(DeviceModel::fuer('ups', '', 'Smart-UPS 1500'))->toBeNull();
});

test('die geladene Liste ist nach einer Aenderung nicht veraltet', function () {
    $modell = DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'X']);

    expect(DeviceModel::fuer('ups', 'APC', 'X')->height_units)->toBe(1);

    $modell->update(['height_units' => 4]);

    // Ohne das Verwerfen des Zwischenspeichers stuende hier weiter die 1.
    expect(DeviceModel::fuer('ups', 'APC', 'X')->height_units)->toBe(4);
});

// --- Der Kern: kundenuebergreifend ---

test('ein einmal hinterlegtes Bild erscheint bei jedem Kunden', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['rack_viewAny']));

    $modell = usvModellMitBild();

    [, $rackEins] = kundeMitUsvImRack('APC', 'Smart-UPS 1500');
    // Zweiter Kunde, andere Schreibweise, dasselbe Geraet.
    [, $rackZwei] = kundeMitUsvImRack('apc', 'SMART-UPS 1500');

    expect(rackHtml($rackEins))->toContain(route('devicemodel.image', $modell));
    expect(rackHtml($rackZwei))->toContain(route('devicemodel.image', $modell));
});

test('ohne passendes Modell wird weiter gezeichnet', function () {
    Storage::fake('local');
    usvModellMitBild();

    // Gegenprobe: anderes Modell desselben Herstellers.
    [, $rack] = kundeMitUsvImRack('APC', 'Back-UPS 700');

    $html = rackHtml($rack);

    expect($html)->not->toContain('device-model-image');
    expect(substr_count($html, '<svg'))->toBe(1);
});

test('das Foto des Katalogelements geht dem des Modells vor', function () {
    Storage::fake('local');
    $modell = usvModellMitBild();

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack', 'height_units' => 10,
    ]);

    $eintrag = RackCatalogItem::where('name', 'Blindplatte 1 HE')->firstOrFail();
    $eintrag->update(['image_path' => UploadedFile::fake()->image('blind.png')->store(RackCatalogItem::BILDORDNER, 'local')]);

    $rack->items()->create([
        'position' => 1, 'height_units' => 1, 'name' => $eintrag->name,
        'appearance' => $eintrag->appearance, 'rack_catalog_item_id' => $eintrag->id,
    ]);

    $html = rackHtml($rack);

    expect($html)->toContain(route('rackcatalogitem.image', $eintrag));
    expect($html)->not->toContain(route('devicemodel.image', $modell));
});

// --- Hoehe aus dem Modell ---

test('eine USV bringt ihre Hoehe aus dem Modell mit', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500', 'height_units' => 2]);

    [$customer, $rack] = kundeMitUsvImRack('APC', 'Smart-UPS 1500');
    $rack->items()->delete();
    $usv = Ups::where('customer_id', $customer->id)->firstOrFail();

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'ups', $usv->id, 1);

    // Eine USV fuehrt keine eigene Hoehe - ohne das Modell waere sie 1 HE.
    expect($rack->items()->first()->height_units)->toBe(2);
});

test('die eigene Hoehe des Geraets geht der des Modells vor', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    DeviceModel::create(['device_type' => 'server', 'manufacturer' => 'Dell', 'model' => 'R740', 'height_units' => 4]);

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack', 'height_units' => 10,
    ]);

    // Ein Server fuehrt seine Hoehe selbst - anders als eine USV.
    $server = rackTestServer($customer, $site);
    $server->update(['manufacturer' => 'Dell', 'model' => 'R740', 'height_units' => 2]);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'server', $server->id, 1);

    // Gegenprobe zum Test darueber: Das Modell darf die eigene Angabe nicht
    // ueberschreiben, sonst waere hier eine 4.
    expect($rack->items()->first()->height_units)->toBe(2);
});

// --- Auslieferung ---

test('das Bild geht an jeden Angemeldeten heraus, ohne Mandantenpruefung', function () {
    Storage::fake('local');
    $modell = usvModellMitBild();

    // Ein Nutzer eines beliebigen Kunden - das Modellfoto gehoert keinem.
    $this->actingAs(userWithPermissions(['rack_viewAny']));

    $this->get(route('devicemodel.image', $modell))
        ->assertStatus(200)
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('ohne Bild 404, ohne Anmeldung zur Anmeldung', function () {
    $ohne = DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'Eaton']);

    $this->get(route('devicemodel.image', $ohne))->assertRedirect('/login');

    $this->actingAs(adminUser());
    $this->get(route('devicemodel.image', $ohne))->assertStatus(404);
});

// --- Pflegeseite ---

test('Anlegen speichert Modell und Bild', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());

    $this->post('/admin/devicemodel/create', [
        'device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500',
        'height_units' => 2, 'full_depth' => 1,
        'image' => UploadedFile::fake()->image('usv.png'),
    ])->assertRedirect('/admin/devicemodel');

    $modell = DeviceModel::firstOrFail();

    expect($modell->manufacturer_key)->toBe('apc');
    expect($modell->model_key)->toBe('smart-ups 1500');
    Storage::disk('local')->assertExists($modell->image_path);
});

test('dasselbe Modell laesst sich nicht zweimal anlegen, auch nicht anders geschrieben', function () {
    $this->actingAs(adminUser());
    DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500']);

    $this->post('/admin/devicemodel/create', [
        'device_type' => 'ups', 'manufacturer' => ' apc ', 'model' => 'SMART-UPS  1500',
        'height_units' => 1, 'full_depth' => 1,
    ])->assertSessionHasErrors('manufacturer');

    // Anderer Typ ist etwas anderes und darf angelegt werden.
    $this->post('/admin/devicemodel/create', [
        'device_type' => 'networkswitch', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500',
        'height_units' => 1, 'full_depth' => 1,
    ])->assertSessionHasNoErrors();

    expect(DeviceModel::count())->toBe(2);
});

test('der eigene Datensatz blockiert sich beim Bearbeiten nicht selbst', function () {
    $this->actingAs(adminUser());
    $modell = DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500']);

    $this->patch("/admin/devicemodel/{$modell->id}", [
        'device_type' => 'ups', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500',
        'height_units' => 3, 'full_depth' => 1,
    ])->assertRedirect('/admin/devicemodel');

    expect($modell->fresh()->height_units)->toBe(3);
});

test('unbekannter Geraetetyp und zu grosse Hoehe werden abgelehnt', function () {
    $this->actingAs(adminUser());

    $this->post('/admin/devicemodel/create', ['device_type' => 'raumschiff', 'manufacturer' => 'X', 'height_units' => 1, 'full_depth' => 1])
        ->assertSessionHasErrors('device_type');

    $this->post('/admin/devicemodel/create', ['device_type' => 'ups', 'manufacturer' => 'X', 'height_units' => 9, 'full_depth' => 1])
        ->assertSessionHasErrors('height_units');

    expect(DeviceModel::count())->toBe(0);
});

test('Loeschen nimmt die Bilddatei mit, laesst die Geraete aber unberuehrt', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());
    $modell = usvModellMitBild();
    $pfad = $modell->image_path;
    [$customer, $rack, $usv] = kundeMitUsvImRack('APC', 'Smart-UPS 1500');

    $this->delete("/admin/devicemodel/{$modell->id}")->assertRedirect('/admin/devicemodel');

    Storage::disk('local')->assertMissing($pfad);
    expect($usv->fresh()->manufacturer)->toBe('APC');
    expect(substr_count(rackHtml($rack->fresh()), '<svg'))->toBe(1);
});

test('Nicht-Admins kommen an die Pflegeseite nicht heran', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    $modell = DeviceModel::create(['device_type' => 'ups', 'manufacturer' => 'APC']);

    $this->get('/admin/devicemodel')->assertStatus(403);
    $this->post('/admin/devicemodel/create', ['device_type' => 'ups', 'manufacturer' => 'Y', 'height_units' => 1, 'full_depth' => 1])->assertStatus(403);
    $this->delete("/admin/devicemodel/{$modell->id}")->assertStatus(403);

    expect(DeviceModel::count())->toBe(1);
});

// --- PDF ---

test('das PDF nimmt das Modellfoto', function () {
    Storage::fake('local');
    $modell = usvModellMitBild();
    [, $rack] = kundeMitUsvImRack('APC', 'Smart-UPS 1500');

    $svgDir = storage_path('app/pdf-svg/test-'.uniqid());
    File::ensureDirectoryExists($svgDir);

    try {
        $html = view('pdf._rack', [
            'rack' => $rack->load('items.device', 'items.catalogItem'),
            'svgDir' => $svgDir,
        ])->render();

        expect($html)->toContain(Storage::disk('local')->path($modell->image_path));
    } finally {
        File::deleteDirectory($svgDir);
    }
});

// --- Upload direkt im Geraeteformular ---

test('ein Bild aus dem Geraeteformular legt das Modell an und gilt sofort bei anderen Kunden', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['ups_create', 'admin_catalog']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(ObjektFormular::class, ['typ' => 'ups', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'USV-01')
        ->set('form.manufacturer', 'APC')
        ->set('form.model', 'Smart-UPS 1500')
        ->set('modellbild', UploadedFile::fake()->image('usv.png'))
        ->call('speichern')
        ->assertHasNoErrors();

    $modell = DeviceModel::firstOrFail();

    expect($modell->device_type)->toBe('ups');
    expect($modell->manufacturer)->toBe('APC');
    Storage::disk('local')->assertExists($modell->image_path);

    // Der Punkt der Uebung: ein zweiter Kunde, dieselbe USV, dasselbe Bild.
    [, $rackZwei] = kundeMitUsvImRack('apc', 'SMART-UPS 1500');
    expect(rackHtml($rackZwei))->toContain(route('devicemodel.image', $modell));
});

test('ein zweites Bild ersetzt das des Modells und laesst keine Datei liegen', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['ups_create', 'admin_catalog']));
    $modell = usvModellMitBild();
    $alt = $modell->image_path;

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(ObjektFormular::class, ['typ' => 'ups', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'USV-02')
        ->set('form.manufacturer', ' apc ')
        ->set('form.model', 'SMART-UPS  1500')
        ->set('modellbild', UploadedFile::fake()->image('neu.png'))
        ->call('speichern')
        ->assertHasNoErrors();

    // Kein zweiter Eintrag trotz anderer Schreibweise.
    expect(DeviceModel::count())->toBe(1);
    expect($modell->fresh()->image_path)->not->toBe($alt);
    Storage::disk('local')->assertMissing($alt);
});

test('die Hoehe eines vorhandenen Modells bleibt unangetastet', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['ups_create', 'admin_catalog']));
    $modell = usvModellMitBild(he: 2);

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(ObjektFormular::class, ['typ' => 'ups', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'USV-03')
        ->set('form.manufacturer', 'APC')
        ->set('form.model', 'Smart-UPS 1500')
        ->set('modellbild', UploadedFile::fake()->image('neu.png'))
        ->call('speichern');

    // Die zwei Hoeheneinheiten hat jemand im Adminbereich gesetzt - ein neu
    // angelegtes Geraet darf sie nicht stillschweigend umwerfen.
    expect($modell->fresh()->height_units)->toBe(2);
});

test('ohne Hersteller wird das Bild abgelehnt, statt im Nichts zu landen', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['ups_create', 'admin_catalog']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(ObjektFormular::class, ['typ' => 'ups', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'USV-04')
        ->set('modellbild', UploadedFile::fake()->image('usv.png'))
        ->call('speichern')
        ->assertHasErrors('modellbild');

    expect(DeviceModel::count())->toBe(0);
    expect(Ups::count())->toBe(0);
});

test('ohne admin_catalog gibt es das Feld nicht', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['ups_create']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $komponente = Livewire::test(ObjektFormular::class, ['typ' => 'ups', 'customer' => $customer])
        ->call('neu');

    $komponente->assertDontSee('Bild der Frontblende');

    // Und auch nicht am Formular vorbei: Ein untergeschobenes Bild wird
    // stillschweigend nicht zugeordnet - das Recht gilt fuer alle Kunden.
    $komponente->set('form.site_id', $site->id)
        ->set('form.name', 'USV-05')
        ->set('form.manufacturer', 'APC')
        ->set('form.model', 'Smart-UPS 1500')
        ->set('modellbild', UploadedFile::fake()->image('usv.png'))
        ->call('speichern')
        ->assertHasNoErrors();

    expect(Ups::count())->toBe(1);
    expect(DeviceModel::count())->toBe(0);
});

test('ein Typ ohne Rack kennt das Feld nicht', function () {
    $this->actingAs(userWithPermissions(['domain_create', 'admin_catalog']));
    $customer = Customer::factory()->create();

    // Gegenprobe zur Ableitung aus rack_device_types: Eine Domain hat keine
    // Frontblende.
    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->assertDontSee('Bild der Frontblende');
});

test('das hinterlegte Bild erscheint beim Tippen, noch vor dem Speichern', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['ups_create', 'admin_catalog']));
    $modell = usvModellMitBild();

    $customer = Customer::factory()->create();

    $komponente = Livewire::test(ObjektFormular::class, ['typ' => 'ups', 'customer' => $customer])
        ->call('neu');

    // Noch nichts eingetippt: kein Bild.
    $komponente->assertDontSee(route('devicemodel.image', $modell), false);

    // Hersteller allein reicht nicht - das Modell gehoert zum Schluessel.
    $komponente->set('form.manufacturer', 'APC')
        ->assertDontSee(route('devicemodel.image', $modell), false);

    // Beides da, wenn auch anders geschrieben: Jetzt weiss der Nutzer, dass es
    // schon ein Bild gibt und er keines hochladen muss.
    $komponente->set('form.model', 'smart-ups 1500')
        ->assertSee(route('devicemodel.image', $modell), false);
});

test('ein Typ ohne Frontblende ueberträgt seine Felder weiter erst beim Speichern', function () {
    $this->actingAs(userWithPermissions(['domain_create', 'admin_catalog']));
    $customer = Customer::factory()->create();

    // Gegenprobe: Die laufende Uebertragung haengt am Modellbild-Block, nicht
    // am Feldnamen - sonst zahlte jedes Formular der App eine Runde je
    // Tastenpause.
    $html = Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->html();

    expect($html)->not->toContain('wire:model.live.debounce.600ms');
});

// --- Vier Beispielmodelle mit eigener Zeichnung ---

test('der Seeder legt die vier Beispiele an und beim zweiten Lauf keines doppelt', function () {
    $this->seed(DeviceModelSeeder::class);
    $anzahl = DeviceModel::count();

    expect($anzahl)->toBe(4);
    expect(DeviceModel::whereNotNull('drawing')->count())->toBe(4);

    $this->seed(DeviceModelSeeder::class);

    expect(DeviceModel::count())->toBe($anzahl);
});

test('zu jeder Zeichnung gibt es eine Ansicht, und sie rendert in jeder Höhe', function () {
    foreach (array_keys(config('custom.rack_model_drawings')) as $schluessel) {
        foreach ([1, 2, 4] as $he) {
            $svg = view('components.rack.face', [
                'appearance' => 'blank', 'he' => $he, 'drawing' => $schluessel,
            ])->render();

            expect(str_contains($svg, 'viewBox="0 0 1086 '.(100 * $he).'"'))
                ->toBeTrue("Zeichnung {$schluessel} bei {$he} HE hat den falschen viewBox");
            expect(substr_count($svg, '<svg'))->toBe(1);
        }
    }
});

test('ein unbekannter Schlüssel lädt keine Ansicht, sondern zeichnet die Gattungsblende', function () {
    // Der Wert kommt aus der Datenbank und darf nie bestimmen, welche
    // Blade-Datei @include zieht.
    $svg = view('components.rack.face', [
        'appearance' => 'switch', 'he' => 1, 'drawing' => '../../../etc/passwd',
    ])->render();

    // data-port setzen nur die eigenen Zeichnungen; die Gattungsblende erkennt
    // man an ihren beiden SFP-Uplinks ganz rechts.
    expect(substr_count($svg, 'data-port='))->toBe(0);
    expect(str_contains($svg, 'x="900"'))->toBeTrue();
});

test('ein Gerät bekommt die Zeichnung seines Modells', function () {
    $this->seed(DeviceModelSeeder::class);

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack', 'height_units' => 10,
    ]);
    $switch = NetworkSwitch::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SW-01', 'manufacturer' => 'ubiquiti', 'model' => 'usw-pro-48-poe',
    ]);
    $rack->items()->create([
        'position' => 1, 'height_units' => 1,
        'device_type' => NetworkSwitch::class, 'device_id' => $switch->id,
    ]);

    // 48 Buchsen statt der ueblichen 24 - und zwar aus der eigenen Zeichnung,
    // die auch bei abweichender Schreibweise gefunden wird.
    expect(substr_count(rackHtml($rack), 'data-port='))->toBe(48);
});

test('die Vorschau übernimmt die eigene Zeichnung schon beim Öffnen', function () {
    $this->actingAs(adminUser());

    // Sie kam einmal als vierter von sieben Werten an einer Methode mit drei
    // Parametern an und ging dabei verloren - das Formular zeigte dann die
    // Blende des Gerätetyps statt der eigenen Zeichnung.
    Livewire::test(RackKatalogVorschau::class, [
        'appearance' => 'switch', 'he' => 1, 'drawing' => 'unifi-usw-pro-48-poe',
    ])
        ->assertSet('drawing', 'unifi-usw-pro-48-poe')
        ->assertSee('data-port="48"', false);
});
