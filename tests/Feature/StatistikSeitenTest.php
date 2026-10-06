<?php

use App\Livewire\AdminAgentenStatistik;
use App\Livewire\AdminDatenwachstum;
use App\Livewire\AdminNutzung;
use App\Livewire\AdminSystem;
use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Support\AgentSkript;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Admin -> Statistik: System, Datenwachstum, Agenten, Nutzung - all behind
 * the right "Statistik sehen" (admin_statistik).
 */

test('the nightly snapshot stores object counts per customer and in total, and system values', function () {
    $a = Customer::factory()->create();
    $b = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $a->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 12']);
    Server::factory()->count(2)->create(['customer_id' => $a->id, 'site_id' => $site->id, 'operating_system_id' => $os->id]);
    Server::factory()->create(['customer_id' => $b->id, 'site_id' => Site::factory()->create(['customer_id' => $b->id])->id, 'operating_system_id' => $os->id]);

    $this->artisan('statistik:schnappschuss')->assertSuccessful();
    // Same day again: overwritten, not doubled.
    $this->artisan('statistik:schnappschuss')->assertSuccessful();

    $server = DB::table('statistik_werte')->where('kennzahl', 'server')->pluck('wert', 'customer_id');
    expect((int) $server[$a->id])->toBe(2)
        ->and((int) $server[$b->id])->toBe(1)
        ->and((int) $server[0])->toBe(3)
        ->and(DB::table('statistik_werte')->where('kennzahl', 'server')->count())->toBe(3)
        ->and(DB::table('statistik_werte')->where('kennzahl', 'dateien_bytes')->exists())->toBeTrue();
});

test('all statistics pages open with the statistics right', function (string $komponente) {
    $this->artisan('statistik:schnappschuss');
    $this->actingAs(userWithPermissions(['admin_statistik']));

    Livewire::test($komponente)->assertOk();
})->with([AdminSystem::class, AdminDatenwachstum::class, AdminAgentenStatistik::class, AdminNutzung::class]);

test('without the statistics right the pages stay closed', function (string $route) {
    $this->actingAs(userWithPermissions(['admin_setting']));

    $this->get(route($route))->assertForbidden();
})->with(['admin.statistik.system', 'admin.statistik.wachstum', 'admin.statistik.agenten', 'admin.statistik.nutzung']);

test('the agents page puts silent and outdated agents first', function () {
    $customer = Customer::factory()->create(['name' => 'Nordwind']);
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token] = AgentToken::generateFor($customer, $site, 'T', now()->addYear());
    AgentInstallation::checkin($token, 'proxmox', 'ok-1', 'pve-ok', null, AgentSkript::installerVersion('linux-agent.sh'), ['proxmox']);
    AgentInstallation::checkin($token, 'proxmox', 'alt-1', 'pve-alt', null, 'altversion', ['proxmox']);
    AgentInstallation::checkin($token, 'proxmox', 'still-1', 'pve-still', null, AgentSkript::installerVersion('linux-agent.sh'), ['proxmox']);
    AgentInstallation::where('machine_id', 'still-1')->update(['last_seen_at' => now()->subDay()]);

    $this->actingAs(userWithPermissions(['admin_statistik']));
    Livewire::test(AdminAgentenStatistik::class)
        ->assertViewHas('summe', fn ($s) => $s['gesamt'] === 3 && $s['still'] === 1 && $s['veraltet'] === 1)
        ->assertViewHas('agenten', fn ($a) => $a->pluck('hostname')->all() === ['pve-still', 'pve-alt', 'pve-ok']);
});

test('the usage page counts sign-ins and changes', function () {
    $this->actingAs(userWithPermissions(['admin_statistik']));
    $customer = Customer::factory()->create();
    Domain::factory()->create(['customer_id' => $customer->id]);

    Livewire::test(AdminNutzung::class)
        ->assertViewHas('summe', fn ($s) => $s['aenderungen'] >= 1)
        ->set('zeitraum', '7')
        ->assertViewHas('aenderungenProTag', fn ($t) => count($t) === 7);
});
