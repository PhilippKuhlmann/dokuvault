<?php

use App\Jobs\KundenPdfErzeugen;
use App\Models\Customer;
use App\Models\PdfExport;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * Die Dokumentation als PDF, aus der App bestellt.
 *
 * Je Nutzer, nicht je Kunde: Das PDF traegt jedes Kennwort des Kunden -
 * abholen darf es nur, wer es bestellt hat.
 */
function pdfToken(array $rechte): string
{
    return userWithPermissions($rechte)->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

test('bestellen legt einen Auftrag an und stellt ihn in die Warteschlange', function () {
    Queue::fake();
    $kunde = Customer::factory()->create();

    $antwort = test()->withToken(pdfToken(['create_pdf']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/pdf")
        ->assertOk();

    expect($antwort->json('auftrag.status'))->toBe(PdfExport::OFFEN);
    expect($antwort->json('auftrag.laeuft_noch'))->toBeTrue();
    expect($antwort->json('auftrag.fertig'))->toBeFalse();
    Queue::assertPushed(KundenPdfErzeugen::class);
});

/** Wer zweimal tippt, soll nicht zweimal denselben Berg Arbeit ausloesen. */
test('ein zweiter Auftrag entsteht nicht, solange einer läuft', function () {
    Queue::fake();
    $kunde = Customer::factory()->create();
    $token = pdfToken(['create_pdf']);

    $erster = test()->withToken($token)->postJson("/api/mobile/customers/{$kunde->slug}/pdf")->assertOk();
    $zweiter = test()->withToken($token)->postJson("/api/mobile/customers/{$kunde->slug}/pdf")->assertOk();

    expect($zweiter->json('auftrag.id'))->toBe($erster->json('auftrag.id'));
    expect(PdfExport::count())->toBe(1);
    Queue::assertPushed(KundenPdfErzeugen::class, 1);
});

test('ohne das Recht gibt es kein PDF', function () {
    $kunde = Customer::factory()->create();

    test()->withToken(pdfToken(['server_viewAny']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/pdf")
        ->assertForbidden();
});

test('der Stand ist null, solange nichts bestellt wurde', function () {
    $kunde = Customer::factory()->create();

    expect(test()->withToken(pdfToken(['create_pdf']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/pdf")
        ->assertOk()->json('auftrag'))->toBeNull();
});

test('die fertige Datei kommt heraus und steht im Protokoll', function () {
    Storage::fake('local');
    $kunde = Customer::factory()->create();
    $nutzer = userWithPermissions(['create_pdf']);
    $ablage = "pdf-exports/{$kunde->id}/dokumentation.pdf";
    Storage::disk('local')->put($ablage, '%PDF-1.4 Testinhalt');

    $auftrag = PdfExport::create([
        'customer_id' => $kunde->id,
        'user_id' => $nutzer->id,
        'status' => PdfExport::FERTIG,
        'path' => $ablage,
        'finished_at' => now(),
    ]);

    $token = $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;

    test()->withToken($token)
        ->get("/api/mobile/customers/{$kunde->slug}/pdf/{$auftrag->id}")
        ->assertOk()
        ->assertDownload();

    expect(Activity::where('event', 'pdf_heruntergeladen')->exists())->toBeTrue();
});

/** Das PDF traegt alle Kennwoerter - es gehoert dem, der es bestellt hat. */
test('ein fremder Auftrag lässt sich nicht abholen', function () {
    Storage::fake('local');
    $kunde = Customer::factory()->create();
    $ablage = "pdf-exports/{$kunde->id}/fremd.pdf";
    Storage::disk('local')->put($ablage, '%PDF-1.4');

    $auftrag = PdfExport::create([
        'customer_id' => $kunde->id,
        'user_id' => userWithPermissions(['create_pdf'])->id,
        'status' => PdfExport::FERTIG,
        'path' => $ablage,
        'finished_at' => now(),
    ]);

    test()->withToken(pdfToken(['create_pdf']))
        ->get("/api/mobile/customers/{$kunde->slug}/pdf/{$auftrag->id}")
        ->assertForbidden();
});

test('ein Auftrag eines anderen Kunden ist ein 404', function () {
    $kunde = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $nutzer = userWithPermissions(['create_pdf']);

    $auftrag = PdfExport::create([
        'customer_id' => $fremder->id,
        'user_id' => $nutzer->id,
        'status' => PdfExport::FERTIG,
        'path' => "pdf-exports/{$fremder->id}/x.pdf",
        'finished_at' => now(),
    ]);

    $token = $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;

    test()->withToken($token)
        ->get("/api/mobile/customers/{$kunde->slug}/pdf/{$auftrag->id}")
        ->assertNotFound();
});

/** Ein Auftrag ohne Datei auf der Platte: 410, nicht 500. */
test('eine aufgeräumte Datei meldet sich als weg', function () {
    Storage::fake('local');
    $kunde = Customer::factory()->create();
    $nutzer = userWithPermissions(['create_pdf']);

    $auftrag = PdfExport::create([
        'customer_id' => $kunde->id,
        'user_id' => $nutzer->id,
        'status' => PdfExport::FERTIG,
        'path' => "pdf-exports/{$kunde->id}/weg.pdf",
        'finished_at' => now(),
    ]);

    $token = $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;

    test()->withToken($token)
        ->get("/api/mobile/customers/{$kunde->slug}/pdf/{$auftrag->id}")
        ->assertStatus(410);
});

/**
 * Der Pfad kommt aus dem Auftrag, nicht aus einem Formular - trotzdem muss
 * er im Ordner dieses Kunden liegen. Ein Pfad, der herausführt, ist kein
 * Pfad dieses Auftrags.
 */
test('ein Pfad außerhalb des Kundenordners ist ein 404', function () {
    Storage::fake('local');
    $kunde = Customer::factory()->create();
    $nutzer = userWithPermissions(['create_pdf']);
    Storage::disk('local')->put('woanders/geheim.pdf', '%PDF-1.4');

    $auftrag = PdfExport::create([
        'customer_id' => $kunde->id,
        'user_id' => $nutzer->id,
        'status' => PdfExport::FERTIG,
        'path' => 'woanders/geheim.pdf',
        'finished_at' => now(),
    ]);

    $token = $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;

    test()->withToken($token)
        ->get("/api/mobile/customers/{$kunde->slug}/pdf/{$auftrag->id}")
        ->assertNotFound();
});
