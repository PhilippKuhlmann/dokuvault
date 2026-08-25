<?php

use App\Livewire\ObjektFormular;
use App\Livewire\ObjektListe;
use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Models\LicenseWindows;
use App\Models\OperatingSystem;
use Database\Seeders\OperatingSystemsSeeder;
use Livewire\Livewire;

function softwareLizenzen(Customer $customer): void
{
    $bestand = [
        // Name, Enddatum, Abo
        ['Veeam Backup', now()->subDays(30), 'Jährlich'],
        ['Lexware Buchhaltung', now()->addDays(20), 'Jährlich'],
        ['TeamViewer Corporate', now()->addDays(60), 'Monatlich'],
        ['Adobe Acrobat', now()->addDays(300), 'Monatlich'],
        // Dauerlizenz: laeuft nicht ab.
        ['AutoCAD Dauerlizenz', null, null],
    ];

    foreach ($bestand as [$name, $ende, $abo]) {
        LicenseSoftware::create([
            'customer_id' => $customer->id, 'name' => $name,
            'key' => 'KEY-'.$name, 'end_date' => $ende, 'abo' => $abo,
        ]);
    }
}

test('der Laufzeit-Filter trennt abgelaufen, bald und laufend', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));
    softwareLizenzen($customer);

    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('filter.ablauf', 'abgelaufen')
        ->assertSee('Veeam Backup')
        ->assertDontSee('Lexware Buchhaltung');

    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('filter.ablauf', '30')
        ->assertSee('Lexware Buchhaltung')       // in 20 Tagen
        ->assertDontSee('TeamViewer Corporate')  // erst in 60
        ->assertDontSee('Veeam Backup');         // schon abgelaufen
});

test('"Läuft noch" schliesst Lizenzen ohne Enddatum ein', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));
    softwareLizenzen($customer);

    // Eine Dauerlizenz laeuft nicht ab und gehoert zu den unproblematischen -
    // sie faellt sonst durch jedes Raster.
    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('filter.ablauf', 'offen')
        ->assertSee('AutoCAD Dauerlizenz')
        ->assertSee('Lexware Buchhaltung')
        ->assertDontSee('Veeam Backup');
});

test('der Abo-Filter trennt jaehrlich und monatlich', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));
    softwareLizenzen($customer);

    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('filter.abo', 'Monatlich')
        ->assertSee('TeamViewer Corporate')
        ->assertDontSee('Veeam Backup');
});

test('Filter lassen sich kombinieren', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));
    softwareLizenzen($customer);

    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('filter.ablauf', 'offen')
        ->set('filter.abo', 'Jährlich')
        ->assertSee('Lexware Buchhaltung')
        ->assertDontSee('TeamViewer Corporate')  // laeuft noch, aber monatlich
        ->assertDontSee('AutoCAD Dauerlizenz');  // laeuft noch, aber kein Abo
});

test('nach Ablauf sortiert stehen die dringenden oben, Dauerlizenzen hinten', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));
    softwareLizenzen($customer);

    // Ohne Enddatum ist nicht "laeuft als naechstes ab" - solche Zeilen
    // gehoeren ans Ende, nicht an den Anfang.
    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('sortierung', 'ablauf')
        ->assertSeeInOrder(['Veeam Backup', 'Lexware Buchhaltung', 'AutoCAD Dauerlizenz']);
});

test('der Betriebssystem-Filter der Windows-Lizenzen kennt nur den Bestand', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensewindows_viewAny']));

    $benutzt = OperatingSystem::factory()->create(['name' => 'Windows Server 2022 Standard']);
    $anderes = OperatingSystem::factory()->create(['name' => 'Windows Server 2016 Standard']);
    OperatingSystem::factory()->create(['name' => 'Nie benutztes System']);

    LicenseWindows::create(['customer_id' => $customer->id, 'key' => 'AAAA-2022', 'operating_system_id' => $benutzt->id]);
    LicenseWindows::create(['customer_id' => $customer->id, 'key' => 'BBBB-2016', 'operating_system_id' => $anderes->id]);

    $komponente = Livewire::test(ObjektListe::class, ['typ' => 'licensewindows', 'customer' => $customer]);

    // Ein Betriebssystem ohne Lizenz waere eine Zeile, die immer nichts findet.
    $optionen = $komponente->viewData('filterDefinition')[0]['optionen'];
    expect($optionen)->toHaveCount(2);
    expect(array_values($optionen))->not->toContain('Nie benutztes System');

    $komponente->set('filter.os', (string) $benutzt->id)
        ->assertSee('AAAA-2022')
        ->assertDontSee('BBBB-2016');
});

test('Filter zuruecksetzen bringt alle Eintraege zurueck', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));
    softwareLizenzen($customer);

    Livewire::test(ObjektListe::class, ['typ' => 'licensesoftware', 'customer' => $customer])
        ->set('filter.abo', 'Monatlich')
        ->call('zuruecksetzen')
        ->assertSet('filter', [])
        ->assertSee('Veeam Backup');
});

test('eine Liste ohne Filterdefinition zeigt keine Filterleiste', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['printer_viewAny']));

    // Ein Drucker hat weder Laufzeit noch Auswahlfeld - eine leere Leiste
    // ueber der Liste waere nur Rauschen.
    Livewire::test(ObjektListe::class, ['typ' => 'printer', 'customer' => $customer])
        ->assertSet('filter', [])
        ->assertDontSee('Sortierung');
});

test('eine Windows-Lizenz bietet nur Windows-Systeme zur Auswahl', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licensewindows_create']));

    OperatingSystem::factory()->create(['name' => 'Windows Server 2022 Standard']);
    OperatingSystem::factory()->create(['name' => 'Windows 11 Pro']);
    OperatingSystem::factory()->create(['name' => 'Debian 13']);
    OperatingSystem::factory()->create(['name' => 'Proxmox VE 9']);

    // Eine Windows-Lizenz fuer Debian oder Proxmox gibt es nicht - der
    // Katalog fuehrt beides, das Formular darf es nicht anbieten.
    $auswahl = Livewire::test(ObjektFormular::class, ['typ' => 'licensewindows', 'customer' => $customer])
        ->call('neu')
        ->viewData('auswahlen')['operating_system_id'];

    expect($auswahl->values()->all())->toContain('Windows Server 2022 Standard', 'Windows 11 Pro');
    expect($auswahl->values()->all())->not->toContain('Debian 13', 'Proxmox VE 9');
});

test('der Demo-Datensatz erzeugt keine Windows-Lizenz fuer Debian oder Proxmox', function () {
    $this->seed(OperatingSystemsSeeder::class);
    $customer = Customer::factory()->create();

    LicenseWindows::factory(10)->create(['customer_id' => $customer->id]);

    // Die Factory wuerfelte vorher eine Id zwischen 1 und 14 - welches System
    // dahinter steckt, haengt an der Reihenfolge im Katalog. Im Demo-Datensatz
    // standen dadurch Windows-Lizenzen fuer "Debian 13" und "Proxmox VE 7".
    $systeme = LicenseWindows::where('customer_id', $customer->id)
        ->with('operatingSystem')->get()
        ->map(fn ($l) => $l->operatingSystem?->name);

    foreach ($systeme as $name) {
        expect($name)->toStartWith('Windows', "Windows-Lizenz fuer {$name}");
    }
});
