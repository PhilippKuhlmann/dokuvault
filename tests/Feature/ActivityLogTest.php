<?php

use App\Models\Computer;
use App\Models\Customer;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Wifi;
use Spatie\Activitylog\Models\Activity;

test('Anlegen eines Objekts wird mit Verursacher protokolliert', function () {
    $user = userWithPermissions(['computer_create']);
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);

    imModal('computer', $customer, [
        'site_id' => $site->id,
        'name' => 'PC-Audit',
        'operating_system_id' => $os->id,
    ]);

    $activity = Activity::where('event', 'created')
        ->where('subject_type', Computer::class)
        ->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->properties['attributes']['name'])->toBe('PC-Audit');
});

test('Löschen wird protokolliert', function () {
    $this->actingAs(userWithPermissions(['computer_update', 'computer_delete']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows 11']);
    $computer = Computer::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'PC-Weg',
        'operating_system_id' => $os->id,
    ]);

    imModalLoeschen('computer', $customer, $computer);

    expect(Activity::where('event', 'deleted')
        ->where('subject_type', Computer::class)
        ->where('subject_id', $computer->id)
        ->exists())->toBeTrue();
});

test('Passwörter erscheinen niemals im Aktivitätsprotokoll', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $network = Network::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
    ]);

    $wifi = Wifi::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'network_id' => $network->id,
        'ssid' => 'Firmen-WLAN',
        'password' => 'SuperGeheim123!',
        'encryption' => 'WPA2',
    ]);

    $wifi->update(['password' => 'NochGeheimer456!', 'ssid' => 'Firmen-WLAN-Neu']);

    $allProperties = Activity::all()->pluck('properties')->toJson();
    expect($allProperties)->not->toContain('SuperGeheim123!');
    expect($allProperties)->not->toContain('NochGeheimer456!');
});

test('Aktivitäten-Seite nur für Admins erreichbar', function () {
    // Admin (Role id 1)
    $adminRole = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $this->actingAs($admin);
    $this->get('/admin/activity')->assertStatus(200);

    // Nicht-Admin
    nutzerWechseln(userWithPermissions([]));
    $this->get('/admin/activity')->assertStatus(403);
});
