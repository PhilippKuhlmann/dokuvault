<?php

use App\Livewire\GlobalSearch;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\LoginGeneral;
use App\Models\Permission;
use App\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** @return array{0: Customer, 1: Site} */
function kundeMitStandort(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('eine Firewall laesst sich anlegen, bearbeiten und loeschen', function () {
    [$customer, $site] = kundeMitStandort();
    $this->actingAs(userWithPermissions([
        'firewall_viewAny', 'firewall_create', 'firewall_update', 'firewall_delete',
    ]));

    $this->post(route('firewall.store', $customer), [
        'site_id' => $site->id,
        'name' => 'FW-HH-01',
        'manufacturer' => 'Sophos',
        'model' => 'XGS 2100',
        'firmware' => 'SFOS 20.0.2 MR-2',
        'username' => 'admin',
        'password' => 'Geheim!2026',
        'subscription_until' => '2027-03-31',
    ])->assertRedirect(route('firewall.index', $customer));

    $firewall = Firewall::where('name', 'FW-HH-01')->firstOrFail();
    expect($firewall->manufacturer)->toBe('Sophos');
    expect($firewall->firmware)->toBe('SFOS 20.0.2 MR-2');
    expect($firewall->subscription_until->format('Y-m-d'))->toBe('2027-03-31');

    $this->get(route('firewall.index', $customer))->assertSee('FW-HH-01');

    $this->patch(route('firewall.update', [$customer, $firewall]), [
        'site_id' => $site->id,
        'name' => 'FW-HH-01',
        'firmware' => 'SFOS 21.0.0',
    ])->assertRedirect(route('firewall.index', $customer));

    expect($firewall->fresh()->firmware)->toBe('SFOS 21.0.0');

    $this->delete(route('firewall.destroy', [$customer, $firewall]));
    expect(Firewall::find($firewall->id))->toBeNull();
    expect(Firewall::withTrashed()->find($firewall->id))->not->toBeNull();
});

test('das Kennwort liegt verschluesselt in der Tabelle', function () {
    [$customer, $site] = kundeMitStandort();

    $firewall = Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'password' => 'Klartext!2026',
    ]);

    $roh = DB::table('firewalls')->where('id', $firewall->id)->value('password');

    expect($roh)->not->toBe('Klartext!2026');
    expect(Crypt::decryptString($roh))->toBe('Klartext!2026');
    expect($firewall->fresh()->password)->toBe('Klartext!2026');
});

test('ohne Berechtigung ist die Firewall nicht erreichbar', function () {
    [$customer, $site] = kundeMitStandort();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $this->get(route('firewall.index', $customer))->assertForbidden();
    $this->post(route('firewall.store', $customer), [
        'site_id' => $site->id, 'name' => 'FW-Fremd',
    ])->assertForbidden();

    expect(Firewall::where('name', 'FW-Fremd')->exists())->toBeFalse();
});

test('die globale Suche findet die Firewall', function () {
    [$customer, $site] = kundeMitStandort();
    $this->actingAs(userWithPermissions(['firewall_viewAny']));

    Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'FW-Suchtest',
    ]);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'FW-Suchtest')
        ->assertSee('FW-Suchtest');
});

test('der Standort eines fremden Kunden wird abgelehnt', function () {
    [$customer] = kundeMitStandort();
    $fremd = Customer::factory()->create();
    $fremderStandort = Site::factory()->create(['customer_id' => $fremd->id]);

    $this->actingAs(userWithPermissions(['firewall_create']));

    // Ohne diese Pruefung liesse sich ein Geraet an einen Standort haengen, der
    // einem anderen Mandanten gehoert.
    $this->post(route('firewall.store', $customer), [
        'site_id' => $fremderStandort->id,
        'name' => 'FW-IDOR',
    ])->assertSessionHasErrors('site_id');

    expect(Firewall::where('name', 'FW-IDOR')->exists())->toBeFalse();
});

test('die Firewall ist im Serverschrank platzierbar', function () {
    // Der Eintrag in rack_device_types ist die Voraussetzung dafuer - ohne ihn
    // taucht die Firewall im Schrank-Editor nicht auf.
    $typen = config('custom.rack_device_types');

    expect($typen)->toHaveKey('firewall');
    expect($typen['firewall'][0])->toBe(Firewall::class);
    expect(config('custom.rack_appearances'))->toHaveKey($typen['firewall'][2]);
});

test('die Firewall steht im Papierkorb und in den Berechtigungen', function () {
    expect(config('custom.trashables'))->toHaveKey('firewall');
    expect(config('custom.permissions'))->toContain('Firewall');
});

test('die Securepoint-Felder gehoeren zur Firewall', function () {
    [$customer, $site] = kundeMitStandort();
    $this->actingAs(userWithPermissions(['firewall_viewAny', 'firewall_create']));

    // Eine UTM ist eine Firewall - sie unterscheidet sich im Hersteller, nicht
    // im Geraetetyp.
    $this->post(route('firewall.store', $customer), [
        'site_id' => $site->id,
        'name' => 'UTM-01',
        'manufacturer' => 'Securepoint',
        'form_factor' => 'appliance',
        'management_url' => 'https://10.0.0.1:11115',
        'url_user' => 'https://10.0.0.1',
        'url_external' => 'https://utm.example.de:11115',
        'usc_pin' => '448213',
        'cloud_backup_password' => 'Wolke!2026',
    ])->assertRedirect();

    $utm = Firewall::where('name', 'UTM-01')->sole();

    expect($utm->istSecurepoint())->toBeTrue();
    expect($utm->usc_pin)->toBe('448213');
    expect($utm->cloud_backup_password)->toBe('Wolke!2026');
    expect($utm->url_external)->toBe('https://utm.example.de:11115');

    // Beide Geheimnisse liegen verschluesselt in der Tabelle.
    foreach (['usc_pin', 'cloud_backup_password'] as $spalte) {
        $roh = DB::table('firewalls')->where('id', $utm->id)->value($spalte);
        expect($roh)->not->toBe('448213')->and($roh)->not->toBe('Wolke!2026');
        expect(Crypt::decryptString($roh))->not->toBeEmpty();
    }

    $this->get(route('firewall.index', $customer))->assertSee('Securepoint');
});

test('bei anderen Herstellern bleiben die Securepoint-Felder aus der Liste', function () {
    [$customer, $site] = kundeMitStandort();
    $this->actingAs(userWithPermissions(['firewall_viewAny']));

    Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'FW-Sophos',
        'manufacturer' => 'Sophos',
        'usc_pin' => null,
        'cloud_backup_password' => null,
        'url_user' => null,
        'url_external' => null,
    ]);

    // Die Karte blendet sich aus, wenn nichts gefuellt ist - eine Sophos hat
    // keine USC-PIN, und ein leerer Block sieht wie eine Luecke aus.
    $antwort = $this->get(route('firewall.index', $customer));
    $antwort->assertSee('FW-Sophos');
    $antwort->assertDontSee('USC-PIN');
});

test('von der Securepoint UTM ist nichts uebrig geblieben', function () {
    expect(Schema::hasTable('securepoint_utms'))->toBeFalse();
    expect(file_exists(app_path('Models/SecurepointUTM.php')))->toBeFalse();
    expect(config('custom.trashables'))->not->toHaveKey('securepointutm');
    expect(config('custom.permissions'))->not->toContain('SecurepointUTM');

    // Die Rechte sind weg, damit sie nicht in der Rollenverwaltung auf eine
    // Seite zeigen, die es nicht mehr gibt.
    expect(Permission::where('name', 'like', 'securepointutm_%')->exists())->toBeFalse();
});

test('die Migration raeumt Verweise auf die entfernte Klasse ab', function () {
    // Ein Verweis auf eine geloeschte Klasse bricht jede Seite, die ihn
    // aufloest, mit "Class not found" - gemessen an der Zugangsdaten-Seite und
    // am Verlauf. Deshalb entfernt die Migration ihn.
    $customer = Customer::factory()->create();
    $login = LoginGeneral::factory()->create(['customer_id' => $customer->id]);

    DB::table('credential_links')->insert([
        'customer_id' => $customer->id,
        'login_general_id' => $login->id,
        'credentialable_type' => 'App\Models\SecurepointUTM',
        'credentialable_id' => 999,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('credential_links')->where('credentialable_type', 'App\Models\SecurepointUTM')->count())->toBe(1);

    $migration = require database_path('migrations/2026_08_17_160100_drop_securepoint_utms.php');
    $migration->up();

    expect(DB::table('credential_links')->where('credentialable_type', 'App\Models\SecurepointUTM')->count())->toBe(0);
    // Fremde Verknuepfungen bleiben unangetastet.
    expect(DB::table('credential_links')->count())->toBe(0);
});
