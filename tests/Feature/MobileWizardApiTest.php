<?php

use App\Models\Customer;
use App\Models\DocumentationRun;

/**
 * Der Erstaufnahme-Assistent in der App.
 *
 * Der Ablauf selbst steht in App\Support\Erstaufnahme und wird von der
 * Livewire-Komponente mitbenutzt (dafuer gibt es DocumentationWizardTest).
 * Hier geht es darum, was ueber die Leitung geht und dass die Rechte halten.
 */
function assistentToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

test('der Assistent nennt nur Schritte, die der Nutzer anlegen darf', function () {
    $kunde = Customer::factory()->create();

    $antwort = test()->withToken(assistentToken(['site_create', 'server_create']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/wizard")
        ->assertOk();

    $schluessel = collect($antwort->json('schritte'))->pluck('schluessel');

    expect($schluessel->all())->toBe(['site', 'server']);
    expect($antwort->json('laeuft'))->toBeFalse();
    // Der Typ sagt der App, mit welchem Formular sie den Schritt ausfuellt.
    expect($antwort->json('schritte.0.typ'))->toBe('site');
    expect($antwort->json('schritte.0.frage'))->not->toBeEmpty();
});

test('ohne ein einziges Anlege-Recht ist der Assistent gesperrt', function () {
    $kunde = Customer::factory()->create();

    test()->withToken(assistentToken(['server_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/wizard")
        ->assertForbidden();
});

test('start beginnt einen Durchlauf beim ersten Schritt', function () {
    $kunde = Customer::factory()->create();

    $antwort = test()->withToken(assistentToken(['site_create', 'server_create']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/wizard/start")
        ->assertOk();

    expect($antwort->json('laeuft'))->toBeTrue();
    expect($antwort->json('aktuell'))->toBe('site');
    expect(DocumentationRun::count())->toBe(1);
});

test('ein zweiter Start führt den offenen Durchlauf weiter', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create', 'server_create']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/wizard";

    test()->withToken($token)->postJson("{$pfad}/start")->assertOk();
    test()->withToken($token)->postJson("{$pfad}/next", ['wie' => 'erledigt'])->assertOk();
    $antwort = test()->withToken($token)->postJson("{$pfad}/start")->assertOk();

    expect($antwort->json('aktuell'))->toBe('server');
    expect(DocumentationRun::count())->toBe(1);
});

test('erledigt und übersprungen stehen am Schritt', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create', 'server_create']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/wizard";

    test()->withToken($token)->postJson("{$pfad}/start")->assertOk();
    test()->withToken($token)->postJson("{$pfad}/next", ['wie' => 'erledigt'])->assertOk();
    $antwort = test()->withToken($token)->postJson("{$pfad}/next", ['wie' => 'uebersprungen'])->assertOk();

    $schritte = collect($antwort->json('schritte'))->keyBy('schluessel');

    expect($schritte['site']['erledigt'])->toBeTrue();
    expect($schritte['server']['uebersprungen'])->toBeTrue();
    // Hinter dem letzten Schritt ist der Durchlauf fertig.
    expect($antwort->json('aktuell'))->toBeNull();
    expect($antwort->json('fertig'))->not->toBeNull();
});

test('zurück geht einen Schritt, und springen führt direkt hin', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create', 'server_create', 'computer_create']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/wizard";

    test()->withToken($token)->postJson("{$pfad}/start")->assertOk();
    test()->withToken($token)->postJson("{$pfad}/next", ['wie' => 'erledigt'])->assertOk();

    $zurueck = test()->withToken($token)->postJson("{$pfad}/goto")->assertOk();
    expect($zurueck->json('aktuell'))->toBe('site');

    $hin = test()->withToken($token)->postJson("{$pfad}/goto", ['schritt' => 'computer'])->assertOk();
    expect($hin->json('aktuell'))->toBe('computer');
});

/**
 * Ein Schritt, den der Nutzer nicht anlegen darf, ist fuer ihn kein Schritt -
 * der Durchlauf darf dort nicht landen.
 */
test('ein gesperrter Schritt lässt sich nicht anspringen', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/wizard";

    test()->withToken($token)->postJson("{$pfad}/start")->assertOk();

    test()->withToken($token)->postJson("{$pfad}/goto", ['schritt' => 'server'])->assertNotFound();
    test()->withToken($token)->postJson("{$pfad}/goto", ['schritt' => 'quatsch'])->assertNotFound();

    expect(DocumentationRun::first()->current_step)->toBe('site');
});

test('eine ganze Gruppe lässt sich überspringen', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create', 'contactperson_create', 'server_create']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/wizard";

    test()->withToken($token)->postJson("{$pfad}/start")->assertOk();
    $antwort = test()->withToken($token)->postJson("{$pfad}/next", ['wie' => 'gruppe'])->assertOk();

    // Standort und Ansprechpartner stehen beide in "Grunddaten".
    $schritte = collect($antwort->json('schritte'))->keyBy('schluessel');

    expect($schritte['site']['uebersprungen'])->toBeTrue();
    expect($schritte['contactperson']['uebersprungen'])->toBeTrue();
    expect($antwort->json('aktuell'))->toBe('server');
});

test('abschließen beendet den Durchlauf', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create', 'server_create']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/wizard";

    test()->withToken($token)->postJson("{$pfad}/start")->assertOk();
    $antwort = test()->withToken($token)->postJson("{$pfad}/complete")->assertOk();

    expect($antwort->json('laeuft'))->toBeFalse();
    expect(DocumentationRun::first()->completed_at)->not->toBeNull();
});

test('ein unbekanntes Weiter wird abgewiesen', function () {
    $kunde = Customer::factory()->create();
    $token = assistentToken(['site_create']);

    test()->withToken($token)
        ->postJson("/api/mobile/customers/{$kunde->slug}/wizard/next", ['wie' => 'irgendwie'])
        ->assertStatus(422);
});
