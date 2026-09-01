<?php

use App\Jobs\KundenPdfErzeugen;
use App\Livewire\PdfExportStatus;
use App\Models\Customer;
use App\Models\PdfExport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Die PDF-Ausgabe laeuft im Hintergrund.
 *
 * Gemessen: 370 MB und 15 Sekunden bei einem Kunden mit 40 Servern, 90 VMs und
 * 160 Computern - im Request war das erst eine Fehlerseite (Speicher) und dann
 * ein Rennen gegen das Zeitlimit.
 */
test('der Klick legt einen Auftrag an statt zu rendern', function () {
    Queue::fake();
    $this->actingAs(userWithPermissions(['create_pdf']));
    $customer = Customer::factory()->create();

    Livewire::test(PdfExportStatus::class, ['customer' => $customer])
        ->call('starten')
        ->assertDispatched('hinweis');

    Queue::assertPushed(KundenPdfErzeugen::class);
    expect(PdfExport::where('customer_id', $customer->id)->sole()->status)->toBe(PdfExport::OFFEN);
});

test('ein zweiter Klick erzeugt keinen zweiten Auftrag', function () {
    Queue::fake();
    $this->actingAs(userWithPermissions(['create_pdf']));
    $customer = Customer::factory()->create();

    // Wer zweimal klickt, soll nicht zweimal denselben Berg Arbeit ausloesen.
    $komponente = Livewire::test(PdfExportStatus::class, ['customer' => $customer]);
    $komponente->call('starten')->call('starten');

    expect(PdfExport::where('customer_id', $customer->id)->count())->toBe(1);
    Queue::assertPushed(KundenPdfErzeugen::class, 1);
});

test('der Job erzeugt die Datei und haelt den Stand fest', function () {
    $this->actingAs(userWithPermissions(['create_pdf']));
    $customer = Customer::factory()->create();

    $export = PdfExport::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(), 'status' => PdfExport::OFFEN,
    ]);

    (new KundenPdfErzeugen($export->id))->handle();

    $export->refresh();

    expect($export->status)->toBe(PdfExport::FERTIG);
    expect($export->size)->toBeGreaterThan(1000);
    expect(Storage::disk('local')->exists($export->path))->toBeTrue();
    // Der Zwischenordner fuer die Rack-SVG muss wieder weg sein.
    expect(Storage::disk('local')->directories('pdf-svg'))->toBe([]);
});

test('nur der Besteller darf das PDF laden', function () {
    $this->actingAs($besteller = userWithPermissions(['create_pdf']));
    $customer = Customer::factory()->create();

    $export = PdfExport::create([
        'customer_id' => $customer->id, 'user_id' => $besteller->id, 'status' => PdfExport::OFFEN,
    ]);
    (new KundenPdfErzeugen($export->id))->handle();

    $this->get(route('customer.pdf-download', [$customer, $export]))->assertOk();

    // Das PDF enthaelt alle Zugangsdaten des Kunden - eine ID in der Adresse
    // darf nicht genuegen.
    $anderer = User::factory()->create(['role_id' => $besteller->role_id]);
    nutzerWechseln($anderer);
    $this->get(route('customer.pdf-download', [$customer, $export]))
        ->assertForbidden();
});

test('ein unfertiger Auftrag liefert keine Datei', function () {
    $this->actingAs($nutzer = userWithPermissions(['create_pdf']));
    $customer = Customer::factory()->create();

    $export = PdfExport::create([
        'customer_id' => $customer->id, 'user_id' => $nutzer->id, 'status' => PdfExport::OFFEN,
    ]);

    $this->get(route('customer.pdf-download', [$customer, $export]))->assertNotFound();
});

test('der Aufraeumbefehl loescht alte Ausgaben mitsamt Datei', function () {
    $this->actingAs($nutzer = userWithPermissions(['create_pdf']));
    $customer = Customer::factory()->create();

    Storage::disk('local')->put('pdf-exports/test.pdf', 'inhalt');

    $alt = PdfExport::create([
        'customer_id' => $customer->id, 'user_id' => $nutzer->id,
        'status' => PdfExport::FERTIG, 'path' => 'pdf-exports/test.pdf',
    ]);
    $alt->forceFill(['created_at' => now()->subHours(PdfExport::AUFBEWAHRUNG_STUNDEN + 1)])->save();

    $this->artisan('pdf:aufraeumen')->assertSuccessful();

    expect(PdfExport::find($alt->id))->toBeNull();
    expect(Storage::disk('local')->exists('pdf-exports/test.pdf'))->toBeFalse();
});
