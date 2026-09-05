<?php

use App\Http\Controllers\API\AgentController;
use App\Models\Accesspoint;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;

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

function apMitAdresse(Customer $customer, Site $site, Network $netz, string $name, string $adresse, ?string $label): Accesspoint
{
    $ap = Accesspoint::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => $name,
    ]);
    $ap->ipAddresses()->create([
        'address' => $adresse, 'customer_id' => $customer->id,
        'network_id' => $netz->id, 'label' => $label,
    ]);

    return $ap;
}

test('ein DHCP-Geraet im Pool bekommt keine eigene Zeile, sondern steht am Bereich', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', AgentController::MARKE_DHCP);

    $antwort = $this->get("/{$customer->slug}/ip-plan")->assertOk();

    // Der Name steht da - aber nicht als Zeile "10.0.0.150".
    $antwort->assertSee('AP-Buero');
    $antwort->assertDontSee('10.0.0.150');
    $antwort->assertSee('DHCP-Bereich');
});

test('mehrere DHCP-Geraete stehen gemeinsam am Bereich', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', AgentController::MARKE_DHCP);
    apMitAdresse($customer, $site, $netz, 'AP-Lager', '10.0.0.151', AgentController::MARKE_DHCP);

    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('AP-Buero')
        ->assertSee('AP-Lager')
        ->assertDontSee('10.0.0.150')
        ->assertDontSee('10.0.0.151');
});

test('eine fest vergebene Adresse im DHCP-Bereich bleibt sichtbar', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.150', null);

    // Das ist ein Konflikt - fest vergeben mitten im Pool. Genau der gehoert
    // gesehen, nicht zusammengefasst.
    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('10.0.0.150')
        ->assertSee('AP-Fest');
});

test('sitzt beides auf derselben Adresse, bleibt die Zeile stehen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Geliehen', '10.0.0.150', AgentController::MARKE_DHCP);
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.150', null);

    // Zwei Geraete auf einer Adresse ist ein Fehler im Netz. Ihn hinter dem
    // Pool verschwinden zu lassen waere die schlechteste Antwort.
    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('10.0.0.150')
        ->assertSee('AP-Fest');
});

test('ausserhalb des Pools bleibt eine DHCP-Adresse eine eigene Zeile', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Draussen', '10.0.0.50', AgentController::MARKE_DHCP);

    // Ausserhalb des gepflegten Bereichs vergeben: Entweder stimmt der Bereich
    // nicht oder das Geraet haengt an einem anderen DHCP-Server. Beides will
    // man sehen.
    $this->get("/{$customer->slug}/ip-plan")->assertOk()
        ->assertSee('10.0.0.50')
        ->assertSee('AP-Draussen');
});

test('ein Geraet im Pool zaehlt nicht zusaetzlich als belegte Adresse', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site, $netz] = dhcpNetz();
    apMitAdresse($customer, $site, $netz, 'AP-Buero', '10.0.0.150', AgentController::MARKE_DHCP);
    apMitAdresse($customer, $site, $netz, 'AP-Fest', '10.0.0.50', null);

    // Der Pool steht als Ganzes in der Rechnung. Zaehlte die geliehene
    // Adresse zusaetzlich, stuende in der Kopfzeile mehr belegt, als die
    // Tabelle darunter zeigt.
    $plan = $this->get("/{$customer->slug}/ip-plan")->assertOk()->viewData('plans')->first()['plan'];

    expect($plan['usedCount'])->toBe(1);
    expect(collect($plan['rows'])->where('kind', 'device')->count())->toBe(1);
});
