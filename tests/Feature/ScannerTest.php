<?php

use App\Livewire\ScanTargets;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Models\Site;
use Livewire\Livewire;

test('ein Scanner lässt sich anlegen und steht in der Liste', function () {
    $this->actingAs(userWithPermissions(['scanner_create', 'scanner_viewAny']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    imModal('scanner', $customer, [
        'site_id' => $site->id,
        'name' => 'SCN-Empfang',
        'manufacturer' => 'Fujitsu',
        'model' => 'fi-8170',
    ])->assertHasNoErrors();

    $scanner = Scanner::where('name', 'SCN-Empfang')->firstOrFail();
    expect($scanner->customer_id)->toBe($customer->id);

    $this->get(route('scanner.index', $customer))
        ->assertOk()
        ->assertSee('SCN-Empfang')
        ->assertSee('fi-8170');
});

test('ohne Recht ist die Scanner-Liste gesperrt und fehlt in der Seitenleiste', function () {
    $this->actingAs(userWithPermissions(['printer_viewAny']));
    $customer = Customer::factory()->create();

    $this->get(route('scanner.index', $customer))->assertForbidden();
    $this->get(route('printer.index', $customer))
        ->assertOk()
        ->assertDontSee(route('scanner.index', $customer));
});

test('die Migration gibt jeder Rolle mit Drucker-Recht das Scanner-Recht', function () {
    // Ausgangslage einer bestehenden Installation: Rolle mit Drucker-Recht,
    // Scanner-Rechte gibt es noch nicht.
    Permission::where('name', 'like', 'scanner\_%')->delete();
    $drucker = Permission::where('name', 'printer_viewAny')->first()
        ?? Permission::forceCreate(['name' => 'printer_viewAny', 'description' => 'Printer sehen']);

    $rolle = Role::forceCreate(['name' => 'Druckerpflege', 'description' => 'Test']);
    $rolle->permissions()->attach($drucker);

    $migration = require database_path('migrations/2026_09_30_130100_add_scanner_permissions.php');
    $migration->up();

    $rechte = $rolle->permissions()->pluck('name');
    expect($rechte)->toContain('scanner_viewAny')
        ->and($rechte)->not->toContain('scanner_create');

    // Ein zweiter Lauf legt nichts doppelt an.
    $migration->up();
    expect(Permission::where('name', 'scanner_viewAny')->count())->toBe(1)
        ->and($rolle->permissions()->where('name', 'scanner_viewAny')->count())->toBe(1);
});

/**
 * Ein Scanner beim Kunden, dazu ein Nutzer, der ihn bearbeiten darf.
 */
function scannerZumBearbeiten(): array
{
    $customer = Customer::factory()->create();
    $scanner = Scanner::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
    ]);

    return [$customer, $scanner];
}

test('ein Scanner nimmt beliebig viele Scan-Ziele auf', function () {
    $this->actingAs(userWithPermissions(['scanner_update', 'scanner_viewAny', 'scantarget_create', 'scantarget_viewAny']));
    [$customer, $scanner] = scannerZumBearbeiten();

    $block = Livewire::test(ScanTargets::class, ['model' => $scanner, 'customer' => $customer]);

    foreach (range(1, 12) as $n) {
        $block->set('neu', true)
            ->set('name', "Mitarbeiter {$n}")
            ->set('kind', 'email')
            ->set('target', "ma{$n}@example.com")
            ->call('add')
            ->assertHasNoErrors();
    }

    // Die Art bleibt nach dem Anlegen stehen, Bezeichnung und Ziel nicht.
    $block->assertSet('kind', 'email')->assertSet('target', '');

    expect($scanner->scanTargets()->count())->toBe(12)
        ->and(ScanTarget::where('customer_id', $customer->id)->count())->toBe(12);

    $this->get(route('scanner.index', $customer))
        ->assertOk()
        ->assertSee('ma12@example.com')
        ->assertSee('Mitarbeiter 7');
});

test('ein Scan-Ziel lässt sich auf mehreren Scannern einrichten', function () {
    $this->actingAs(userWithPermissions(['scanner_update', 'scantarget_viewAny']));
    [$customer, $eg] = scannerZumBearbeiten();
    $og = Scanner::factory()->create(['customer_id' => $customer->id, 'site_id' => $eg->site_id, 'name' => 'SCN-OG']);
    $ziel = ScanTarget::factory()->create(['customer_id' => $customer->id, 'name' => 'Buchhaltung']);

    foreach ([$eg, $og] as $scanner) {
        Livewire::test(ScanTargets::class, ['model' => $scanner, 'customer' => $customer])
            ->set('zielId', $ziel->id)
            ->call('add')
            ->assertHasNoErrors();
    }

    expect($ziel->scanners()->pluck('scanners.id')->all())->toEqualCanonicalizing([$eg->id, $og->id]);

    // Loesen nimmt nur die Verknuepfung - am anderen Scanner und in der Liste
    // bleibt das Ziel.
    Livewire::test(ScanTargets::class, ['model' => $eg, 'customer' => $customer])
        ->call('remove', $ziel->id);

    expect($ziel->scanners()->pluck('scanners.id')->all())->toBe([$og->id])
        ->and(ScanTarget::find($ziel->id))->not->toBeNull();
});

test('die Karte des Ziels zeigt Scanner und Zugangsdaten, das Kennwort erst auf Klick', function () {
    $this->actingAs(userWithPermissions(['scantarget_viewAny', 'scanner_viewAny', 'logingeneral_viewAny']));
    [$customer, $scanner] = scannerZumBearbeiten();
    $konto = LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'Scanner FTP', 'username' => 'scan', 'password' => 'Nicht-Im-Dom-2026',
    ]);
    $ziel = ScanTarget::factory()->create([
        'customer_id' => $customer->id, 'kind' => 'ftp', 'target' => 'sftp://srv/archiv', 'login_general_id' => $konto->id,
    ]);
    $scanner->scanTargets()->attach($ziel);

    $this->get(route('scantarget.index', $customer))
        ->assertOk()
        ->assertSee('sftp://srv/archiv')
        ->assertSee('Scanner FTP')
        ->assertSee(route('scanner.index', [$customer, 'highlight' => $scanner->id]), false)
        ->assertDontSee('Nicht-Im-Dom-2026');
});

test('ein Ziel eines fremden Kunden lässt sich nicht verknüpfen', function () {
    $this->actingAs(userWithPermissions(['scanner_update', 'scantarget_viewAny']));
    [$customer, $scanner] = scannerZumBearbeiten();
    $fremd = ScanTarget::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    Livewire::test(ScanTargets::class, ['model' => $scanner, 'customer' => $customer])
        ->set('neu', false)
        ->set('zielId', $fremd->id)
        ->call('add')
        ->assertHasErrors('zielId');

    expect($scanner->scanTargets()->count())->toBe(0);
});

test('ein neues Ziel braucht das Anlegen-Recht der Scan-Ziele', function () {
    $this->actingAs(userWithPermissions(['scanner_update']));
    [$customer, $scanner] = scannerZumBearbeiten();

    Livewire::test(ScanTargets::class, ['model' => $scanner, 'customer' => $customer])
        ->set('neu', true)
        ->set('target', '\\\\srv\\scans')
        ->call('add')
        ->assertForbidden();

    expect(ScanTarget::count())->toBe(0);
});

test('ohne Bearbeiten-Recht am Scanner lässt sich nichts verknüpfen oder lösen', function () {
    $this->actingAs(userWithPermissions(['scanner_viewAny', 'scantarget_viewAny', 'scantarget_create']));
    [$customer, $scanner] = scannerZumBearbeiten();
    $ziel = ScanTarget::factory()->create(['customer_id' => $customer->id]);
    $scanner->scanTargets()->attach($ziel);

    Livewire::test(ScanTargets::class, ['model' => $scanner, 'customer' => $customer])
        ->call('remove', $ziel->id)
        ->assertForbidden();

    expect($scanner->scanTargets()->count())->toBe(1);
});

test('der Block eines fremden Scanners lässt sich nicht öffnen', function () {
    [$customer, $scanner] = scannerZumBearbeiten();
    $fremd = Customer::factory()->create();

    $this->actingAs(userWithPermissions(['scanner_update']));

    Livewire::test(ScanTargets::class, ['model' => $scanner, 'customer' => $fremd])
        ->assertForbidden();
});

test('ein Scan-Ziel lässt sich über die eigene Liste anlegen', function () {
    $this->actingAs(userWithPermissions(['scantarget_create', 'scantarget_viewAny']));
    $customer = Customer::factory()->create();

    imModal('scantarget', $customer, [
        'name' => 'Personal',
        'kind' => 'smb',
        'target' => '\\\\srv-file01\\scans\\personal',
    ])->assertHasNoErrors();

    expect(ScanTarget::where('name', 'Personal')->value('target'))->toBe('\\\\srv-file01\\scans\\personal');
});

test('Scan-Ziele stehen nicht im Menü, sondern als Link auf der Scanner-Seite', function () {
    $this->actingAs(userWithPermissions(['scanner_viewAny', 'scantarget_viewAny']));
    $customer = Customer::factory()->create();

    $seite = $this->get(route('scanner.index', $customer))->assertOk();
    $seite->assertSee(route('scantarget.index', $customer), false);

    // Genau ein Link: der im Listenkopf, keiner in der Seitenleiste.
    expect(substr_count($seite->getContent(), 'href="'.route('scantarget.index', $customer).'"'))->toBe(1);

    // Und zurueck.
    $this->get(route('scantarget.index', $customer))
        ->assertOk()
        ->assertSee('href="'.route('scanner.index', $customer).'"', false);
});

test('ohne Recht auf die Scan-Ziele fehlt der Link', function () {
    $this->actingAs(userWithPermissions(['scanner_viewAny']));
    $customer = Customer::factory()->create();

    $this->get(route('scanner.index', $customer))
        ->assertOk()
        ->assertDontSee(route('scantarget.index', $customer), false);
});
