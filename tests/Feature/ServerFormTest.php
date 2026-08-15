<?php

use App\Livewire\GlobalSearch;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Das Server-Formular fuehrt ip1/ip2 nicht mehr - Adressen kommen aus dem
 * IP-Block. Diese Tests halten die Folgen davon fest.
 */
function serverFormUmgebung(): array
{
    $customer = Customer::factory()->create(['name' => 'Formkunde']);
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    return [$customer, $site, $os];
}

function serverMitAdresse(Customer $customer, Site $site, OperatingSystem $os, string $name, string $adresse): Server
{
    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => $name, 'operating_system_id' => $os->id,
    ]);

    IpAddress::create([
        'customer_id' => $customer->id, 'ipable_type' => Server::class,
        'ipable_id' => $server->id, 'address' => $adresse,
    ]);

    return $server;
}

test('das Formular bietet kein IP-1- und IP-2-Feld mehr', function () {
    $this->actingAs(userWithPermissions(['server_create', 'server_update']));
    [$customer, $site, $os] = serverFormUmgebung();

    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-01', 'operating_system_id' => $os->id,
    ]);

    foreach (["/{$customer->slug}/server/create", "/{$customer->slug}/server/{$server->id}/edit"] as $url) {
        $inhalt = $this->get($url)->assertOk()->getContent();

        expect($inhalt)->not->toContain('name="ip1"');
        expect($inhalt)->not->toContain('name="ip2"');
    }
});

test('die Liste zeigt die dokumentierte Adresse aus dem IP-Block', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));
    [$customer, $site, $os] = serverFormUmgebung();

    serverMitAdresse($customer, $site, $os, 'SRV-NEU', '10.20.30.40');

    $this->get("/{$customer->slug}/server")->assertOk()->assertSee('10.20.30.40');
});

test('die globale Suche findet einen Server ueber eine Adresse aus dem IP-Block', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));
    [$customer, $site, $os] = serverFormUmgebung();

    serverMitAdresse($customer, $site, $os, 'SRV-GESUCHT', '172.16.5.99');

    Livewire::test(GlobalSearch::class)
        ->set('search', '172.16.5.99')
        ->assertSee('SRV-GESUCHT');
});

test('das Formular hat oben einen Zurueck-Link auf die Liste', function () {
    $this->actingAs(userWithPermissions(['server_create', 'server_update']));
    [$customer, $site, $os] = serverFormUmgebung();

    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-01', 'operating_system_id' => $os->id,
    ]);

    foreach (["/{$customer->slug}/server/create", "/{$customer->slug}/server/{$server->id}/edit"] as $url) {
        $inhalt = $this->get($url)->assertOk()->getContent();

        // Das href aus genau diesem Anker ziehen: assertSee wuerde die Listen-URL
        // auch in der Seitenleiste finden und den Link selbst nie pruefen. Der
        // Text muss mit rein, sonst trifft das Muster den ersten beliebigen
        // Anker mit Symbol - davon hat die Seitenleiste ein Dutzend.
        preg_match('#<a href="([^"]+)"[^>]*>\s*<svg.*?</svg>\s*Zurück\s*</a>#s', $inhalt, $treffer);

        expect($treffer[1] ?? null)->toEndWith("/{$customer->slug}/server");
    }
});
