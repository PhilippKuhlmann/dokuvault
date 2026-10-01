<?php

use App\Models\Customer;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\SipAccount;
use App\Models\Site;

function dashboardKunde(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('support end lists devices and operating systems, but not distant ones', function () {
    [$customer, $site] = dashboardKunde();
    $this->actingAs(userWithPermissions(['server_viewAny', 'networkswitch_viewAny']));

    $alt = OperatingSystem::factory()->create(['name' => 'Windows Server 2012 R2', 'eol_date' => now()->subYear()]);
    $neu = OperatingSystem::factory()->create(['name' => 'Windows Server 2025', 'eol_date' => now()->addYears(8)]);

    Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => $alt->id, 'name' => 'SRV-ALT']);
    Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => $neu->id, 'name' => 'SRV-NEU']);
    NetworkSwitch::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SW-EOL', 'eol_date' => now()->addDays(30)->format('Y-m-d')]);

    $this->get(route('customer.dashboard', $customer))
        ->assertSee('Support-Ende')
        ->assertSee('SRV-ALT')
        ->assertSee('Windows Server 2012 R2')
        ->assertSee('SW-EOL')
        // Not via assertDontSee: SRV-NEU is in "Zuletzt geändert", it was just created.
        ->assertViewHas('endOfSupport', fn ($liste) => $liste->pluck('name')->sort()->values()->all() === ['SRV-ALT', 'SW-EOL']);
});

test('support end hides types the user may not list', function () {
    [$customer, $site] = dashboardKunde();
    $this->actingAs(userWithPermissions(['networkswitch_viewAny']));

    Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows 2008', 'eol_date' => now()->subYears(5)])->id,
        'name' => 'SRV-VERBORGEN']);

    $this->get(route('customer.dashboard', $customer))->assertDontSee('SRV-VERBORGEN');
});

test('recent changes show this customer only and only visible types', function () {
    [$customer, $site] = dashboardKunde();
    [$fremd] = dashboardKunde();
    $this->actingAs(userWithPermissions(['sipaccount_viewAny']));

    SipAccount::factory()->create(['customer_id' => $customer->id, 'provider' => 'Eigener-Anbieter']);
    SipAccount::factory()->create(['customer_id' => $fremd->id, 'provider' => 'Fremder-Anbieter']);
    NetworkSwitch::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SW-OHNE-RECHT']);

    $this->get(route('customer.dashboard', $customer))
        ->assertSee('Zuletzt geändert')
        ->assertSee('Eigener-Anbieter')
        ->assertDontSee('Fremder-Anbieter')
        ->assertDontSee('SW-OHNE-RECHT');
});

test('the counter row stays at twelve tiles at most', function () {
    [$customer] = dashboardKunde();
    $this->actingAs(admin());

    $this->get(route('customer.dashboard', $customer))
        ->assertViewHas('tiles', fn ($tiles) => count($tiles) <= 12);
});

test('dashboard links highlight the entry like the global search', function () {
    [$customer, $site] = dashboardKunde();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $server = Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows 2008', 'eol_date' => now()->subYear()])->id,
        'name' => 'SRV-MARKIERT', 'warranty_until' => now()->addDays(5)->format('Y-m-d')]);

    $ziel = route('server.index', [$customer, 'highlight' => $server->id]);

    $this->get(route('customer.dashboard', $customer))
        ->assertViewHas('endOfSupport', fn ($l) => $l->first()['url'] === $ziel)
        ->assertViewHas('expiringWarranties', fn ($l) => $l->first()['url'] === $ziel)
        ->assertViewHas('recentChanges', fn ($l) => $l->first()['url'] === $ziel);
});
