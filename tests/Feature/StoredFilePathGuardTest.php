<?php

use App\Livewire\ObjektFormular;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

// Guards so the licence file_path hole (security audit) cannot come back
// through a new form type or a new download: a stored path is server-owned,
// and nothing is served from the shared disk without checking the folder.

/** Every form type with an upload, as [type, upload field]. */
function uploadFormTypes(): array
{
    return collect(config('forms'))
        ->map(fn ($settings) => collect($settings['felder'])->firstWhere('type', 'datei'))
        ->filter()
        ->map(fn ($field, $type) => [$type, $field])
        ->values()
        ->all();
}

test('no form request accepts the stored path of an upload', function () {
    $types = uploadFormTypes();
    expect($types)->not->toBeEmpty();

    foreach ($types as [$type, $field]) {
        $rules = (new (config("forms.$type.request")))->rules();

        expect(array_key_exists($field['pfad_feld'], $rules))
            ->toBeFalse("$type accepts {$field['pfad_feld']} from the client");
    }
});

test('no upload form type takes a stored path from the form or serves a foreign one', function () {
    Storage::fake('local');

    $own = Customer::factory()->create(['name' => 'Kunde A']);
    $other = Customer::factory()->create(['name' => 'Kunde B']);
    $foreign = 'pdf-exports/'.$other->id.'/2026-10-24_101500_dokumentation.pdf';
    $foreignToo = $other->slug.'/files/1700000000_vertrag.txt';
    Storage::disk('local')->put($foreign, 'FREMD');
    Storage::disk('local')->put($foreignToo, 'FREMD');

    $types = uploadFormTypes();
    $user = userWithPermissions(collect($types)->flatMap(fn ($t) => ["{$t[0]}_viewAny", "{$t[0]}_update"])->all());
    $user->update(['customer_id' => $own->id]);
    $this->actingAs($user);

    foreach ($types as [$type, $field]) {
        // A row written before the fix may still carry a foreign path.
        $object = config("forms.$type.model")::factory()->create([
            'customer_id' => $own->id,
            $field['pfad_feld'] => $foreign,
        ]);

        Livewire::test(ObjektFormular::class, ['typ' => $type, 'customer' => $own])
            ->call('bearbeiten', $type, $object->id)
            ->set("form.{$field['pfad_feld']}", $foreignToo)
            ->call('speichern')
            ->assertHasNoErrors();

        expect($object->fresh()->{$field['pfad_feld']})->toBe($foreign, "$type took the path from the form");

        if (Route::has("$type.download")) {
            $this->get(route("$type.download", [$own, $object]))->assertNotFound();
        }
    }
});

test('every download from storage checks the stored path', function () {
    // Downloads whose path never comes from a stored, form-editable column.
    $exceptions = [
        // Only a path the backup package itself lists on a backup disk.
        'Http/Controllers/BackupDownloadController.php',
        // PDF export: path written by the job; owner and requester are checked.
        'Http/Controllers/CustomerController.php',
        // The same export for the app. StoragePath::belongsTo does not fit -
        // the job writes under pdf-exports/{customer_id}/, not under the
        // slug. The controller checks that folder itself.
        'Http/Controllers/API/Mobile/PdfController.php',
    ];

    $unchecked = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $code = $file->getContents();
        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if (! preg_match('/Storage::(disk\([^)]*\)->)?download\(/', $code) || in_array($relative, $exceptions, true)) {
            continue;
        }

        if (! str_contains($code, 'StoragePath::belongsTo(')) {
            $unchecked[] = $relative;
        }
    }

    expect($unchecked)->toBe([], 'Storage downloads without StoragePath::belongsTo: '.implode(', ', $unchecked));
});
