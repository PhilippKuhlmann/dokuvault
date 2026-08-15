<?php

use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Role;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\User;

/**
 * Die Dienste am Geraet bleiben Freitext (komma-getrennt). Der Katalog gibt nur
 * vor, was zur Auswahl steht, welche Farbe eine Kachel bekommt und was die
 * Beschreibung dazu sagt.
 */
function dienstUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    Service::create(['name' => 'AD', 'description' => 'Verzeichnisdienst', 'color' => '#b91c1c']);
    Service::create(['name' => 'DNS', 'description' => 'Namensauflösung', 'color' => '#dc2626']);
    Service::create(['name' => 'Ohnetext', 'color' => '#15803d']);

    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-01', 'operating_system_id' => $os->id, 'services' => 'AD,Nextcloud',
    ]);

    return [$customer, $server];
}

/**
 * Die Seitenleiste bringt eigene Hover-Fenster mit (x-nav.link). Wer die
 * Fenster der Dienste zaehlen will, muss die abziehen - sonst prueft der Test
 * die Navigation mit.
 */
function tooltipGrundrauschen($test): int
{
    $customer = Customer::factory()->create();

    return substr_count($test->get("/{$customer->slug}")->getContent(), 'role="tooltip"');
}

test('das Formular zeigt den Katalog samt Beschreibung zur Auswahl', function () {
    $this->actingAs(userWithPermissions(['server_update']));
    [$customer, $server] = dienstUmgebung();

    $inhalt = $this->get("/{$customer->slug}/server/{$server->id}/edit")->assertOk()->getContent();

    expect($inhalt)->toContain('Aus dem Katalog');

    // Die Beschreibung steht im Hover-Fenster, nicht als title-Attribut:
    // Der Browser-Tooltip kommt erst nach einer Sekunde.
    expect($inhalt)->toContain('role="tooltip"');
    expect($inhalt)->toContain('Verzeichnisdienst');
    expect($inhalt)->toContain('Namensauflösung');

    // Ein Katalogeintrag ohne Beschreibung ("Ohnetext") bekommt gar kein
    // Fenster - sonst haette man ein leeres Kaestchen am Mauszeiger.
    // Relativ gezaehlt: Die Seitenleiste bringt eigene tooltips mit.
    expect(substr_count($inhalt, 'role="tooltip"') - tooltipGrundrauschen($this))->toBe(2);

    // Freitext bleibt moeglich.
    expect($inhalt)->toContain('Nicht im Katalog?');
});

test('die bereits gepflegten Dienste stehen vorbelegt im Feld', function () {
    $this->actingAs(userWithPermissions(['server_update']));
    [$customer, $server] = dienstUmgebung();

    $inhalt = $this->get("/{$customer->slug}/server/{$server->id}/edit")->assertOk()->getContent();

    // Alpine bekommt die Auswahl als JSON - inklusive des freien Dienstes, den
    // es im Katalog nicht gibt. @js() schreibt JSON.parse('["…"]'),
    // die Anfuehrungszeichen stehen also als Escape-Folge im Attribut.
    expect($inhalt)->toContain('\u0022AD\u0022');
    expect($inhalt)->toContain('\u0022Nextcloud\u0022');
});

test('gespeichert wird weiterhin eine Komma-Liste, auch mit freien Diensten', function () {
    $this->actingAs(userWithPermissions(['server_update']));
    [$customer, $server] = dienstUmgebung();

    $this->patch("/{$customer->slug}/server/{$server->id}", [
        'site_id' => $server->site_id, 'name' => $server->name,
        'operating_system_id' => $server->operating_system_id,
        'form_factor' => 'rack', 'full_depth' => '1', 'height_units' => 1,
        'services' => 'AD,DNS,Nextcloud',
    ])->assertSessionHasNoErrors();

    expect($server->fresh()->services)->toBe(['AD', 'DNS', 'Nextcloud']);
});

test('die Kachel in der Liste zeigt die Beschreibung beim Ueberfahren', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));
    [$customer] = dienstUmgebung();

    $inhalt = $this->get("/{$customer->slug}/server")->assertOk()->getContent();

    // Der Server traegt "AD" (im Katalog, mit Beschreibung) und "Nextcloud"
    // (nicht im Katalog): genau ein Hover-Fenster, die Seitenleiste abgezogen.
    expect($inhalt)->toContain('Verzeichnisdienst');
    expect(substr_count($inhalt, 'role="tooltip"') - tooltipGrundrauschen($this))->toBe(1);
});

test('die Beschreibung laesst sich im Admin pflegen', function () {
    // Der Dienste-Katalog haengt an der isAdmin-Middleware, nicht an einer
    // Berechtigung - ein Nutzer mit Rechten allein kommt hier nicht durch.
    $adminRolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $this->actingAs(User::factory()->create(['role_id' => $adminRolle->id]));

    // Auf den Status pruefen, nicht nur auf fehlende Fehler: Ein Post an eine
    // falsche Route erzeugt auch keine Session-Fehler und waere still gruen.
    $this->post('/admin/service/create', [
        'name' => 'PBS', 'description' => 'Proxmox Backup Server', 'color' => '#15803d',
    ])->assertRedirect(route('admin.service.index'));

    expect(Service::where('name', 'PBS')->first()->description)->toBe('Proxmox Backup Server');

    // Ohne Beschreibung geht es weiterhin.
    $this->post('/admin/service/create', ['name' => 'Ohne', 'color' => '#15803d'])
        ->assertRedirect(route('admin.service.index'));

    expect(Service::where('name', 'Ohne')->first()->description)->toBeNull();
});
