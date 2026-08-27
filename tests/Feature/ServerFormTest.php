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
