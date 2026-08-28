<?php

use App\Livewire\AdminOperatingSystem;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Role;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Models\VM;
use Database\Seeders\OperatingSystemsSeeder;
use Livewire\Livewire;

function eolUmgebung(?string $eol): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2012 R2', 'eol_date' => $eol]);
    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-ALT', 'operating_system_id' => $os->id,
    ]);

    return [$customer, $site, $os, $server];
}

test('ein abgelaufenes System ist EOL, ein zukünftiges nicht', function () {
    expect((new OperatingSystem(['eol_date' => now()->subDay()]))->istEol())->toBeTrue();
    expect((new OperatingSystem(['eol_date' => now()->addYears(5)]))->istEol())->toBeFalse();

    // Ohne gepflegtes Datum trifft die Anwendung keine Aussage.
    expect((new OperatingSystem)->istEol())->toBeFalse();
    expect((new OperatingSystem)->laeuftAus())->toBeFalse();
});

test('laeuftAus greift ein halbes Jahr vorher, aber nicht rückwirkend', function () {
    expect((new OperatingSystem(['eol_date' => now()->addDays(100)]))->laeuftAus())->toBeTrue();
    expect((new OperatingSystem(['eol_date' => now()->addDays(200)]))->laeuftAus())->toBeFalse();

    // Bereits abgelaufen ist nicht "laeuft aus" - sonst stuenden zwei Abzeichen da.
    expect((new OperatingSystem(['eol_date' => now()->subDay()]))->laeuftAus())->toBeFalse();
});

test('die Serverliste zeigt das EOL-Abzeichen', function () {
    $nutzer = userWithPermissions(['server_viewAny']);
    [$customer, $site, $os, $server] = eolUmgebung('2023-10-10');

    $this->actingAs($nutzer)->get("/{$customer->slug}/server")
        ->assertSee('EOL')
        ->assertSee('10/2023');
});

test('die VM-Liste zeigt das EOL-Abzeichen', function () {
    $nutzer = userWithPermissions(['vm_viewAny']);
    [$customer, $site, $os, $server] = eolUmgebung('2023-10-10');
    VM::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'VM-ALT', 'operating_system_id' => $os->id,
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/vm")->assertSee('EOL');
});

test('ohne gepflegtes Datum steht kein Abzeichen in der Liste', function () {
    $nutzer = userWithPermissions(['server_viewAny']);
    [$customer, $site, $os, $server] = eolUmgebung(null);

    // Ein graues "unbekannt" an jedem Geraet waere nur Rauschen.
    $this->actingAs($nutzer)->get("/{$customer->slug}/server")->assertDontSee('EOL');
});

test('das Datum lässt sich in der Administration pflegen', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 11']);

    $this->actingAs($admin)->patch("/admin/operatingsystem/{$os->id}", [
        'name' => 'Debian 11', 'eol_date' => '2026-08-31',
    ])->assertSessionHasNoErrors();

    expect($os->fresh()->eol_date->format('Y-m-d'))->toBe('2026-08-31');
});

test('ein leeres Datum ist erlaubt und ein ungültiges nicht', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 11', 'eol_date' => '2026-08-31']);

    $this->actingAs($admin)->patch("/admin/operatingsystem/{$os->id}", [
        'name' => 'Debian 11', 'eol_date' => '',
    ])->assertSessionHasNoErrors();
    expect($os->fresh()->eol_date)->toBeNull();

    $this->actingAs($admin)->patch("/admin/operatingsystem/{$os->id}", [
        'name' => 'Debian 11', 'eol_date' => 'übermorgen',
    ])->assertSessionHasErrors('eol_date');
});

test('das Admin-Dashboard nennt betroffene Systeme, aber nur mit Geräten', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);

    [$customer, $site, $os, $server] = eolUmgebung('2023-10-10');
    OperatingSystem::factory()->create(['name' => 'CentOS 7', 'eol_date' => '2024-06-30']);

    $antwort = $this->actingAs($admin)->get('/admin');

    $antwort->assertSee('Windows Server 2012 R2 (1 Systeme)');
    // Ein EOL-System ohne Geraete darauf ist kein Problem und nur Laerm.
    $antwort->assertDontSee('CentOS 7');
});

test('die EOL-Übersicht gruppiert die betroffenen Geräte nach Kunde', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);

    [$kundeA, $siteA, $altesOs, $server] = eolUmgebung('2023-10-10');
    $kundeA->update(['name' => 'Alpha GmbH']);

    // Zweiter Kunde mit einer VM auf demselben alten System.
    $kundeB = Customer::factory()->create(['name' => 'Beta AG']);
    $siteB = Site::factory()->create(['customer_id' => $kundeB->id]);
    VM::create([
        'customer_id' => $kundeB->id, 'site_id' => $siteB->id,
        'name' => 'VM-BETA', 'operating_system_id' => $altesOs->id,
    ]);

    // Ein Gerät auf einem noch unterstützten System gehört nicht auf die Seite.
    $neu = OperatingSystem::factory()->create(['name' => 'Windows Server 2025', 'eol_date' => '2034-10-10']);
    Server::create([
        'customer_id' => $kundeB->id, 'site_id' => $siteB->id,
        'name' => 'SRV-NEU', 'operating_system_id' => $neu->id,
    ]);

    $this->actingAs($admin)->get('/admin/eol')
        ->assertSee('Alpha GmbH')
        ->assertSee('Beta AG')
        ->assertSee('SRV-ALT')
        ->assertSee('VM-BETA')
        ->assertDontSee('SRV-NEU');
});

test('ohne betroffene Geräte bleibt die EOL-Übersicht leer', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);

    eolUmgebung(null);

    $this->actingAs($admin)->get('/admin/eol')
        ->assertOk()
        ->assertSee('Kein Gerät läuft auf einem System');
});

test('ohne Admin-Rolle ist die EOL-Übersicht gesperrt', function () {
    $this->actingAs(userWithPermissions([]))->get('/admin/eol')->assertForbidden();
});

test('die Betriebssystem-Liste steht alphabetisch, nicht in Anlage-Reihenfolge', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);

    // Absichtlich in "falscher" Reihenfolge angelegt.
    OperatingSystem::factory()->create(['name' => 'Zorin OS']);
    OperatingSystem::factory()->create(['name' => 'AlmaLinux 9']);
    OperatingSystem::factory()->create(['name' => 'Debian 12']);

    $this->actingAs($admin);

    Livewire::test(AdminOperatingSystem::class)
        ->assertSeeInOrder(['AlmaLinux 9', 'Debian 12', 'Zorin OS']);
});

test('die Betriebssystem-Liste lässt sich nach Namen durchsuchen', function () {
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRolle->id]);
    OperatingSystem::factory()->create(['name' => 'AlmaLinux 9']);
    OperatingSystem::factory()->create(['name' => 'Zorin OS']);

    $this->actingAs($admin);

    Livewire::test(AdminOperatingSystem::class)
        ->set('suche', 'alma')
        ->assertSee('AlmaLinux 9')
        ->assertDontSee('Zorin OS');
});

test('der Seeder befüllt den vollständigen Betriebssystem-Katalog', function () {
    $this->seed(OperatingSystemsSeeder::class);

    $nachName = OperatingSystem::pluck('eol_date', 'name');

    expect($nachName['Windows 11 Pro'])->toBeNull(); // keine Version im Katalog, also kein Datum
    expect($nachName['Ubuntu Server 22.04 LTS']->format('Y-m-d'))->toBe('2027-04-01');
    expect($nachName['AlmaLinux 9']->format('Y-m-d'))->toBe('2032-05-31');
    expect($nachName['macOS Sonoma']->format('Y-m-d'))->toBe('2026-09-15');
    expect($nachName['Rangee OS'])->toBeNull(); // kein öffentlicher Support-Zeitplan
    expect($nachName['Proxmox VE 7']->format('Y-m-d'))->toBe('2024-07-31');
    expect($nachName['Proxmox VE 8']->format('Y-m-d'))->toBe('2026-08-31');
    expect($nachName['Debian 13']->format('Y-m-d'))->toBe('2030-06-30');
    expect($nachName['Proxmox VE 9'])->toBeNull(); // Termin noch nicht angekuendigt
    expect($nachName['VMware ESXi 6']->format('Y-m-d'))->toBe('2022-10-15');
    expect($nachName['Proxmox Backup Server 1']->format('Y-m-d'))->toBe('2022-09-30');
    expect($nachName['Proxmox Backup Server 3']->format('Y-m-d'))->toBe('2026-08-31');
    expect($nachName['Proxmox Backup Server 4'])->toBeNull(); // Termin noch nicht angekuendigt
    // Alte Sammel-Eintraege werden nicht mehr frisch angelegt.
    expect($nachName->has('Proxmox Virtual Environment'))->toBeFalse();
    expect($nachName->has('Proxmox Backup Server'))->toBeFalse();
});
