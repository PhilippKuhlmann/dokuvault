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
 * Was im Protokoll steht, wenn es kein bisheriges Kennwort gibt.
 *
 * Gefragt wird das Protokoll nach dem, was VORHER galt. Beim ersten Setzen gab
 * es nichts - dann bleibt es bei der Beschriftung des Feldes. Das aktuelle
 * Kennwort steht dort bewusst nicht: Es ist keine Antwort auf die Frage, und
 * es gehoert an die Stelle, wo man das Objekt pflegt.
 */
test('ohne Vorgaenger nennt die Zeile nur das Feld, nicht den Wert', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi');

    $melden = fn (array $wlan) => $this->withToken($plain)
        ->postJson('/api/agent/unifi', ['wifis' => [$wlan]])->assertOk();

    // Zwei Laeufe: der erste legt das WLAN an, der zweite setzt die
    // Passphrase. Erst dadurch entsteht ein Eintrag "Kennwort geaendert" ohne
    // Vorgaenger - ein einzelner Lauf erzeugt nur "Erstellt".
    $melden(['identifier' => 'w-1', 'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK']);
    $melden(['identifier' => 'w-1', 'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK', 'password' => 'Geheim123!']);

    $eintrag = Activity::where('event', 'password_changed')->latest('id')->first();
    expect($eintrag->properties['verlauf_ids'])->toBeEmpty();

    Livewire::test(AdminProtokoll::class)
        ->assertSee('Kennwort')
        ->assertDontSee('Geheim123!');
});

test('mit Vorgaenger laesst sich das bisherige Kennwort aufdecken', function () {
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
    ])
        ->assertDontSee('Altes111')
        ->call('zeigen')
        // Gefragt ist, was vorher galt - nicht, was jetzt gilt.
        ->assertSee('Altes111')
        ->assertDontSee('Neues222');
});

test('ohne das Recht kommt niemand an ein bisheriges Kennwort', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $wlan = Wifi::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'network_id' => Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id])->id,
        'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK', 'password' => 'Altes111',
    ]);
    $wlan->update(['password' => 'Neues222']);

    $eintrag = Activity::where('event', 'password_changed')->latest('id')->first();

    // Das Recht wird hier noch einmal geprueft: Der Aufruf kommt aus dem
    // Browser und darf sich nicht auf die Route verlassen.
    Livewire::test(ProtokollKennwort::class, [
        'ids' => $eintrag->properties['verlauf_ids'],
        'felder' => ['password'],
    ])->call('zeigen')->assertForbidden();
});
