<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Network;
use App\Models\PasswordHistory;
use App\Models\Site;
use App\Models\Wifi;
use Spatie\Activitylog\Models\Activity;

/**
 * Wozu ein aufgehobenes Kennwort gehoerte.
 *
 * Der Verlauf schreibt den Namen mit, statt ihn nachzuladen - ein Eintrag soll
 * lesbar bleiben, wenn das Geraet laengst weg ist. Genommen wurde dafuer
 * name/username; ein WLAN heisst aber 'ssid'. Fuer WLANs, Anschluesse und
 * Adressen stand dort deshalb nichts.
 */
test('der Kennwortverlauf eines WLANs nennt die SSID', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $wlan = Wifi::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'network_id' => Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id])->id,
        'ssid' => 'Werkstatt',
        'encryption' => 'WPA2-PSK',
        'password' => 'Erstes123',
    ]);

    $wlan->update(['password' => 'Zweites456']);

    $verlauf = PasswordHistory::where('subject_type', Wifi::class)->latest('id')->first();

    expect($verlauf)->not->toBeNull();
    expect($verlauf->value)->toBe('Erstes123');
    expect($verlauf->subject_name)->toBe('Werkstatt');
});

test('das Protokoll verweist auf den Verlauf, sobald es ein bisheriges Kennwort gibt', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'UniFi');

    $meldung = fn (string $kennwort) => [
        'wifis' => [['identifier' => 'w-1', 'ssid' => 'Werkstatt', 'encryption' => 'WPA2-PSK', 'password' => $kennwort]],
    ];

    // Erster Lauf: Der Agent setzt die Passphrase zum ersten Mal. Es gibt kein
    // bisheriges Kennwort - im Protokoll steht deshalb nur, welches Feld
    // gemeint war, und nichts zum Aufdecken. Das ist richtig so.
    $this->withToken($plain)->postJson('/api/agent/unifi', $meldung('Erstes123'))->assertOk();
    expect(Activity::where('event', 'password_changed')->count())->toBe(0);

    // Zweiter Lauf mit anderer Passphrase: Jetzt gibt es eine Vorgaengerin.
    $this->withToken($plain)->postJson('/api/agent/unifi', $meldung('Zweites456'))->assertOk();

    $eintrag = Activity::where('event', 'password_changed')->latest('id')->first();
    expect($eintrag->properties['objekt'])->toBe('Werkstatt');
    expect($eintrag->properties['verlauf_ids'])->toHaveCount(1);

    $verlauf = PasswordHistory::find($eintrag->properties['verlauf_ids'][0]);
    expect($verlauf->value)->toBe('Erstes123');
    expect($verlauf->subject_name)->toBe('Werkstatt');
});
