<?php

use App\Models\Customer;
use App\Models\PdfExport;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

// Security audit finding: deleting the user who ordered a PDF export dropped
// the row through the database cascade, but left the file - every password
// of a customer in plain text - on disk for good, past the retention.

function exportWithFile(Customer $customer, ?int $userId = null, string $name = 'export.pdf'): PdfExport
{
    $path = "pdf-exports/{$customer->id}/{$name}";
    Storage::disk('local')->put($path, 'ALLE-KENNWOERTER');

    return PdfExport::create([
        'customer_id' => $customer->id,
        'user_id' => $userId ?? userWithPermissions(['create_pdf'])->id,
        'status' => PdfExport::FERTIG,
        'path' => $path,
    ]);
}

function ageFile(string $path, int $hours): void
{
    touch(Storage::disk('local')->path($path), now()->subHours($hours)->getTimestamp());
}

beforeEach(fn () => Storage::fake('local'));

test('deleting a user deletes the files of their exports', function () {
    $user = userWithPermissions(['create_pdf']);
    $export = exportWithFile(Customer::factory()->create(), $user->id);

    $user->delete();

    expect(PdfExport::find($export->id))->toBeNull();
    Storage::disk('local')->assertMissing($export->path);
});

test('deleting an export row deletes its file', function () {
    $export = exportWithFile(Customer::factory()->create());

    $export->delete();

    Storage::disk('local')->assertMissing($export->path);
});

test('a customer in the trash keeps its exports, the final delete removes them', function () {
    $customer = Customer::factory()->create();
    $export = exportWithFile($customer);

    $customer->delete();
    Storage::disk('local')->assertExists($export->path);

    $customer->forceDelete();
    Storage::disk('local')->assertMissing($export->path);
});

test('the purge also removes files without a row once they are past the retention', function () {
    $customer = Customer::factory()->create();
    $hours = Setting::pdfStunden();

    $orphanOld = "pdf-exports/{$customer->id}/verwaist-alt.pdf";
    $orphanNew = "pdf-exports/{$customer->id}/verwaist-neu.pdf";
    Storage::disk('local')->put($orphanOld, 'X');
    Storage::disk('local')->put($orphanNew, 'X');
    ageFile($orphanOld, $hours + 2);

    $current = exportWithFile($customer, name: 'aktuell.pdf');
    ageFile($current->path, $hours + 2);

    $this->artisan('pdf:purge')->assertSuccessful();

    Storage::disk('local')->assertMissing($orphanOld);
    Storage::disk('local')->assertExists($orphanNew);
    // A file with a row is judged by the row's age, not the file's.
    Storage::disk('local')->assertExists($current->path);
});
