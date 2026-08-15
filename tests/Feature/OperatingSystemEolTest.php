<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Role;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Models\VM;

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
