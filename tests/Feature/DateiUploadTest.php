<?php

use App\Livewire\AdminAllgemein;
use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\Setting;
use App\Support\Dateiname;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function lizenzFormular(Customer $kunde)
{
    return Livewire::test(ObjektFormular::class, ['typ' => 'licenseaccess', 'customer' => $kunde])
        ->set('form.name', 'Lizenz')
        ->set('form.key', 'ABC-123');
}

// --- Pfadmanipulation -------------------------------------------------------

test('die Bezeichnung kann den Ablageordner nicht verlassen', function () {
    // Nachgestellt, bevor es den Schutz gab: Mit dieser Bezeichnung landete
    // die Datei im Verzeichnis eines anderen Kunden - und überschrieb, was
    // dort lag.
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['licenseaccess_create']));

    $kunde = Customer::factory()->create();
    $fremd = Customer::factory()->create();

    lizenzFormular($kunde)
        ->set('form.file_name', '../../../../'.$fremd->slug.'/files/eingeschleust')
        ->set('datei', UploadedFile::fake()->create('x.pdf', 10))
        ->call('speichern');

    $dateien = Storage::disk('local')->allFiles();

    expect($dateien)->toHaveCount(1)
        ->and($dateien[0])->toStartWith($kunde->slug.'/licenseaccess/')
        ->and($dateien[0])->not->toContain('..')
        // Und beim fremden Kunden liegt nichts. Sein Name steckt jetzt
        // harmlos im Dateinamen - das ist Text, kein Pfad.
        ->and(Storage::disk('local')->allFiles($fremd->slug))->toBeEmpty();
});

test('auch der Dateiname aus dem Browser kommt nicht durch', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['licenseaccess_create']));
    $kunde = Customer::factory()->create();

    // Ohne Bezeichnung greift der Name, den der Browser mitschickt.
    lizenzFormular($kunde)
        ->set('form.file_name', '')
        ->set('datei', UploadedFile::fake()->create('../../ausbruch.pdf', 10))
        ->call('speichern');

    $dateien = Storage::disk('local')->allFiles();

    expect($dateien[0] ?? '')->toStartWith($kunde->slug.'/licenseaccess/')
        ->and($dateien[0] ?? '')->not->toContain('..');
});

// --- Größe und Inhaltstyp ---------------------------------------------------

test('eine zu große Datei landet nicht auf der Platte', function () {
    // Vorher gab es in der Anwendung überhaupt keine Grenze - 60 MB gingen
    // durch. Abgewiesen wird sie jetzt schon von Livewire, beim temporären
    // Upload; geprüft wird deshalb das Ergebnis und nicht die Meldung.
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['licenseaccess_create']));
    $kunde = Customer::factory()->create();

    lizenzFormular($kunde)
        ->set('form.file_name', 'gross')
        ->set('datei', UploadedFile::fake()->create('gross.pdf', config('custom.datei_max_kb') + 100))
        ->call('speichern');

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('Livewire und die Anwendung nennen dieselbe Grenze', function () {
    // Livewire lässt ohne Angabe höchstens 12 MB durch, die Anwendung erlaubte
    // 20 - über ein gewöhnliches Formular ging das auch, über ein Modal nicht.
    // Zwei Grenzen, von denen niemand die kleinere kannte.
    expect(config('livewire.temporary_file_upload.rules'))
        ->toContain('max:'.config('custom.datei_max_kb'));
});

test('eine ausführbare Datei wird abgewiesen', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['licenseaccess_create']));
    $kunde = Customer::factory()->create();

    lizenzFormular($kunde)
        ->set('form.file_name', 'skript')
        ->set('datei', UploadedFile::fake()->createWithContent('boese.php', '<?php echo 1;'))
        ->call('speichern')
        ->assertHasErrors('datei');

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('Gegenprobe: eine erlaubte Datei geht durch', function () {
    Storage::fake('local');
    $this->actingAs(userWithPermissions(['licenseaccess_create']));
    $kunde = Customer::factory()->create();

    lizenzFormular($kunde)
        ->set('form.file_name', 'Lizenzurkunde 2026')
        ->set('datei', UploadedFile::fake()->create('urkunde.pdf', 100))
        ->call('speichern')
        ->assertHasNoErrors();

    expect(Storage::disk('local')->allFiles()[0] ?? '')
        ->toContain($kunde->slug.'/licenseaccess/')
        ->toContain('Lizenzurkunde_2026.pdf');
});

// --- Dieselben Regeln bei den Kundendateien ---------------------------------

test('die Kundendateien prüfen Größe und Typ ebenso', function () {
    Storage::fake('local');
    $nutzer = userWithPermissions(['file_create']);
    $this->actingAs($nutzer);
    $kunde = Customer::factory()->create();

    $this->post("/{$kunde->slug}/file", [
        'name' => 'Vertrag',
        'file' => UploadedFile::fake()->createWithContent('boese.php', '<?php echo 1;'),
    ])->assertSessionHasErrors('file');

    $this->post("/{$kunde->slug}/file", [
        'name' => 'Vertrag',
        'file' => UploadedFile::fake()->create('gross.pdf', config('custom.datei_max_kb') + 100),
    ])->assertSessionHasErrors('file');

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

// --- Der Helfer selbst ------------------------------------------------------

test('aus einer Bezeichnung wird ein Dateiname und nichts anderes', function () {
    expect(Dateiname::bereinigen('../../etc/passwd'))->toBe('etc_passwd')
        ->and(Dateiname::bereinigen('Lizenz 2026'))->toBe('Lizenz_2026')
        // Nur Sonderzeichen heisst: Es war nichts Brauchbares dabei.
        ->and(Dateiname::bereinigen('///'))->toBe('datei');
});

test('die Endung kommt kleingeschrieben und ohne Sonderzeichen an', function () {
    $name = Dateiname::fuer(UploadedFile::fake()->create('x.PDF', 1), 'Urkunde');

    expect($name)->toEndWith('_Urkunde.pdf');
});

// --- Die Obergrenze im Adminbereich -----------------------------------------

test('die Obergrenze lässt sich einstellen und gilt sofort', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminAllgemein::class)
        ->set('uploadMb', 5)
        ->assertHasNoErrors();

    expect(Setting::uploadMaxKb())->toBe(5 * 1024);
});

test('mehr als der Server annimmt geht nicht', function () {
    // Ein höherer Wert wäre ein Versprechen, das nicht hält: Der Upload bräche
    // mitten im Hochladen ab, ohne verständliche Meldung.
    $this->actingAs(userWithPermissions(['admin_setting']));

    $zuViel = (int) floor(Setting::serverGrenzeKb() / 1024) + 1;

    Livewire::test(AdminAllgemein::class)
        ->set('uploadMb', $zuViel)
        ->assertHasErrors('uploadMb');
});

test('die Grenze wird auch dann gedeckelt, wenn sie schon zu hoch in der Einstellung steht', function () {
    // Etwa nach einem Umzug auf einen Server mit engeren Grenzen.
    Setting::setzen(Setting::UPLOAD_MAX_KB, Setting::serverGrenzeKb() * 10);

    expect(Setting::uploadMaxKb())->toBe(Setting::serverGrenzeKb());
});

test('ohne Einstellung gilt der Wert aus der Konfiguration', function () {
    expect(Setting::uploadMaxKb())->toBe((int) config('custom.datei_max_kb'));
});
