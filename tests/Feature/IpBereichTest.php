<?php

use App\Livewire\IpBereiche;
use App\Models\Customer;
use App\Models\IpRange;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

function netzMitBereich(Customer $kunde, array $werte = []): Network
{
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    return Network::create(array_merge([
        'customer_id' => $kunde->id,
        'site_id' => $standort->id,
        'description' => 'Clients',
        'vlanId' => 250,
        'network' => '10.10.250.0',
        'subnetmask' => '255.255.255.0',
        'cidr' => 24,
    ], $werte));
}

function bereichAnlegen(Network $netz, string $von, string $bis, string $label): IpRange
{
    return IpRange::create([
        'customer_id' => $netz->customer_id,
        'network_id' => $netz->id,
        'from_ip' => $von,
        'to_ip' => $bis,
        'label' => $label,
    ]);
}

/*
|--------------------------------------------------------------------------
| Die Anzeige im IPAM - darum ging es
|--------------------------------------------------------------------------
*/

test('ein reservierter Bereich steht im Plan, auch wenn nichts belegt ist', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');

    // Der Punkt der ganzen Sache: Ohne den Bereich stuende hier "frei", und
    // niemand wuesste, dass die elf Adressen vergeben sind, bevor sie es sind.
    $this->get("/{$kunde->slug}/ip-plan")
        ->assertStatus(200)
        ->assertSee('Proxmox-Server')
        ->assertSee('reserviert')
        ->assertSee('10.10.250.10');
});

test('eine belegte Adresse im Bereich bleibt belegt und zeigt den Bereich', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'server_viewAny']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');

    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $server = Server::create([
        'customer_id' => $kunde->id, 'site_id' => $standort->id, 'name' => 'PVE-01',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Debian 12'])->id,
    ]);
    $server->ipAddresses()->create([
        'customer_id' => $kunde->id, 'network_id' => $netz->id, 'address' => '10.10.250.12',
    ]);

    $antwort = $this->get("/{$kunde->slug}/ip-plan")->assertStatus(200);

    // Der Server steht als Server da - und daneben, wozu der Block gehoert.
    // Ohne das saehe die Reservierung loechrig aus, sobald jemand eine Adresse
    // daraus vergibt.
    $antwort->assertSee('PVE-01')->assertSee('Proxmox-Server');
});

test('zwei Bereiche verschmelzen nicht zu einem', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');
    bereichAnlegen($netz, '10.10.250.21', '10.10.250.30', 'Drucker');

    // Sie stossen aneinander. Ohne die Unterscheidung nach Beschriftung stuende
    // dort eine Zeile ".10 - .30 Proxmox-Server" - und die Drucker waeren weg.
    $this->get("/{$kunde->slug}/ip-plan")
        ->assertStatus(200)
        ->assertSee('Proxmox-Server')
        ->assertSee('Drucker');
});

test('der DHCP-Bereich schlägt die Reservierung', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde, ['dhcpStart' => '10.10.250.100', 'dhcpEnd' => '10.10.250.200']);
    bereichAnlegen($netz, '10.10.250.150', '10.10.250.160', 'Falsch geplant');

    // Wer beides uebereinanderlegt, soll das sehen: Ein Bereich, den der
    // DHCP-Server selbst vergibt, ist kein reservierter Block mehr.
    //
    // Geprueft am ungeteilten DHCP-Block: Haette die Reservierung gewonnen,
    // stuende dort ".100 - .149", ".150 - .160 reserviert", ".161 - .200".
    // Der Name des Bereichs taugt nicht als Gegenprobe - er steht ohnehin in
    // der Liste der Bereiche unter der Tabelle.
    $this->get("/{$kunde->slug}/ip-plan")
        ->assertStatus(200)
        ->assertSee('DHCP-Bereich')
        ->assertSee('10.10.250.100 – 10.10.250.200');
});

/*
|--------------------------------------------------------------------------
| Anlegen
|--------------------------------------------------------------------------
*/

test('ein Bereich lässt sich anlegen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->call('oeffnen')
        ->set('from_ip', '10.10.250.10')
        ->set('to_ip', '10.10.250.20')
        ->set('label', 'Proxmox-Server')
        ->call('speichern')
        ->assertHasNoErrors();

    $bereich = IpRange::first();

    expect($bereich)->not->toBeNull()
        ->and($bereich->label)->toBe('Proxmox-Server')
        ->and($bereich->anzahl())->toBe(11);
});

test('eine Adresse außerhalb des Netzes wird abgewiesen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->call('oeffnen')
        ->set('from_ip', '10.10.250.10')
        ->set('to_ip', '10.10.251.20')
        ->set('label', 'Daneben')
        ->call('speichern')
        ->assertHasErrors(['to_ip']);

    expect(IpRange::count())->toBe(0);
});

test('eine Endadresse vor der Anfangsadresse wird abgewiesen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->call('oeffnen')
        ->set('from_ip', '10.10.250.20')
        ->set('to_ip', '10.10.250.10')
        ->set('label', 'Verdreht')
        ->call('speichern')
        ->assertHasErrors(['to_ip']);
});

test('zwei Bereiche dürfen sich nicht überschneiden', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');

    // Zwei Reservierungen fuer dieselbe Adresse waeren ein Widerspruch in der
    // Doku, kein Zustand, den man abbilden will.
    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->call('oeffnen')
        ->set('from_ip', '10.10.250.15')
        ->set('to_ip', '10.10.250.25')
        ->set('label', 'Drucker')
        ->call('speichern')
        ->assertHasErrors(['from_ip']);

    expect(IpRange::count())->toBe(1);
});

test('ohne network_update legt niemand einen Bereich an', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->call('oeffnen')
        ->assertForbidden();
});

test('das Netz eines fremden Kunden ist nicht erreichbar', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $fremdesNetz = netzMitBereich($fremder);

    // Ueber die Id waere es sonst erreichbar - und damit die Netzplanung eines
    // anderen Kunden.
    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $fremdesNetz])
        ->assertNotFound();
});

test('ein auf einen Kunden festgelegter Nutzer sieht keine fremde Netzplanung', function () {
    // Zwei verschiedene Fragen: ob das Netz zum Kunden gehoert - und ob dieser
    // Benutzer den Kunden ueberhaupt sehen darf. Die zweite fehlte zuerst; ein
    // bestehender Invariantentest hat es gefunden.
    $kunde = Customer::factory()->create();
    $fremder = Customer::factory()->create();

    $nutzer = userWithPermissions(['network_viewAny', 'network_update']);
    $nutzer->forceFill(['customer_id' => $fremder->id])->save();
    $this->actingAs($nutzer->fresh());

    $netz = netzMitBereich($kunde);

    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->assertForbidden();
});

test('gelöscht wird nur im eigenen Netz', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    $fremder = Customer::factory()->create();
    $fremdesNetz = netzMitBereich($fremder);
    $fremderBereich = bereichAnlegen($fremdesNetz, '10.10.250.10', '10.10.250.20', 'Fremd');

    Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->call('loeschen', $fremderBereich->id);

    expect(IpRange::find($fremderBereich->id))->not->toBeNull();
});

test('mit dem Netz verschwindet auch der Bereich', function () {
    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');

    // Ein Bereich ohne sein Netz waere eine Zeile, die nirgends erscheint.
    $netz->forceDelete();

    expect(IpRange::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Farben - damit zwei Bereiche nebeneinander zwei Bereiche bleiben
|--------------------------------------------------------------------------
*/

test('zwei Bereiche bekommen zwei Farben', function () {
    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');
    bereichAnlegen($netz, '10.10.250.21', '10.10.250.30', 'Drucker');

    $eingefaerbt = IpRange::eingefaerbt(IpRange::all());

    expect($eingefaerbt[0]->farbe)->not->toBe($eingefaerbt[1]->farbe)
        ->and($eingefaerbt[0]->farbe['rand'])->not->toBe($eingefaerbt[1]->farbe['rand']);
});

test('die Farbe folgt der Adresse, nicht der Anlegereihenfolge', function () {
    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    // Verkehrt herum angelegt: der hintere zuerst.
    bereichAnlegen($netz, '10.10.250.100', '10.10.250.110', 'Später im Netz');
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Vorne im Netz');

    $eingefaerbt = IpRange::eingefaerbt(IpRange::all());

    // Sortiert nach Adresse, damit die Farbe im Plan von Zeile zu Zeile
    // wechselt - und nicht nach from_ip als Text, dort käme ".100" vor ".20".
    expect($eingefaerbt[0]->label)->toBe('Vorne im Netz')
        ->and($eingefaerbt[1]->label)->toBe('Später im Netz');
});

test('nach der letzten Farbe geht es wieder von vorn los', function () {
    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);

    $anzahl = count(config('custom.ipam_farben'));

    for ($i = 0; $i <= $anzahl; $i++) {
        $start = 10 + $i * 2;
        bereichAnlegen($netz, "10.10.250.{$start}", '10.10.250.'.($start + 1), "Block {$i}");
    }

    $eingefaerbt = IpRange::eingefaerbt(IpRange::all());

    // Der erste nach dem Umlauf bekommt wieder die erste Farbe - und liegt
    // weit genug vom Original entfernt, dass das nicht stört.
    expect($eingefaerbt[$anzahl]->farbe)->toBe($eingefaerbt[0]->farbe);
});

test('Tabelle und Liste färben denselben Bereich gleich', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_update']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');
    bereichAnlegen($netz, '10.10.250.21', '10.10.250.30', 'Drucker');

    // Die Liste unter der Tabelle ist deren Legende. Zwei getrennte
    // Zuordnungen würden auseinanderlaufen, sobald eine Sortierung abweicht.
    $ausListe = Livewire::test(IpBereiche::class, ['customer' => $kunde, 'network' => $netz])
        ->viewData('bereiche')
        ->mapWithKeys(fn ($b) => [$b->label => $b->farbe['rand']]);

    $ausPlan = IpRange::eingefaerbt(IpRange::all())
        ->mapWithKeys(fn ($b) => [$b->label => $b->farbe['rand']]);

    expect($ausListe->all())->toBe($ausPlan->all());
});

test('der Balken oben zeigt jeden Bereich in seiner Farbe', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');
    bereichAnlegen($netz, '10.10.250.21', '10.10.250.30', 'Drucker');

    $antwort = $this->get("/{$kunde->slug}/ip-plan")->assertStatus(200);

    $plan = collect($antwort->viewData('plans'))->firstWhere('network.id', $netz->id)['plan'];

    // Ein Stueck je Reservierung, nicht ein Sammelstueck: Sonst sagt der Balken
    // "reserviert", aber nicht, von wem - und zwei Bereiche saehen aus wie einer.
    expect($plan['reserviert'])->toHaveCount(2)
        ->and($plan['reserviert'][0]['label'])->toBe('Proxmox-Server')
        ->and($plan['reserviert'][1]['label'])->toBe('Drucker')
        ->and($plan['reserviert'][0]['farbe']['balken'])
        ->not->toBe($plan['reserviert'][1]['farbe']['balken']);
});

test('eine belegte Adresse im Bereich zählt nicht doppelt', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'server_viewAny']));

    $kunde = Customer::factory()->create();
    $netz = netzMitBereich($kunde);
    bereichAnlegen($netz, '10.10.250.10', '10.10.250.20', 'Proxmox-Server');

    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $server = Server::create([
        'customer_id' => $kunde->id, 'site_id' => $standort->id, 'name' => 'PVE-01',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Debian 12'])->id,
    ]);
    $server->ipAddresses()->create([
        'customer_id' => $kunde->id, 'network_id' => $netz->id, 'address' => '10.10.250.12',
    ]);

    $antwort = $this->get("/{$kunde->slug}/ip-plan")->assertStatus(200);
    $plan = collect($antwort->viewData('plans'))->firstWhere('network.id', $netz->id)['plan'];

    // Elf Adressen im Bereich, eine davon belegt - der Balken zeigt zehn als
    // reserviert und eine als belegt. Zaehlte er elf, ginge er ueber 100 Prozent.
    expect($plan['reserviert'][0]['anzahl'])->toBe(10);
});
