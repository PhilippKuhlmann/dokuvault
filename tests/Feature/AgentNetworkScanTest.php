<?php

use App\Models\Accesspoint;
use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Camera;
use App\Models\Customer;
use App\Models\NAS;
use App\Models\Network;
use App\Models\NetworkScanFinding;
use App\Models\NetworkSwitch;
use App\Models\Printer;
use App\Models\Site;
use App\Models\Ups;

/*
 * The network scan of the Windows service: printers, UPS, switches, access
 * points, NAS and cameras found by SNMP.
 * Switched on by hand only; scans at most once a day; nothing is created
 * without a technician - findings are taken over, linked or ignored.
 */

function scanSetup(?Customer $customer = null): array
{
    $customer ??= Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'Dienst', now()->addDays(60));

    $netz = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Büro', 'network' => '192.0.2.0', 'cidr' => '24',
    ]);

    $installation = AgentInstallation::forceCreate([
        'customer_id' => $customer->id,
        'agent_token_id' => $token->id,
        'kind' => 'windows',
        'machine_id' => 'scan-pc',
        'hostname' => 'SCAN-PC',
        'detected' => ['windows-server'],
        'roles' => ['windows-server', AgentInstallation::SCAN_ROLE],
        'scan_network_ids' => [$netz->id],
        'last_seen_at' => now(),
    ]);

    return compact('customer', 'site', 'token', 'plain', 'netz', 'installation');
}

function scanCheckin(string $plain, array $detected = ['windows-server'])
{
    return test()->withToken($plain)->postJson('/api/agent/checkin', [
        'kind' => 'windows',
        'machine_id' => 'scan-pc',
        'hostname' => 'SCAN-PC',
        'version' => '26.11.14-1200-abc',
        'detected' => $detected,
    ]);
}

function scanReport(string $plain, array $devices)
{
    return test()->withToken($plain)->postJson('/api/agent/network-scan', [
        'machine_id' => 'scan-pc',
        'devices' => $devices,
    ]);
}

function hpPrinter(array $overrides = []): array
{
    return $overrides + [
        'type' => 'printer',
        'ip' => '192.0.2.20',
        'serial' => 'CNB1234567',
        'name' => 'HP-OG2',
        'manufacturer' => 'HP',
        'model' => 'LaserJet M480',
        'firmware' => '2507252_052345',
    ];
}

test('the scan role is never switched on by detection', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'Dienst', now()->addDays(60));

    scanCheckin($plain, ['windows-server', AgentInstallation::SCAN_ROLE])->assertOk()
        ->assertJsonPath('roles', ['windows-server'])
        ->assertJsonPath('scan', null);
});

test('the checkin hands out the scan once a day, and again on request', function () {
    ['plain' => $plain, 'installation' => $installation] = scanSetup();

    scanCheckin($plain)->assertOk()
        ->assertJsonPath('scan.networks', ['192.0.2.0/24'])
        ->assertJsonPath('scan.communities', ['public']);

    // Five minutes later: not again.
    $this->travel(5)->minutes();
    scanCheckin($plain)->assertJsonPath('scan', null);

    // "Jetzt scannen".
    $installation->refresh()->forceFill(['scan_requested_at' => now()])->save();
    scanCheckin($plain)->assertJsonPath('scan.networks', ['192.0.2.0/24']);
    scanCheckin($plain)->assertJsonPath('scan', null);

    // A day later.
    $this->travel(1)->days();
    scanCheckin($plain)->assertJsonPath('scan.networks', ['192.0.2.0/24']);
});

test('without the role or without networks there is no scan', function () {
    ['plain' => $plain, 'installation' => $installation] = scanSetup();

    $installation->update(['scan_network_ids' => []]);
    scanCheckin($plain)->assertJsonPath('scan', null);

    $installation->update(['roles' => ['windows-server']]);
    scanCheckin($plain)->assertJsonPath('scan', null);
});

test('networks larger than a /22 and IPv6 networks are not scannable', function () {
    ['customer' => $customer, 'site' => $site, 'netz' => $netz] = scanSetup();

    $gross = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'network' => '10.0.0.0', 'cidr' => '16']);
    $v6 = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'network' => '2001:db8::', 'cidr' => '64']);
    $grenze = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'network' => '10.1.4.7', 'cidr' => '22']);

    $ids = AgentInstallation::scannableNetworks($customer->id)->pluck('id');

    expect($ids)->toContain($netz->id, $grenze->id)
        ->not->toContain($gross->id, $v6->id)
        ->and(AgentInstallation::cidrOf($grenze))->toBe('10.1.4.0/22');
});

test('a scanned printer becomes a finding, not a device', function () {
    ['plain' => $plain, 'customer' => $customer, 'netz' => $netz] = scanSetup();

    scanReport($plain, [hpPrinter(), ['type' => 'ups', 'ip' => '192.0.2.30', 'mac' => '00-C0-B7-11-22-33', 'model' => 'Smart-UPS 1500']])
        ->assertOk()
        ->assertJson(['findings_new' => 2, 'devices_updated' => 0]);

    expect(Printer::forCustomer($customer)->count())->toBe(0);

    $drucker = NetworkScanFinding::where('type', 'printer')->sole();
    expect($drucker->identifier)->toBe('CNB1234567')
        ->and($drucker->status)->toBe('new')
        ->and($drucker->firmware)->toBe('2507252_052345')
        ->and($drucker->network_id)->toBe($netz->id);

    // Without a serial number by its MAC address.
    expect(NetworkScanFinding::where('type', 'ups')->sole()->identifier)->toBe('mac:00:c0:b7:11:22:33');
});

test('a device without serial and MAC is skipped', function () {
    ['plain' => $plain] = scanSetup();

    scanReport($plain, [hpPrinter(['serial' => '  '])])
        ->assertOk()
        ->assertJson(['findings_new' => 0, 'skipped_without_identifier' => 1]);

    expect(NetworkScanFinding::count())->toBe(0);
});

test('a scan report needs the role switched on and a machine of this customer', function () {
    ['plain' => $plain, 'installation' => $installation] = scanSetup();
    $andere = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $andere->id]);
    [, $fremd] = AgentToken::generateFor($andere, $site, 'Dienst', now()->addDays(60));

    // The machine checked in with the first customer, not with this one.
    scanReport($fremd, [hpPrinter()])->assertNotFound();

    $installation->update(['roles' => ['windows-server']]);
    scanReport($plain, [hpPrinter()])->assertForbidden();

    expect(NetworkScanFinding::count())->toBe(0);
});

test('a taken-over printer is updated by later scans, its own fields stay', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    scanReport($plain, [hpPrinter()]);
    $fund = NetworkScanFinding::sole();

    $this->post(route('agent.finding.accept', [$customer, $fund]), ['name' => 'Drucker Empfang', 'site_id' => $site->id])
        ->assertRedirect(route('agent.findings', $customer));

    $drucker = Printer::forCustomer($customer)->sole();
    expect($drucker->name)->toBe('Drucker Empfang')
        ->and($drucker->agent_identifier)->toBe('CNB1234567')
        ->and($drucker->firmware)->toBe('2507252_052345')
        ->and($drucker->ipAddresses()->first()->address)->toBe('192.0.2.20')
        ->and(NetworkScanFinding::count())->toBe(0);

    $drucker->update(['name' => 'Von Hand', 'port' => '9100']);

    scanReport($plain, [hpPrinter(['firmware' => '2511010_000001', 'name' => 'HP-NEU', 'model' => null])])
        ->assertJson(['devices_updated' => 1, 'findings_new' => 0]);

    $drucker->refresh();
    expect($drucker->firmware)->toBe('2511010_000001')
        ->and($drucker->name)->toBe('Von Hand')
        ->and($drucker->port)->toBe('9100')
        // Not reported this time: stays.
        ->and($drucker->model)->toBe('LaserJet M480')
        ->and(NetworkScanFinding::count())->toBe(0);
});

test('a finding is linked to a printer documented by hand', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $vorhanden = Printer::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'Empfang', 'serialNumber' => 'CNB1234567']);
    scanReport($plain, [hpPrinter()]);
    $fund = NetworkScanFinding::sole();

    expect($fund->linkCandidates()->pluck('id')->all())->toBe([$vorhanden->id]);

    $this->post(route('agent.finding.link', [$customer, $fund]), ['device_id' => $vorhanden->id])->assertRedirect();

    $vorhanden->refresh();
    expect($vorhanden->agent_identifier)->toBe('CNB1234567')
        ->and($vorhanden->name)->toBe('Empfang')
        ->and($vorhanden->firmware)->toBe('2507252_052345')
        ->and(Printer::forCustomer($customer)->count())->toBe(1)
        ->and(NetworkScanFinding::count())->toBe(0);
});

test('an ignored finding is not offered again until brought back', function () {
    ['plain' => $plain, 'customer' => $customer] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    scanReport($plain, [hpPrinter()]);
    $fund = NetworkScanFinding::sole();

    $this->post(route('agent.finding.ignore', [$customer, $fund]))->assertRedirect();
    scanReport($plain, [hpPrinter(['ip' => '192.0.2.21'])])->assertJson(['findings_new' => 0]);

    $fund->refresh();
    expect($fund->status)->toBe('ignored')->and($fund->ip)->toBe('192.0.2.21');

    $this->get(route('agent.findings', $customer))->assertOk()->assertSee('Ignorierte Geräte');

    $this->post(route('agent.finding.restore', [$customer, $fund]))->assertRedirect();
    expect($fund->refresh()->status)->toBe('new');
});

test('findings of another customer cannot be touched', function () {
    ['plain' => $plain] = scanSetup();
    $fremd = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['agent_manage']));

    scanReport($plain, [hpPrinter()]);
    $fund = NetworkScanFinding::sole();

    $this->post(route('agent.finding.ignore', [$fremd, $fund]))->assertNotFound();
    expect($fund->refresh()->status)->toBe('new');
});

test('without the right to manage agents findings cannot be taken over', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();
    $this->actingAs(userWithPermissions(['server_viewAny']));

    scanReport($plain, [hpPrinter()]);
    $fund = NetworkScanFinding::sole();

    $this->post(route('agent.finding.accept', [$customer, $fund]), ['name' => 'X', 'site_id' => $site->id])->assertForbidden();
    expect(Printer::forCustomer($customer)->count())->toBe(0);
});

test('a ups is taken over at the site of its network', function () {
    ['plain' => $plain, 'customer' => $customer, 'installation' => $installation] = scanSetup();
    $zweigstelle = Site::factory()->create(['customer_id' => $customer->id]);
    $netz = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $zweigstelle->id, 'network' => '198.51.100.0', 'cidr' => '24']);
    $installation->update(['scan_network_ids' => [...$installation->scan_network_ids, $netz->id]]);

    scanReport($plain, [['type' => 'ups', 'ip' => '198.51.100.5', 'serial' => 'AS1234567890', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500']]);
    $fund = NetworkScanFinding::sole();

    expect($fund->suggestedSiteId())->toBe($zweigstelle->id)
        ->and($fund->suggestedName())->toBe('Smart-UPS 1500 (198.51.100.5)');

    $this->actingAs(userWithPermissions(['agent_manage']));
    $this->get(route('agent.findings', $customer))->assertOk()
        ->assertSee('Neu gefunden')
        ->assertSee('Smart-UPS 1500 (198.51.100.5)');

    $this->post(route('agent.finding.accept', [$customer, $fund]), ['name' => 'USV Zweigstelle', 'site_id' => $zweigstelle->id]);

    expect(Ups::forCustomer($customer)->sole()->site_id)->toBe($zweigstelle->id);
});

test('the agent page saves networks and communities, encrypted', function () {
    ['customer' => $customer, 'installation' => $installation, 'netz' => $netz] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->put(route('agent.installation.update', [$customer, $installation]), [
        'roles' => ['windows-server', AgentInstallation::SCAN_ROLE],
        'scan_settings' => 1,
        'scan_network_ids' => [$netz->id],
        'scan_communities' => 'geheim, public',
    ])->assertRedirect();

    $installation->refresh();
    expect($installation->scan_communities)->toBe(['geheim', 'public'])
        ->and($installation->getRawOriginal('scan_communities'))->not->toContain('geheim');

    // Saving without the scan form (the app, another agent) keeps them.
    $this->put(route('agent.installation.update', [$customer, $installation]), ['roles' => ['windows-server']]);
    expect($installation->refresh()->scan_communities)->toBe(['geheim', 'public']);
});

test('networks of another customer and too many communities are refused', function () {
    ['customer' => $customer, 'installation' => $installation] = scanSetup();
    ['netz' => $fremdesNetz] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->put(route('agent.installation.update', [$customer, $installation]), [
        'roles' => [AgentInstallation::SCAN_ROLE], 'scan_settings' => 1, 'scan_network_ids' => [$fremdesNetz->id],
    ])->assertSessionHasErrors('scan_network_ids.0');

    $this->put(route('agent.installation.update', [$customer, $installation]), [
        'roles' => [], 'scan_settings' => 1, 'scan_communities' => 'a,b,c,d,e,f',
    ])->assertSessionHasErrors('scan_communities');

    // Refused means nothing saved - not even the roles.
    expect($installation->refresh()->roles)->toContain(AgentInstallation::SCAN_ROLE);
});

test('"Jetzt scannen" requests a scan for the next checkin', function () {
    ['customer' => $customer, 'installation' => $installation] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->post(route('agent.installation.scan', [$customer, $installation]))->assertRedirect();

    expect($installation->refresh()->scan_requested_at)->not->toBeNull();
});

test('the dashboard points to new findings', function () {
    ['plain' => $plain, 'customer' => $customer] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    scanReport($plain, [hpPrinter(), hpPrinter(['serial' => 'CNB7654321', 'ip' => '192.0.2.21'])]);

    $this->get(route('customer.dashboard', $customer))->assertOk()
        ->assertSee('2 neue Geräte gefunden')
        ->assertSee(route('agent.findings', $customer), false);
});

test('Auto-Doku has its own menu with three pages, the old address redirects', function () {
    ['plain' => $plain, 'customer' => $customer] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));
    scanReport($plain, [hpPrinter()]);

    $this->get('/'.$customer->slug.'/agent')->assertRedirect(route('agent.index', $customer));

    foreach (['agent.index', 'agent.findings', 'agent.tokens'] as $seite) {
        $this->get(route($seite, $customer))->assertOk()
            ->assertSee('Auto-Doku')
            ->assertSee(route('agent.index', $customer), false)
            ->assertSee(route('agent.findings', $customer), false)
            ->assertSee(route('agent.tokens', $customer), false);
    }

    // The scan settings are on the agents page, the findings on their own.
    $this->get(route('agent.index', $customer))->assertSee('Netze und Communities ändern')->assertDontSee('Ignorierte Geräte');
    $this->get(route('agent.tokens', $customer))->assertSee('Neuen Token erzeugen')->assertDontSee('Installierte Agenten');
});

test('without findings and without a scan the findings page says how to switch it on', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['agent_manage']));

    $this->get(route('agent.findings', $customer))->assertOk()
        ->assertSee('Noch kein Netzwerk-Scan eingeschaltet');
});

test('a finding opens to everything the scan knows, and what it may match', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));
    Printer::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'Empfang', 'serialNumber' => 'CNB1234567']);

    scanReport($plain, [
        hpPrinter(),
        ['type' => 'ups', 'ip' => '192.0.2.30', 'mac' => '00-C0-B7-11-22-33', 'model' => 'Smart-UPS 1500'],
    ]);

    $this->get(route('agent.findings', $customer))->assertOk()
        ->assertSee('CNB1234567')
        ->assertSee('2507252_052345')
        ->assertSee('00:c0:b7:11:22:33')
        ->assertSee('192.0.2.0/24')
        ->assertSee('SCAN-PC')
        ->assertSee('Passt vermutlich zu')
        ->assertSee('Empfang (gleiche Seriennummer)');
});

test('switches, access points, NAS and cameras become findings too', function () {
    ['plain' => $plain] = scanSetup();

    scanReport($plain, [
        ['type' => 'switch', 'ip' => '192.0.2.2', 'serial' => 'FOC1234X0AB', 'name' => 'sw-core', 'manufacturer' => 'Cisco', 'model' => 'WS-C2960X-48FPD-L', 'firmware' => '15.2(7)E4'],
        ['type' => 'accesspoint', 'ip' => '192.0.2.3', 'mac' => '78:8A:20:01:02:03', 'name' => 'ap-og2', 'manufacturer' => 'Ubiquiti', 'model' => 'UAP-AC-Pro'],
        ['type' => 'nas', 'ip' => '192.0.2.4', 'serial' => '2150PDN123456', 'manufacturer' => 'Synology', 'model' => 'DS920+', 'firmware' => 'DSM 7.2.2-72806'],
        ['type' => 'camera', 'ip' => '192.0.2.5', 'mac' => '44:19:b6:aa:bb:cc', 'manufacturer' => 'Hikvision'],
    ])->assertOk()->assertJson(['findings_new' => 4]);

    expect(NetworkScanFinding::orderBy('ip')->pluck('identifier', 'type')->all())->toBe([
        'switch' => 'FOC1234X0AB',
        'accesspoint' => 'mac:78:8a:20:01:02:03',
        'nas' => '2150PDN123456',
        'camera' => 'mac:44:19:b6:aa:bb:cc',
    ]);
});

test('an unknown kind is refused', function () {
    ['plain' => $plain] = scanSetup();

    scanReport($plain, [hpPrinter(['type' => 'router'])])->assertUnprocessable();
});

test('a taken-over switch, NAS and camera carry identifier and firmware', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    scanReport($plain, [
        ['type' => 'switch', 'ip' => '192.0.2.2', 'serial' => 'FOC1234X0AB', 'manufacturer' => 'Cisco', 'model' => 'WS-C2960X-48FPD-L', 'firmware' => '15.2(7)E4'],
        ['type' => 'nas', 'ip' => '192.0.2.4', 'serial' => '2150PDN123456', 'manufacturer' => 'Synology', 'model' => 'DS920+', 'firmware' => 'DSM 7.2.2-72806'],
        ['type' => 'camera', 'ip' => '192.0.2.5', 'mac' => '44:19:b6:aa:bb:cc', 'manufacturer' => 'Hikvision', 'firmware' => 'V5.7.3'],
    ]);

    foreach (NetworkScanFinding::all() as $fund) {
        $this->post(route('agent.finding.accept', [$customer, $fund]), ['name' => 'Neu '.$fund->type, 'site_id' => $site->id])
            ->assertRedirect(route('agent.findings', $customer));
    }

    $switch = NetworkSwitch::forCustomer($customer)->sole();
    expect($switch->agent_identifier)->toBe('FOC1234X0AB')
        ->and($switch->serialNumber)->toBe('FOC1234X0AB')
        ->and($switch->firmware)->toBe('15.2(7)E4')
        ->and($switch->ipAddresses()->first()->address)->toBe('192.0.2.2');

    $nas = NAS::forCustomer($customer)->sole();
    expect($nas->agent_identifier)->toBe('2150PDN123456')->and($nas->firmware)->toBe('DSM 7.2.2-72806');

    $kamera = Camera::forCustomer($customer)->sole();
    expect($kamera->agent_identifier)->toBe('mac:44:19:b6:aa:bb:cc')->and($kamera->name)->toBe('Neu camera');

    // The next scan updates them instead of finding them again.
    scanReport($plain, [
        ['type' => 'switch', 'ip' => '192.0.2.2', 'serial' => 'FOC1234X0AB', 'firmware' => '15.2(7)E11'],
    ])->assertJson(['devices_updated' => 1, 'findings_new' => 0]);
    expect($switch->fresh()->firmware)->toBe('15.2(7)E11');
});

test('a switch the UniFi agent documents is not offered again', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();

    $unifi = NetworkSwitch::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'USW-24', 'agent_identifier' => '78:8a:20:aa:bb:cc',
    ]);
    Accesspoint::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'AP-Flur', 'agent_identifier' => '78:8a:20:dd:ee:ff', 'serialNumber' => 'UAP123',
    ]);
    $unifi->ipAddresses()->create(['address' => '192.0.2.40', 'customer_id' => $customer->id]);

    scanReport($plain, [
        // Same address, a different MAC (a port, not the base address).
        ['type' => 'switch', 'ip' => '192.0.2.40', 'mac' => '78:8a:20:aa:bb:cd', 'model' => 'USW-24'],
        // Same serial.
        ['type' => 'accesspoint', 'ip' => '192.0.2.41', 'serial' => 'UAP123'],
        // Same MAC as the UniFi identifier.
        ['type' => 'switch', 'ip' => '192.0.2.42', 'mac' => '78:8A:20:AA:BB:CC'],
    ])->assertJson(['findings_new' => 0, 'skipped_other_agent' => 3]);

    expect(NetworkScanFinding::count())->toBe(0)
        ->and($unifi->fresh()->name)->toBe('USW-24');
});

test('a device the scan replaced at the same address is still offered', function () {
    ['plain' => $plain, 'customer' => $customer, 'site' => $site] = scanSetup();

    $alt = NetworkSwitch::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Alter Switch', 'agent_identifier' => 'OLD123',
    ]);
    $alt->ipAddresses()->create(['address' => '192.0.2.2', 'customer_id' => $customer->id]);

    scanReport($plain, [['type' => 'switch', 'ip' => '192.0.2.2', 'serial' => 'NEW456']])
        ->assertJson(['findings_new' => 1, 'skipped_other_agent' => 0]);
});

test('the findings page lists the kinds in their order', function () {
    ['plain' => $plain, 'customer' => $customer] = scanSetup();
    $this->actingAs(userWithPermissions(['agent_manage']));

    scanReport($plain, [
        ['type' => 'camera', 'ip' => '192.0.2.5', 'mac' => '44:19:b6:aa:bb:cc', 'name' => 'cam-hof'],
        ['type' => 'switch', 'ip' => '192.0.2.9', 'serial' => 'S1', 'name' => 'sw-9'],
        ['type' => 'switch', 'ip' => '192.0.2.10', 'serial' => 'S2', 'name' => 'sw-10'],
        hpPrinter(['ip' => '192.0.2.50']),
    ]);

    $this->get(route('agent.findings', $customer))->assertOk()
        ->assertSeeInOrder(['HP-OG2', 'sw-9', 'sw-10', 'cam-hof']);
});

test('switch, access point, NAS and camera forms keep the firmware', function (string $typ, string $klasse) {
    ['customer' => $customer, 'site' => $site] = scanSetup();
    $this->actingAs(userWithPermissions([$typ.'_viewAny', $typ.'_create']));

    imModal($typ, $customer, ['site_id' => $site->id, 'name' => 'Mit Firmware', 'port' => '80', 'firmware' => '1.2.3'])
        ->assertHasNoErrors();

    expect($klasse::where('name', 'Mit Firmware')->sole()->firmware)->toBe('1.2.3');
})->with([
    ['networkswitch', NetworkSwitch::class],
    ['accesspoint', Accesspoint::class],
    ['nas', NAS::class],
    ['camera', Camera::class],
]);
