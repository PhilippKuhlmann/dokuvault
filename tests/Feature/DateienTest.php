<?php

use App\Models\Customer;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
