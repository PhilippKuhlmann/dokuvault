<?php

use App\Livewire\GeheimFeld;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\SecurepointUMA;
use App\Models\Site;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Firewall mit gesetzten Geheimnissen - das Testobjekt für GeheimFeld.
 */
function eineGeheimFirewall(array $werte = []): Firewall
{
    $customer = Customer::factory()->create();

    return Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        ...$werte,
    ]);
}

test('mit dem Recht der Liste holt das Feld den Wert auf Klick', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny']));
    $fw = eineGeheimFirewall(['cloud_backup_password' => 'Wolke-Geheim-2026']);

    Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'cloud_backup_password'])
        ->assertDontSee('Wolke-Geheim-2026')
        ->call('zeigen')
        ->assertSet('offen', true)
        ->assertSee('Wolke-Geheim-2026');
});

test('ohne das Recht bleibt das Geheimnis verborgen', function () {
    $this->actingAs(userWithPermissions([])); // kein firewall_viewAny
    $fw = eineGeheimFirewall(['cloud_backup_password' => 'Verboten-2026']);

    Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'cloud_backup_password'])
        ->call('zeigen')
        ->assertForbidden();
});

test('ein Feld ausserhalb der Geheimnis-Spalten wird nicht herausgegeben', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny']));
    $fw = eineGeheimFirewall(['name' => 'FW-Sichtbar']);

    // 'name' steht nicht in secret_columns - zeigen() bricht ab, nichts wird geladen.
    Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'name'])
        ->call('zeigen')
        ->assertSet('offen', false)
        ->assertSet('wert', null);
});

test('die Einsicht steht im Protokoll - ohne den Wert', function () {
    $nutzer = userWithPermissions(['firewall_viewAny']);
    $this->actingAs($nutzer);
    $fw = eineGeheimFirewall(['usc_pin' => 'Nie-Ins-Protokoll-2026']);

    Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'usc_pin'])->call('zeigen');

    $eintrag = Activity::where('event', 'kennwort_angesehen')->latest('id')->first();

    expect($eintrag)->not->toBeNull();
    expect($eintrag->causer_id)->toBe($nutzer->id);
    expect($eintrag->subject_id)->toBe($fw->id);
    expect(json_encode($eintrag->toArray(), JSON_UNESCAPED_UNICODE))
        ->not->toContain('Nie-Ins-Protokoll-2026');
});

test('die Bremse stoppt das reihenweise Abgreifen', function () {
    config(['custom.kennwort.ansehen_je_minute' => 1]);
    $this->actingAs(userWithPermissions(['firewall_viewAny']));
    $fw = eineGeheimFirewall(['cloud_backup_password' => 'Nur-Einmal-2026']);

    $test = Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'cloud_backup_password']);

    $test->call('zeigen')->assertSet('offen', true);
    $test->call('verbergen')
        ->call('zeigen')
        ->assertSet('offen', false)
        ->assertHasErrors('kennwort');
});

test('kopieren gibt den Wert an die Zwischenablage, ohne aufzudecken', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny']));
    $fw = eineGeheimFirewall(['cloud_backup_password' => 'Kopiert-Ohne-Anzeige-2026']);

    Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'cloud_backup_password'])
        ->call('kopieren')
        ->assertSet('offen', false)       // bleibt verdeckt
        ->assertSet('wert', null)          // Wert nicht im DOM-Zustand
        ->assertDispatched('kennwort-bereit', wert: 'Kopiert-Ohne-Anzeige-2026');
});

test('kopieren ohne das Recht gibt nichts heraus', function () {
    $this->actingAs(userWithPermissions([]));
    $fw = eineGeheimFirewall(['cloud_backup_password' => 'Verboten-2026']);

    Livewire::test(GeheimFeld::class, ['modell' => Firewall::class, 'id' => $fw->id, 'feld' => 'cloud_backup_password'])
        ->call('kopieren')
        ->assertForbidden();
});

test('die Firewall-Liste trägt den Klartext nicht mehr im HTML', function () {
    $nutzer = userWithPermissions(['firewall_viewAny']);
    $customer = Customer::factory()->create();
    Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'name' => 'FW-Listentest',
        'usc_pin' => 'PIN-Nicht-Im-Dom-2026',
        'cloud_backup_password' => 'Wolke-Nicht-Im-Dom-2026',
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/firewall")
        ->assertOk()
        ->assertSee('FW-Listentest')
        ->assertDontSee('PIN-Nicht-Im-Dom-2026')
        ->assertDontSee('Wolke-Nicht-Im-Dom-2026');
});

test('die SecurepointUMA-Liste trägt Gerätepasswort und Code nicht mehr im HTML', function () {
    $nutzer = userWithPermissions(['securepointuma_viewAny']);
    $customer = Customer::factory()->create();
    SecurepointUMA::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'UMA-Listentest',
        'password' => 'Geraet-Nicht-Im-Dom-2026',
        'encryptionkey' => 'Code-Nicht-Im-Dom-2026',
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/securepointuma")
        ->assertOk()
        ->assertSee('UMA-Listentest')
        ->assertDontSee('Geraet-Nicht-Im-Dom-2026')
        ->assertDontSee('Code-Nicht-Im-Dom-2026');
});
