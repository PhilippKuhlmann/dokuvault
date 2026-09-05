<?php

use App\Models\Accesspoint;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Site;
use App\Models\Wifi;

function unifiPayload(): array
{
    return [
        'site' => 'default',
        'switches' => [[
            'identifier' => 'aa:bb:cc:00:00:01',
            'name' => 'SW-EG-01',
            'manufacturer' => 'Ubiquiti',
            'model' => 'US24P250',
            'serial' => 'F09FC2000001',
            'ip' => '10.0.0.2',
        ]],
        'accesspoints' => [[
            'identifier' => 'aa:bb:cc:00:00:02',
            'name' => 'AP-Buero',
            'manufacturer' => 'Ubiquiti',
            'model' => 'U6PRO',
            'serial' => 'F09FC2000002',
            'ip' => '10.0.0.3',
        ]],
        'wifis' => [
            ['identifier' => 'wlan-id-1', 'ssid' => 'Firma', 'encryption' => 'WPA2-PSK', 'password' => 'SehrGeheim123!'],
            ['identifier' => 'wlan-id-2', 'ssid' => 'Gast', 'encryption' => 'Offen'],
        ],
    ];
}

test('UniFi-Agent legt Switches, Accesspoints und WLANs beim Kunden des Tokens an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi');

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'switches_documented' => 1,
            'accesspoints_documented' => 1,
            'wifis_documented' => 2,
        ]);

    $switch = NetworkSwitch::where('customer_id', $customer->id)->first();
    expect($switch->agent_identifier)->toBe('aa:bb:cc:00:00:01');
    expect($switch->name)->toBe('SW-EG-01');
    expect($switch->model)->toBe('US24P250');
    expect($switch->site_id)->toBe($site->id);
    expect($switch->ipAddresses()->first()->address)->toBe('10.0.0.2');

    $ap = Accesspoint::where('customer_id', $customer->id)->first();
    expect($ap->name)->toBe('AP-Buero');
    expect($ap->ipAddresses()->first()->address)->toBe('10.0.0.3');

    $wlans = Wifi::where('customer_id', $customer->id)->get();
    expect($wlans)->toHaveCount(2);
    expect($wlans->firstWhere('agent_identifier', 'wlan-id-1')->ssid)->toBe('Firma');
    expect($wlans->firstWhere('agent_identifier', 'wlan-id-1')->encryption)->toBe('WPA2-PSK');
});

test('die gemeldete WLAN-Passphrase wird uebernommen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    $wlan = Wifi::where('agent_identifier', 'wlan-id-1')->first();
    expect($wlan->password)->toBe('SehrGeheim123!');
    // Verschluesselt in der Spalte, nicht im Klartext.
    expect($wlan->getRawOriginal('password'))->not->toBe('SehrGeheim123!');
});

test('ein WLAN ohne Passphrase bekommt keine', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    // 'Gast' ist offen, ein Enterprise-WLAN haette ebenfalls keine. Der
    // Setter verschluesselt bedingungslos - ein Leerwert waere Chiffretext
    // aus nichts.
    $gast = Wifi::where('agent_identifier', 'wlan-id-2')->first();
    expect($gast->getRawOriginal('password'))->toBeNull();
});

test('das VLAN bleibt leer und ein von Hand gesetztes ueberlebt', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);
    $vlan = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    $wlan = Wifi::where('agent_identifier', 'wlan-id-1')->first();
    // Welches der gepflegten VLANs hinter der SSID steht, weiss der
    // Controller nicht - anders als die Passphrase.
    expect($wlan->network_id)->toBeNull();

    $wlan->update(['network_id' => $vlan->id]);
    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    expect($wlan->fresh()->network_id)->toBe($vlan->id);
});

test('eine am Controller geaenderte Passphrase zieht nach', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    $geaendert = unifiPayload();
    $geaendert['wifis'][0]['password'] = 'NeuesKennwort456!';
    $this->withToken($plain)->postJson('/api/agent/unifi', $geaendert)->assertOk();

    // Der Controller ist die Quelle: wer dort die Passphrase aendert, soll sie
    // in der Doku wiederfinden, nicht die alte.
    expect(Wifi::where('agent_identifier', 'wlan-id-1')->first()->password)->toBe('NeuesKennwort456!');
});

test('meldet der Agent keine Passphrase, bleibt die gepflegte stehen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    $wlan = Wifi::where('agent_identifier', 'wlan-id-1')->first();
    $wlan->update(['password' => 'vonHand']);

    // So sieht ein Lauf mit --ohne-kennwoerter aus.
    $ohne = unifiPayload();
    unset($ohne['wifis'][0]['password']);
    $this->withToken($plain)->postJson('/api/agent/unifi', $ohne)->assertOk();

    expect($wlan->fresh()->password)->toBe('vonHand');
});

test('ein unveraendertes Kennwort schreibt die Zeile nicht neu', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    $wlan = Wifi::where('agent_identifier', 'wlan-id-1')->first();
    $chiffre = $wlan->getRawOriginal('password');

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    // Crypt::encryptString erzeugt jedes Mal einen anderen Chiffretext. Ohne
    // den Vergleich der Klartexte waere die Zeile bei jedem Lauf "geaendert" -
    // und stuende jedes Mal als Kennwortaenderung im Protokoll.
    expect($wlan->fresh()->getRawOriginal('password'))->toBe($chiffre);
});

test('erneuter Lauf aktualisiert, statt zu verdoppeln', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    $geaendert = unifiPayload();
    $geaendert['switches'][0]['name'] = 'SW-EG-01-NEU';
    $this->withToken($plain)->postJson('/api/agent/unifi', $geaendert)->assertOk();

    expect(NetworkSwitch::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Accesspoint::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Wifi::where('customer_id', $customer->id)->count())->toBe(2);
    expect(NetworkSwitch::first()->name)->toBe('SW-EG-01-NEU');
});

test('ein Token trifft kein Geraet eines anderen Kunden', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();
    $standortB = Site::factory()->create(['customer_id' => $kundeB->id]);

    $fremder = NetworkSwitch::create([
        'customer_id' => $kundeB->id,
        'site_id' => $standortB->id,
        'name' => 'Fremder Switch',
        'agent_identifier' => 'aa:bb:cc:00:00:01',
    ]);

    $standortA = Site::factory()->create(['customer_id' => $kundeA->id]);
    [$token, $plain] = AgentToken::generateFor($kundeA, $standortA);

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiPayload())->assertOk();

    expect($fremder->fresh()->name)->toBe('Fremder Switch')
        ->and($fremder->fresh()->customer_id)->toBe($kundeB->id);
    expect(NetworkSwitch::where('customer_id', $kundeA->id)->count())->toBe(1);
});

test('ohne Pflichtfelder: 422', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/unifi', ['switches' => [['name' => 'ohne Kennung']]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('switches.0.identifier');

    $this->withToken($plain)->postJson('/api/agent/unifi', ['wifis' => [['identifier' => 'x']]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('wifis.0.ssid');
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/unifi', [])->assertUnauthorized();
    $this->withToken('doc_falsch')->postJson('/api/agent/unifi', unifiPayload())->assertUnauthorized();
});
