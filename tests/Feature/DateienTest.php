<?php

use App\Livewire\DateiListe;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('eine hochgeladene Datei merkt sich ihre Groesse', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_create', 'file_viewAny']));

    // Die Groesse beim Hochladen mitschreiben, statt sie spaeter je Zeile von
    // der Platte zu lesen - das waere ein Dateizugriff pro Zeile der Liste und
    // wuerde bei einer fehlenden Datei zusaetzlich krachen.
    $this->post("/{$customer->slug}/file", [
        'file' => UploadedFile::fake()->create('Vertrag.pdf', 1400),
        'name' => 'Wartungsvertrag 2026',
    ])->assertRedirect();

    $datei = File::where('name', 'Wartungsvertrag 2026')->sole();

    expect($datei->size)->toBeGreaterThan(0);
    expect($datei->groesseLesbar())->toContain('MB');
});

test('die Groesse wird lesbar dargestellt', function () {
    $faelle = [
        512 => '512 B',
        2048 => '2,0 KB',
        1468006 => '1,4 MB',
    ];

    foreach ($faelle as $bytes => $erwartet) {
        expect((new File(['size' => $bytes]))->groesseLesbar())->toBe($erwartet);
    }

    // Ohne gespeicherte Groesse bleibt die Angabe leer - "0 B" waere eine
    // Falschaussage.
    expect((new File)->groesseLesbar())->toBeNull();
});

test('die Dateiart bestimmt sich aus der Endung', function () {
    expect((new File(['extension' => 'pdf']))->art())->toBe('pdf');
    expect((new File(['extension' => 'PNG']))->art())->toBe('bild');
    expect((new File(['extension' => 'xlsx']))->art())->toBe('tabelle');
    expect((new File(['extension' => 'exe']))->art())->toBe('datei');
});

test('die Liste zeigt Titel, Groesse und Anzahl', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));

    File::create([
        'customer_id' => $customer->id, 'name' => 'Handbuch',
        'extension' => 'pdf', 'file_path' => 'x/handbuch.pdf', 'size' => 1468006,
    ]);

    $this->get("/{$customer->slug}/file")
        // Die Seite hatte vorher keine Kopfzeile - kein Titel, kein Zaehler.
        ->assertSee('Dateien')
        ->assertSee('1 Datei')
        ->assertSee('1,4 MB')
        ->assertSee('Handbuch.pdf');
});

test('der Papierkorb zeigt Anzahl und Art der Eintraege', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['see_hidden']));

    $domain = Domain::factory()->create([
        'customer_id' => $customer->id, 'name' => 'geloescht.de',
    ]);
    $domain->delete();

    $this->get(route('trash.index', $customer))
        ->assertSee('Papierkorb')
        ->assertSee('1 Eintrag')
        // Die Art steht als Etikett davor - in einer gemischten Liste sucht man
        // zuerst danach.
        ->assertSee('Domain')
        ->assertSee('geloescht.de');
});

test('der Papierkorb sagt es, wenn er die Liste kuerzt', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['see_hidden']));

    // Eine stille Kuerzung liest sich wie "mehr ist nicht da".
    Domain::factory()->count(101)->create(['customer_id' => $customer->id])
        ->each(fn ($d) => $d->delete());

    $this->get(route('trash.index', $customer))
        ->assertSee('höchstens 100')
        ->assertSee('101');
});

/** Ein paar Dateien mit verschiedenen Arten und Zeitpunkten. */
function dateienBestand(Customer $customer): void
{
    $bestand = [
        ['Wartungsvertrag 2026', 'pdf', 0, 5_000_000],
        ['Netzplan Zentrale', 'PNG', 3, 200_000],
        ['Inventarliste', 'xlsx', 40, 80_000],
        ['Altes Angebot', 'pdf', 200, 30_000],
        ['Konfigurations-Sicherung', 'cfg', 10, 1_000],
    ];

    foreach ($bestand as [$name, $endung, $tageAlt, $groesse]) {
        $datei = File::create([
            'customer_id' => $customer->id, 'name' => $name, 'extension' => $endung,
            'file_path' => 'x/'.$name.'.'.$endung, 'size' => $groesse,
        ]);

        // created_at steht in $guarded und laesst sich nicht mit create()
        // setzen - sonst bekaemen alle Dateien die aktuelle Zeit und der
        // Zeitraum-Filter waere nicht pruefbar.
        $datei->forceFill([
            'created_at' => now()->subDays($tageAlt),
            'updated_at' => now()->subDays($tageAlt),
        ])->save();
    }
}

test('die Suche greift auf Bezeichnung und Endung', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('suche', 'vertrag')
        ->assertSee('Wartungsvertrag 2026')
        ->assertDontSee('Inventarliste');

    // Auch die Endung ist ein Suchbegriff - "alle xlsx" ist eine echte Frage.
    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('suche', 'xlsx')
        ->assertSee('Inventarliste')
        ->assertDontSee('Wartungsvertrag');
});

test('der Zeitraum-Filter zeigt nur die letzten Tage', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('tage', 7)
        ->assertSee('Wartungsvertrag 2026')   // heute
        ->assertSee('Netzplan Zentrale')      // vor 3 Tagen
        ->assertDontSee('Inventarliste')      // vor 40 Tagen
        ->assertDontSee('Altes Angebot');     // vor 200 Tagen
});

test('der Art-Filter trennt die Dateitypen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('art', 'pdf')
        ->assertSee('Wartungsvertrag 2026')
        ->assertSee('Altes Angebot')
        ->assertDontSee('Netzplan Zentrale');

    // "Sonstige" ist der Rest - alles ohne benannte Art.
    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('art', 'datei')
        ->assertSee('Konfigurations-Sicherung')
        ->assertDontSee('Wartungsvertrag 2026');
});

test('der Art-Filter greift auch bei gross geschriebener Endung', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    // Die Endung wird gespeichert, wie sie hochgeladen wurde: "Netzplan.PNG"
    // ist ein Bild und darf im Filter nicht fehlen.
    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('art', 'bild')
        ->assertSee('Netzplan Zentrale');
});

test('die Sortierung laesst sich umstellen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    // Neueste zuerst ist die Vorgabe - vorher stand die aelteste Datei oben.
    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->assertSeeInOrder(['Wartungsvertrag 2026', 'Altes Angebot']);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('sortierung', 'aelteste')
        ->assertSeeInOrder(['Altes Angebot', 'Wartungsvertrag 2026']);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('sortierung', 'groesse')
        ->assertSeeInOrder(['Wartungsvertrag 2026', 'Konfigurations-Sicherung']);
});

test('die Filter lassen sich zuruecksetzen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('suche', 'vertrag')
        ->set('tage', 7)
        ->call('zuruecksetzen')
        ->assertSet('suche', '')
        ->assertSet('tage', 0)
        ->assertSee('Inventarliste');
});

test('ohne Treffer sagt die Liste, dass es an den Filtern liegt', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny']));
    dateienBestand($customer);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->set('suche', 'gibtesnicht')
        ->assertSee('Keine Datei passt zu den Filtern.');
});

test('die Datei eines fremden Kunden laesst sich nicht loeschen (IDOR)', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny', 'file_delete']));

    $fremdeDatei = File::create([
        'customer_id' => $fremder->id, 'name' => 'Fremd',
        'extension' => 'pdf', 'file_path' => 'x/fremd.pdf', 'size' => 100,
    ]);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->call('loeschen', $fremdeDatei->id)
        ->assertStatus(404);

    expect(File::find($fremdeDatei->id))->not->toBeNull();
});

test('loeschen entfernt Eintrag und Datei', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['file_viewAny', 'file_delete']));

    Storage::disk('local')->put('x/weg.pdf', 'inhalt');
    $datei = File::create([
        'customer_id' => $customer->id, 'name' => 'Weg',
        'extension' => 'pdf', 'file_path' => 'x/weg.pdf', 'size' => 6,
    ]);

    Livewire::test(DateiListe::class, ['customer' => $customer])
        ->call('loeschen', $datei->id);

    expect(File::find($datei->id))->toBeNull();
    Storage::disk('local')->assertMissing('x/weg.pdf');
});
