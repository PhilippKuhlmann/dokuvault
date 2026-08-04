<?php

use App\Livewire\GlobalSearch;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function platzUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack HH-01', 'height_units' => 42, 'location' => 'Serverraum EG',
    ]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-01', 'operating_system_id' => $os->id,
    ]);

    return [$customer, $site, $rack, $server];
}

test('ein nicht eingebautes Gerät hat keinen Einbauort', function () {
    [$customer, $site, $rack, $server] = platzUmgebung();

    expect($server->einbauort())->toBeNull();
});

test('der Einbauort nennt Rack, Höheneinheiten und Seite', function () {
    [$customer, $site, $rack, $server] = platzUmgebung();

    $rack->items()->create([
        'side' => 'rear', 'position' => 4, 'height_units' => 2, 'full_depth' => true,
        'device_type' => Server::class, 'device_id' => $server->id,
    ]);

    expect($server->fresh()->einbauort())->toBe('Rack HH-01 · HE 4–5 · Rückseite');
});

test('bei einer Höheneinheit steht kein Bereich', function () {
    [$customer, $site, $rack, $server] = platzUmgebung();

    $rack->items()->create([
        'position' => 7, 'height_units' => 1,
        'device_type' => Server::class, 'device_id' => $server->id,
    ]);

    expect($server->fresh()->einbauort())->toBe('Rack HH-01 · HE 7 · Vorderseite');
});

test('die Geräteliste zeigt den Einbauort', function () {
    $nutzer = userWithPermissions(['server_viewAny']);
    [$customer, $site, $rack, $server] = platzUmgebung();
    $rack->items()->create([
        'position' => 4, 'height_units' => 2,
        'device_type' => Server::class, 'device_id' => $server->id,
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/server")
        ->assertSee('Rack HH-01 · HE 4–5 · Vorderseite');
});

test('die globale Suche findet Serverschränke über Name und Ort', function () {
    $nutzer = userWithPermissions(['rack_viewAny']);
    [$customer, $site, $rack, $server] = platzUmgebung();

    $this->actingAs($nutzer);

    Livewire::test(GlobalSearch::class)->set('search', 'HH-01')->assertSee('Rack HH-01');
    Livewire::test(GlobalSearch::class)->set('search', 'Serverraum')->assertSee('Rack HH-01');
});

test('ohne rack_viewAny erscheinen keine Schränke in der Suche', function () {
    $nutzer = userWithPermissions([]);
    [$customer, $site, $rack, $server] = platzUmgebung();

    $this->actingAs($nutzer);

    Livewire::test(GlobalSearch::class)->set('search', 'HH-01')->assertDontSee('Rack HH-01');
});

test('ein Umbau landet im Aktivitätsprotokoll', function () {
    $nutzer = userWithPermissions(['rack_update']);
    [$customer, $site, $rack, $server] = platzUmgebung();

    $this->actingAs($nutzer);
    $item = $rack->items()->create([
        'position' => 4, 'height_units' => 2,
        'device_type' => Server::class, 'device_id' => $server->id,
    ]);
    $item->update(['position' => 9]);
    $item->delete();

    $ereignisse = Activity::where('subject_type', $item::class)->pluck('event');

    expect($ereignisse)->toContain('created');
    expect($ereignisse)->toContain('updated');
    expect($ereignisse)->toContain('deleted');
});
