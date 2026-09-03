<?php

use App\Models\Customer;
use App\Models\InternetConnection;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Einwahldaten am Internetanschluss. Vorher gab es dafuer keine Stelle - wer
 * sie festhalten wollte, schrieb sie in die Notizen.
 */
function pppoeUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('das Formular bietet Felder fuer die Einwahldaten', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create', 'internetconnection_update']));
    [$customer, $site] = pppoeUmgebung();

    $anschluss = InternetConnection::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'provider' => 'Telekom',
    ]);

    foreach ([null, $anschluss->id] as $id) {
        $inhalt = modalHtml('internetconnection', $customer, $id);

        // Live gebunden seit der laufenden Pruefung - ein rot markiertes Feld
        // soll sein Rot verlieren, sobald der Wert stimmt.
        expect($inhalt)->toContain('wire:model.live.debounce.400ms="form.pppoe_user"');
        expect($inhalt)->toContain('wire:model.live.debounce.400ms="form.pppoe_password"');
    }
});

test('die Einwahldaten werden gespeichert, das Passwort verschluesselt', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = pppoeUmgebung();

    imModal('internetconnection', $customer, [
        'site_id' => $site->id, 'provider' => 'Telekom',
        'pppoe_user' => 'anschluss12345@t-online.de',
        'pppoe_password' => 'geheim123',
    ])->assertHasNoErrors();

    $anschluss = InternetConnection::firstOrFail();
    expect($anschluss->pppoe_user)->toBe('anschluss12345@t-online.de');
    expect($anschluss->pppoe_password)->toBe('geheim123');

    // In der Spalte darf das Kennwort nicht im Klartext stehen.
    $roh = DB::table('internet_connections')->where('id', $anschluss->id)->value('pppoe_password');
    expect($roh)->not->toBe('geheim123');
    expect($roh)->not->toContain('geheim');
});

test('ohne Einwahldaten bleibt das Passwort leer statt verschluesselt leer', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = pppoeUmgebung();

    imModal('internetconnection', $customer, [
        'site_id' => $site->id, 'provider' => 'Vodafone',
    ])->assertHasNoErrors();

    // Ohne die filled()-Pruefung im Setter stuende hier der Chiffretext eines
    // Leerstrings - und die Liste zeigte eine Einwahl-Karte ohne Inhalt.
    expect(DB::table('internet_connections')->value('pppoe_password'))->toBeNull();
});

test('die Liste zeigt die Einwahldaten nur, wenn sie gepflegt sind', function () {
    $this->actingAs(userWithPermissions(['internetconnection_viewAny']));
    [$customer, $site] = pppoeUmgebung();

    InternetConnection::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'provider' => 'Ohne Einwahl',
    ]);

    $this->get("/{$customer->slug}/internetconnection")->assertOk()->assertDontSee('Einwahl (PPPoE)');

    InternetConnection::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'provider' => 'Mit Einwahl',
        'pppoe_user' => 'kunde@provider.de',
    ]);

    $this->get("/{$customer->slug}/internetconnection")->assertOk()
        ->assertSee('Einwahl (PPPoE)')
        ->assertSee('kunde@provider.de');
});
