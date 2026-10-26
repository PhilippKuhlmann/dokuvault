<?php

use App\Livewire\AdminPapierkorb;
use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Security audit finding: the licence form accepted file_path from the
// client, so a customer user could point their licence at another tenant's
// files (or the credential PDF exports), download them and delete them.

beforeEach(function () {
    Storage::fake('local');

    $this->own = Customer::factory()->create(['name' => 'Kunde A']);
    $this->other = Customer::factory()->create(['name' => 'Kunde B']);

    $this->foreignPdf = 'pdf-exports/'.$this->other->id.'/2026-10-24_101500_dokumentation.pdf';
    $this->foreignFile = $this->other->slug.'/files/1700000000_vertrag.txt';
    Storage::disk('local')->put($this->foreignPdf, 'FREMDE-ZUGANGSDATEN');
    Storage::disk('local')->put($this->foreignFile, 'FREMDER-VERTRAG');

    $user = userWithPermissions(['licensesoftware_viewAny', 'licensesoftware_create', 'licensesoftware_update']);
    $user->update(['customer_id' => $this->own->id]);
    $this->actingAs($user);
});

test('a file_path sent with the form is ignored', function () {
    Livewire::test(ObjektFormular::class, ['typ' => 'licensesoftware', 'customer' => $this->own])
        ->call('neu')
        ->set('form.name', 'Probe')
        ->set('form.file_path', $this->foreignPdf)
        ->call('speichern')
        ->assertHasNoErrors();

    $licence = LicenseSoftware::where('name', 'Probe')->sole();

    expect($licence->file_path)->toBeNull();
});

test('a file_path cannot be changed when editing', function () {
    $licence = LicenseSoftware::create(['customer_id' => $this->own->id, 'name' => 'Probe']);

    Livewire::test(ObjektFormular::class, ['typ' => 'licensesoftware', 'customer' => $this->own])
        ->call('bearbeiten', 'licensesoftware', $licence->id)
        ->set('form.file_path', $this->foreignFile)
        ->call('speichern')
        ->assertHasNoErrors();

    expect($licence->fresh()->file_path)->toBeNull();
});

test('a stored path outside the own licence folder is not downloaded', function () {
    // Rows written before the fix may still carry a foreign path.
    $licence = LicenseSoftware::create([
        'customer_id' => $this->own->id, 'name' => 'Alt', 'file_path' => $this->foreignPdf,
    ]);

    $this->get(route('licensesoftware.download', [$this->own, $licence]))->assertNotFound();

    $licence->update(['file_path' => $this->own->slug.'/licensesoftware/../../'.$this->foreignFile]);
    $this->get(route('licensesoftware.download', [$this->own, $licence]))->assertNotFound();
});

test('the own licence file still downloads', function () {
    $path = $this->own->slug.'/licensesoftware/1700000000_lizenz.pdf';
    Storage::disk('local')->put($path, 'EIGENE-LIZENZ');
    $licence = LicenseSoftware::create(['customer_id' => $this->own->id, 'name' => 'Eigen', 'file_path' => $path]);

    $this->get(route('licensesoftware.download', [$this->own, $licence]))->assertOk();
});

test('replacing the file does not delete a foreign stored path', function () {
    $licence = LicenseSoftware::create([
        'customer_id' => $this->own->id, 'name' => 'Alt', 'file_path' => $this->foreignFile,
    ]);

    Livewire::test(ObjektFormular::class, ['typ' => 'licensesoftware', 'customer' => $this->own])
        ->call('bearbeiten', 'licensesoftware', $licence->id)
        ->set('datei', UploadedFile::fake()->create('neu.pdf', 12))
        ->call('speichern')
        ->assertHasNoErrors();

    Storage::disk('local')->assertExists($this->foreignFile);
    expect($licence->fresh()->file_path)->toStartWith($this->own->slug.'/licensesoftware/');
});

test('emptying the trash does not delete a foreign stored path', function () {
    $licence = LicenseSoftware::create([
        'customer_id' => $this->own->id, 'name' => 'Alt', 'file_path' => $this->foreignPdf,
    ]);
    $licence->delete();

    $admin = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $this->actingAs(User::factory()->create(['role_id' => $admin->id]));

    Livewire::test(AdminPapierkorb::class)->call('loeschen', 'licensesoftware', $licence->id);

    expect(LicenseSoftware::withTrashed()->find($licence->id))->toBeNull();
    Storage::disk('local')->assertExists($this->foreignPdf);
});
