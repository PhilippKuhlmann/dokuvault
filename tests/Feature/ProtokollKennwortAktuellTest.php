<?php

use App\Livewire\AdminProtokoll;
use App\Livewire\ProtokollKennwort;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;
use App\Models\Wifi;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Beim ersten Setzen eines Kennworts gibt es keinen Vorgaenger - so entstehen
 * die Eintraege, die ein Agent schreibt. In der Zeile stand deshalb nur die
 * Beschriftung "Kennwort": Sie sagte, dass sich etwas geaendert hat, ohne zu
 * zeigen, was.
 */
function wlanMitAgentkennwort(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi');

    $melden = fn (array $wlan) => test()->withToken($plain)
        ->postJson('/api/agent/unifi', ['wifis' => [$wlan]])->assertOk();

    // Zwei Laeufe, wie es beim Nutzer war: der erste legt das WLAN an (das
    // Script meldete damals keine Passphrase), der zweite setzt sie. Erst
    // dadurch entsteht ein Eintrag "Kennwort geaendert" ohne Vorgaenger - ein
    // einzelner Lauf erzeugt nur "Erstellt".
    $melden(['identifier' => 'w-1', 'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK']);
    $melden(['identifier' => 'w-1', 'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK', 'password' => 'Geheim123!']);

    return [$customer, $site, Wifi::where('agent_identifier', 'w-1')->first()];
}

test('ohne Vorgaenger zeigt die Zeile das Kennwort, das jetzt gilt', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    [, , $wlan] = wlanMitAgentkennwort();

    // Der Agent hat zum ersten Mal gesetzt: Eintrag ja, Vorgaenger nein.
    $eintrag = Activity::where('event', 'password_changed')->latest('id')->first();
    expect($eintrag)->not->toBeNull();
    expect($eintrag->properties['verlauf_ids'])->toBeEmpty();

    Livewire::test(ProtokollKennwort::class, [
        'ids' => [],
        'felder' => ['password'],
        'objektTyp' => Wifi::class,
        'objektId' => $wlan->id,
    ])
        ->assertDontSee('Geheim123!')
        ->call('zeigen')
        ->assertSee('Geheim123!')
        ->assertSee('gilt jetzt');
});

test('mit Vorgaenger bleibt es beim bisherigen Kennwort', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $wlan = Wifi::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network_id' => Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id])->id,
        'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK', 'password' => 'Altes111',
    ]);
    $wlan->update(['password' => 'Neues222']);

    $eintrag = Activity::where('event', 'password_changed')->latest('id')->first();

    Livewire::test(ProtokollKennwort::class, [
        'ids' => $eintrag->properties['verlauf_ids'],
        'felder' => ['password'],
        'objektTyp' => Wifi::class,
        'objektId' => $wlan->id,
    ])
        ->call('zeigen')
        // Gefragt ist, was vorher galt - nicht, was jetzt gilt.
        ->assertSee('Altes111')
        ->assertDontSee('Neues222')
        ->assertSee('galt vorher');
});

test('die Protokollseite reicht Art und Id des Objekts durch', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    [, , $wlan] = wlanMitAgentkennwort();

    // Der Punkt, an dem es leise brechen wuerde: In der Ansicht stehen die
    // Angaben als :objekt-typ und :objekt-id, in der Komponente heissen sie
    // $objektTyp und $objektId. Kommen sie nicht an, faellt die Komponente auf
    // "nichts anzuzeigen" zurueck - und das sieht aus wie vorher.
    $html = Livewire::test(AdminProtokoll::class)->html();

    expect($html)->toContain('objektTyp');
    expect($html)->toContain(str_replace('\\', '\\\\', Wifi::class));
    expect($html)->toContain('&quot;objektId&quot;:'.$wlan->id);
});

test('ein Feld, das kein Geheimnis ist, wird nicht ausgelesen', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    [, , $wlan] = wlanMitAgentkennwort();

    // Die Feldnamen stammen aus dem Protokolleintrag, aber welche Spalte ein
    // Kennwort ist, entscheidet config('custom.secret_columns').
    Livewire::test(ProtokollKennwort::class, [
        'ids' => [],
        'felder' => ['ssid'],
        'objektTyp' => Wifi::class,
        'objektId' => $wlan->id,
    ])
        ->call('zeigen')
        ->assertSee('Nicht mehr aufbewahrt');
});

test('ohne das Recht kommt niemand an den Wert', function () {
    $this->actingAs(userWithPermissions([]));
    [, , $wlan] = wlanMitAgentkennwort();

    Livewire::test(ProtokollKennwort::class, [
        'ids' => [],
        'felder' => ['password'],
        'objektTyp' => Wifi::class,
        'objektId' => $wlan->id,
    ])->call('zeigen')->assertForbidden();
});

test('ein geloeschtes Objekt bricht die Zeile nicht', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    [, , $wlan] = wlanMitAgentkennwort();
    $id = $wlan->id;
    $wlan->forceDelete();

    Livewire::test(ProtokollKennwort::class, [
        'ids' => [],
        'felder' => ['password'],
        'objektTyp' => Wifi::class,
        'objektId' => $id,
    ])->call('zeigen')->assertOk()->assertSee('Nicht mehr aufbewahrt');
});
