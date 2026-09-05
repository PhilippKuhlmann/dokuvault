<?php

use App\Livewire\AdminProtokoll;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use App\Models\Wifi;
use App\Support\Protokoll;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Wer im Protokoll steht, wenn ein Agent geschrieben hat.
 *
 * Vorher stand dort "System": Ein Agent hat keinen angemeldeten Benutzer. Wer
 * nachsah, wer die WLANs angelegt hat, fand niemanden.
 */
function unifiMeldung(): array
{
    return [
        'wifis' => [
            ['identifier' => 'wlan-prot-1', 'ssid' => 'Hallenfunk', 'encryption' => 'WPA2-PSK', 'password' => 'Geheim123!'],
        ],
    ];
}

test('ein vom Agenten angelegtes Objekt nennt den Token als Verursacher', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi Werkstatt');

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiMeldung())->assertOk();

    $wlan = Wifi::where('agent_identifier', 'wlan-prot-1')->first();
    $eintrag = Activity::where('subject_type', Wifi::class)->where('subject_id', $wlan->id)
        ->where('event', 'created')->first();

    expect($eintrag)->not->toBeNull();
    expect($eintrag->causer_type)->toBe(AgentToken::class);
    expect($eintrag->causer_id)->toBe($token->id);
    expect(Protokoll::verursacher($eintrag->causer))->toBe('Agent · UniFi Werkstatt');
});

test('auch eine Kennwortaenderung durch den Agenten hat einen Verursacher', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi Werkstatt');

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiMeldung())->assertOk();

    $geaendert = unifiMeldung();
    $geaendert['wifis'][0]['password'] = 'NeuesKennwort456!';
    $this->withToken($plain)->postJson('/api/agent/unifi', $geaendert)->assertOk();

    // Der manuelle Eintrag in TracksChanges nahm auth()->user() - fuer einen
    // Agenten null.
    $eintrag = Activity::where('event', 'password_changed')->latest('id')->first();
    expect($eintrag->causer_type)->toBe(AgentToken::class);
    expect($eintrag->causer_id)->toBe($token->id);
});

test('ohne Verursacher bleibt es "System"', function () {
    // Seeder, Konsolenbefehle, geplante Aufgaben: dort gibt es wirklich
    // niemanden, und etwas zu erfinden waere schlimmer als "System".
    expect(Protokoll::verursacher(null))->toBe('System');
});

test('ein Token ohne Bezeichnung bleibt erkennbar', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token] = AgentToken::generateFor($customer, $site);

    expect(Protokoll::verursacher($token))->toBe('Agent · Token #'.$token->id);
});

test('der Filter verwechselt einen Token nicht mit dem Benutzer gleicher Id', function () {
    $this->actingAs($admin = userWithPermissions(['admin_activity']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    // Genau die Falle: Der Agent-Token bekommt dieselbe Id wie der Benutzer.
    // Der Klartext bleibt gueltig, gesucht wird ueber den Hash.
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi Werkstatt');
    $token->forceFill(['id' => $admin->id])->save();

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiMeldung())->assertOk();
    expect(Activity::where('causer_type', AgentToken::class)->value('causer_id'))->toBe($admin->id);

    Livewire::test(AdminProtokoll::class)
        ->set('benutzer', 'user:'.$admin->id)
        // Der Agent hat das WLAN angelegt, nicht der Benutzer - unter dessen
        // Filter darf es nicht auftauchen.
        ->assertDontSee('Hallenfunk')
        ->set('benutzer', 'agent:'.$admin->id)
        ->assertSee('Hallenfunk');
});

test('die Auswahlliste fuehrt Agenten getrennt von Benutzern', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi Werkstatt');

    $this->withToken($plain)->postJson('/api/agent/unifi', unifiMeldung())->assertOk();

    Livewire::test(AdminProtokoll::class)
        ->assertSee('Agenten')
        ->assertSee('UniFi Werkstatt');
});
