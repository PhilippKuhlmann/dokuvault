<?php

use App\Livewire\DeviceIpAddresses;
use App\Models\Accesspoint;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Ein per DHCP versorgtes Geraet steht am Pool, nicht an einer Adresse.
 *
 * Eine geliehene Adresse als eigene Zeile zu zeigen behauptet etwas, das
 * morgen nicht mehr stimmt - und genau dafuer schaut man in den Plan.
 */
function dhcpNetz(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $netz = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.0.0.0', 'cidr' => '24',
        'dhcpStart' => '10.0.0.100', 'dhcpEnd' => '10.0.0.200',
    ]);

    return [$customer, $site, $netz];
}

function apMitAdresse(Customer $customer, Site $site, Network $netz, string $name, ?string $adresse, bool $dhcp = false): Accesspoint
{
    $ap = Accesspoint::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => $name,
    ]);
    // Bei DHCP ohne Adresse: Was zaehlt, ist das Netz.
    $ap->ipAddresses()->create([
        'address' => $dhcp ? null : $adresse, 'customer_id' => $customer->id,
        'network_id' => $netz->id, 'dhcp' => $dhcp,
    ]);

    return $ap;
}

test('ein DHCP-Geraet im Pool bekommt keine eigene Zeile, sondern steht am Bereich', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', true);

    $antwort = $this->get("/{$customer->slug}/ip-plan")->assertOk();

    // Der Name steht da - aber nicht als Zeile "10.0.0.150".
    $antwort->assertSee('AP-Buero');
    $antwort->assertDontSee('10.0.0.150');
    $antwort->assertSee('DHCP-Bereich');
});

test('mehrere DHCP-Geraete stehen gemeinsam am Bereich', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', true);
    apMitAdresse($customer, $site, $netz, 'AP-Lager', '10.0.0.151', true);

    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('AP-Buero')
        ->assertSee('AP-Lager')
        ->assertDontSee('10.0.0.150')
        ->assertDontSee('10.0.0.151');
});

test('eine fest vergebene Adresse im DHCP-Bereich bleibt sichtbar', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.150');

    // Das ist ein Konflikt - fest vergeben mitten im Pool. Genau der gehoert
    // gesehen, nicht zusammengefasst.
    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('10.0.0.150')
        ->assertSee('AP-Fest');
});

test('DHCP und feste Adressen stehen im selben Netz nebeneinander', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Geliehen', null, true);
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.150');

    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('AP-Geliehen')
        ->assertSee('10.0.0.150')
        ->assertSee('AP-Fest');
});

test('ohne gepflegten DHCP-Bereich stehen die Geraete unter der Tabelle', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $netz = Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network' => '10.0.0.0', 'cidr' => '24',
        'dhcpStart' => null, 'dhcpEnd' => null,
    ]);
    apMitAdresse($customer, $site, $netz, 'AP-Buero', null, true);

    // Ohne Bereich gibt es keine Zeile, an der sie stehen koennten. Sie
    // wegzulassen hiesse, dass ein dokumentiertes Geraet im Plan fehlt.
    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('AP-Buero')
        ->assertSee('kein DHCP-Bereich gepflegt');
});

test('ein Geraet im Pool zaehlt nicht zusaetzlich als belegte Adresse', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', true);
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.50');

    // Der Pool steht als Ganzes in der Rechnung. Zaehlte die geliehene
    // Adresse zusaetzlich, stuende in der Kopfzeile mehr belegt, als die
    // Tabelle darunter zeigt.
    $plan = $this->get("/{$customer->slug}/ip-plan")->assertOk()->viewData('plans')->first()['plan'];

    expect($plan['usedCount'])->toBe(1);
    expect(collect($plan['rows'])->where('kind', 'device')->count())->toBe(1);
});

test('am Geraet steht bei DHCP nur "DHCP", nicht die Adresse', function () {
    $this->actingAs(userWithPermissions(['accesspoint_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', true);
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.50');

    $antwort = $this->get("/{$customer->slug}/accesspoint")->assertOk();

    // Die geliehene Adresse stimmt nur bis zum naechsten Neustart - sie hier
    // hinzuschreiben laedt dazu ein, sich darauf zu verlassen.
    $antwort->assertDontSee('10.0.0.150');
    $antwort->assertSee('DHCP');

    // Die feste bleibt, wo sie ist.
    $antwort->assertSee('10.0.0.50');
});

test('bei DHCP wird keine Adresse gespeichert, nur das Netz', function () {
    [$customer, $site, $netz] = dhcpNetz();
    $ap = apMitAdresse($customer, $site, $netz, 'AP-Buero', null, true);

    // Welche Adresse das Geraet gerade hat, ist morgen eine andere. Was
    // bleibt, ist das Netz - daran haengt die Zuordnung im Plan.
    $adresse = $ap->ipAddresses()->first();
    expect($adresse->address)->toBeNull();
    expect($adresse->network_id)->toBe($netz->id);
    expect($adresse->anzeige())->toBe('DHCP');
    expect($adresse->istDhcp())->toBeTrue();
});

test('der Schalter im Formular setzt DHCP', function () {
    $this->actingAs(userWithPermissions(['accesspoint_update']));
    [$customer, $site, $netz] = dhcpNetz();
    $ap = Accesspoint::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'AP-Buero']);

    Livewire::test(DeviceIpAddresses::class, ['model' => $ap, 'customer' => $customer])
        ->set('address', '10.0.0.150')
        ->set('network_id', $netz->id)
        ->set('dhcp', true)
        ->call('add')
        ->assertHasNoErrors();

    $adresse = $ap->fresh()->ipAddresses()->first();
    expect($adresse->istDhcp())->toBeTrue();
    expect($adresse->anzeige())->toBe('DHCP');
});

test('ohne den Schalter bleibt die Adresse eine feste', function () {
    $this->actingAs(userWithPermissions(['accesspoint_update']));
    [$customer, $site, $netz] = dhcpNetz();
    $ap = Accesspoint::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'AP-Buero']);

    Livewire::test(DeviceIpAddresses::class, ['model' => $ap, 'customer' => $customer])
        ->set('address', '10.0.0.150')
        ->call('add')
        ->assertHasNoErrors();

    expect($ap->fresh()->ipAddresses()->first()->istDhcp())->toBeFalse();
});

test('Bezeichnung und DHCP stehen nebeneinander', function () {
    $this->actingAs(userWithPermissions(['accesspoint_update']));
    [$customer, $site, $netz] = dhcpNetz();
    $ap = Accesspoint::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'AP-Buero']);

    // Genau das ging vorher nicht: Die Marke sass in der Bezeichnung und
    // nahm ihr den Platz.
    Livewire::test(DeviceIpAddresses::class, ['model' => $ap, 'customer' => $customer])
        ->set('dhcp', true)
        ->set('network_id', $netz->id)
        ->set('label', 'Uplink Dachboden')
        ->call('add')
        ->assertHasNoErrors();

    $adresse = $ap->fresh()->ipAddresses()->first();
    expect($adresse->istDhcp())->toBeTrue();
    expect($adresse->label)->toBe('Uplink Dachboden');
    expect($adresse->address)->toBeNull();
});

test('bei DHCP wird keine Adresse angenommen und das VLAN verlangt', function () {
    $this->actingAs(userWithPermissions(['accesspoint_update']));
    [$customer, $site, $netz] = dhcpNetz();
    $ap = Accesspoint::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'AP-Buero']);

    // Ohne Netz saehe die Zuordnung so aus: "haengt irgendwo per DHCP".
    Livewire::test(DeviceIpAddresses::class, ['model' => $ap, 'customer' => $customer])
        ->set('dhcp', true)
        ->call('add')
        ->assertHasErrors('network_id');

    expect($ap->fresh()->ipAddresses()->count())->toBe(0);
});

test('das Anhaken raeumt eine angefangene Adresse weg', function () {
    $this->actingAs(userWithPermissions(['accesspoint_update']));
    [$customer, $site, $netz] = dhcpNetz();
    $ap = Accesspoint::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'AP-Buero']);

    // Sonst bliebe sie stehen, die Pruefung meldete "darf nicht vorhanden
    // sein", und man suchte den Fehler an einem Feld, das nicht mehr da ist.
    Livewire::test(DeviceIpAddresses::class, ['model' => $ap, 'customer' => $customer])
        ->set('address', '10.0.0.150')
        ->set('dhcp', true)
        ->assertSet('address', '')
        ->set('network_id', $netz->id)
        ->call('add')
        ->assertHasNoErrors();

    expect($ap->fresh()->ipAddresses()->first()->address)->toBeNull();
});

test('das Wort DHCP in der Bezeichnung loest nichts mehr aus', function () {
    $this->actingAs(userWithPermissions(['accesspoint_update']));
    [$customer, $site, $netz] = dhcpNetz();
    $ap = Accesspoint::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'AP-Buero']);

    // Die versteckte Vereinbarung ist weg: Eine Bezeichnung ist eine
    // Bezeichnung, nicht ein Schalter mit richtiger Schreibweise.
    Livewire::test(DeviceIpAddresses::class, ['model' => $ap, 'customer' => $customer])
        ->set('address', '10.0.0.150')
        ->set('label', 'DHCP')
        ->call('add');

    $adresse = $ap->fresh()->ipAddresses()->first();
    expect($adresse->istDhcp())->toBeFalse();
    expect($adresse->anzeige())->toBe('10.0.0.150');
});

test('der Agent setzt die Spalte, nicht die Bezeichnung', function () {
    [$customer, $site, $netz] = dhcpNetz();
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', ['accesspoints' => [[
        'identifier' => 'ap-1', 'name' => 'AP-Buero', 'ip' => '10.0.0.150', 'dhcp' => true,
    ]]])->assertOk();

    $adresse = Accesspoint::where('agent_identifier', 'ap-1')->first()->ipAddresses()->first();
    expect($adresse->istDhcp())->toBeTrue();
    // Keine Adresse, aber das Netz - abgeleitet aus der gemeldeten Adresse.
    expect($adresse->address)->toBeNull();
    expect($adresse->network_id)->toBe($netz->id);
    // Die Bezeichnung gehoert dem Nutzer - der Agent fasst sie nicht an.
    expect($adresse->label)->toBeNull();
});

test('eine von Hand gesetzte Bezeichnung ueberlebt den Agentenlauf', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $melden = fn (bool $dhcp) => $this->withToken($plain)->postJson('/api/agent/unifi', ['accesspoints' => [[
        'identifier' => 'ap-1', 'name' => 'AP-Buero', 'ip' => '10.0.0.150', 'dhcp' => $dhcp,
    ]]])->assertOk();

    $melden(true);
    $adresse = Accesspoint::where('agent_identifier', 'ap-1')->first()->ipAddresses()->first();
    $adresse->update(['label' => 'Uplink Dachboden']);

    $melden(false);

    expect($adresse->fresh()->label)->toBe('Uplink Dachboden');
    expect($adresse->fresh()->istDhcp())->toBeFalse();
});
