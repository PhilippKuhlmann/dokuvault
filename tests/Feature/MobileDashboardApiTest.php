<?php

use App\Models\Certificate;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;

/**
 * Das Kunden-Dashboard in der App - dieselben Zahlen wie im Browser.
 *
 * Geprueft wird vor allem, dass die Rechte halten und dass jede Zeile einen
 * Weg mitbringt, den die App gehen kann (typ + id).
 */
function dashboardAppToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function dashboardAbruf(string $token, Customer $kunde)
{
    return test()->withToken($token)->getJson("/api/mobile/customers/{$kunde->slug}/dashboard");
}

test('das Dashboard nennt Fristen, Standorte, Ansprechpartner und Aenderungen', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id, 'name' => 'Zentrale']);
    $token = dashboardAppToken([
        'licensesoftware_viewAny', 'certificate_viewAny', 'server_viewAny',
        'networkswitch_viewAny', 'site_viewAny', 'contactperson_viewAny',
    ]);

    LicenseSoftware::factory()->create([
        'customer_id' => $kunde->id,
        'name' => 'Office 2021',
        'end_date' => now()->addDays(10)->format('Y-m-d'),
    ]);
    Certificate::factory()->create([
        'customer_id' => $kunde->id,
        'name' => 'wildcard.example.de',
        'expiry_date' => now()->subDays(3)->format('Y-m-d'),
    ]);
    $switch = NetworkSwitch::factory()->create([
        'customer_id' => $kunde->id,
        'site_id' => $standort->id,
        'name' => 'SW-EOL',
        'eol_date' => now()->addDays(30)->format('Y-m-d'),
    ]);
    ContactPerson::factory()->create([
        'customer_id' => $kunde->id,
        'first_name' => 'Hanna',
        'last_name' => 'Meier',
        'phone' => '+49 30 123456',
    ]);

    $antwort = dashboardAbruf($token, $kunde)->assertOk();

    expect($antwort->json('fristen.lizenzen.0.name'))->toBe('Office 2021');
    expect($antwort->json('fristen.lizenzen.0.tage'))->toBe(10);
    // Abgelaufenes kommt negativ heraus - daran faerbt die App rot.
    expect($antwort->json('fristen.zertifikate.0.tage'))->toBeLessThan(0);
    expect($antwort->json('fristen.support_ende.0.name'))->toBe('SW-EOL');
    expect($antwort->json('fristen.support_ende.0.typ'))->toBe('networkswitch');
    expect($antwort->json('fristen.support_ende.0.id'))->toBe($switch->id);
    expect($antwort->json('standorte.0.name'))->toBe('Zentrale');
    expect($antwort->json('ansprechpartner.0.name'))->toContain('Meier');
    expect($antwort->json('ansprechpartner.0.telefon'))->toBe('+49 30 123456');
    // Das eben Angelegte steht im Protokoll.
    expect($antwort->json('aenderungen'))->not->toBeEmpty();
});

test('eine Frist ohne Recht auf die Art steht nicht im Dashboard', function () {
    $kunde = Customer::factory()->create();
    $token = dashboardAppToken(['certificate_viewAny']);

    LicenseSoftware::factory()->create([
        'customer_id' => $kunde->id,
        'name' => 'Office 2021',
        'end_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    expect(dashboardAbruf($token, $kunde)->assertOk()->json('fristen.lizenzen'))->toBe([]);
});

test('das Support-Ende nennt Geraet und Betriebssystem, aber nichts Fernes', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $token = dashboardAppToken(['server_viewAny']);

    $alt = OperatingSystem::factory()->create(['name' => 'Windows Server 2012 R2', 'eol_date' => now()->subYear()]);
    $neu = OperatingSystem::factory()->create(['name' => 'Windows Server 2025', 'eol_date' => now()->addYears(8)]);

    Server::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id,
        'operating_system_id' => $alt->id, 'name' => 'SRV-ALT']);
    Server::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id,
        'operating_system_id' => $neu->id, 'name' => 'SRV-NEU']);

    $namen = collect(dashboardAbruf($token, $kunde)->assertOk()->json('fristen.support_ende'))->pluck('name');

    expect($namen->all())->toBe(['SRV-ALT']);
});

test('ohne Agentenverwaltung gibt es keine Agenten-Karte', function () {
    $kunde = Customer::factory()->create();

    expect(dashboardAbruf(dashboardAppToken(['server_viewAny']), $kunde)->assertOk()->json('agenten'))->toBeNull();
});

test('ein Kundennutzer kommt nicht an das Dashboard eines anderen Kunden', function () {
    $eigener = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $token = dashboardAppToken(['server_viewAny'], $eigener);

    dashboardAbruf($token, $eigener)->assertOk();
    dashboardAbruf($token, $fremder)->assertForbidden();
});

test('ohne Anmeldung kein Dashboard', function () {
    $kunde = Customer::factory()->create();

    $this->getJson("/api/mobile/customers/{$kunde->slug}/dashboard")->assertUnauthorized();
});
