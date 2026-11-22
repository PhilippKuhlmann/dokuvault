<?php

use App\Models\Customer;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Blade;

/*
 * The opened server card sorts its blocks into fixed columns by theme:
 * Zugang | Gerät | System - System only with an agent, an empty column
 * not at all.
 */

function columnsServer(array $attributes = []): Server
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return Server::forceCreate(array_merge([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-DC01',
        'form_factor' => 'tower', 'manufacturer' => 'Dell', 'bmcIp' => '10.10.30.214',
    ], $attributes));
}

test('a server without agent: Zugang and Gerät in two columns', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));
    $server = columnsServer();

    $this->get(route('server.index', $server->customer))->assertOk()
        ->assertSee('lg:grid-cols-2', false)
        ->assertSeeInOrder(['Zugang', 'BMC', '10.10.30.214', 'Gerät', 'Hardware', 'Dell'])
        ->assertDontSee('lg:grid-cols-3', false);
});

test('a server the agent reports: a third column System', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));
    $server = columnsServer(['agent_reported_at' => now(), 'agent_details' => ['pve_version' => '9.2.3', 'kernel' => '7.0.6-2-pve']]);

    $this->get(route('server.index', $server->customer))->assertOk()
        ->assertSee('lg:grid-cols-3', false)
        ->assertSeeInOrder(['Zugang', 'Gerät', 'System', '7.0.6-2-pve']);
});

test('a column whose blocks are all empty is left out', function () {
    $leer = Blade::render('<x-card-column title="Zugang">{!! $nichts !!}</x-card-column>', ['nichts' => "<!--[if BLOCK]><![endif]-->\n  <!--[if ENDBLOCK]><![endif]-->"]);
    $voll = Blade::render('<x-card-column title="Zugang"><p>10.10.30.14</p></x-card-column>');

    expect(trim($leer))->toBe('')
        ->and($voll)->toContain('Zugang')->toContain('10.10.30.14');
});
