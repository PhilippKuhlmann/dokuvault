<?php

use App\Livewire\KennwortFeld;
use App\Models\CredentialLink;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\OperatingSystem;
use App\Models\Site;
use App\Models\VM;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Baut eine Verknüpfung Login → Gerät und gibt sie mit dem Klartext zurück.
 */
function eineVerknuepfung(string $kennwort = 'Geheim!2026'): CredentialLink
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $vm = VM::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'VM-WEB01', 'operating_system_id' => $os->id,
    ]);
    $login = LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'Linux root',
        'username' => 'root', 'password' => $kennwort,
    ]);

    $link = new CredentialLink(['note' => 'SSH root', 'customer_id' => $customer->id]);
    $link->login_general_id = $login->id;
    $link->credentialable()->associate($vm);
    $link->save();

    return $link;
}

test('vor dem Klick steht der Wert nirgends im Feld', function () {
    $this->actingAs(userWithPermissions(['logingeneral_viewAny']));
    $link = eineVerknuepfung('Sichtbar-Waere-Falsch-2026');

    Livewire::test(KennwortFeld::class, ['linkId' => $link->id])
        ->assertSet('wert', null)
        ->assertSet('offen', false)
        ->assertDontSee('Sichtbar-Waere-Falsch-2026');
});

test('auf Klick holt das Feld den Wert - mit Recht', function () {
    $this->actingAs(userWithPermissions(['logingeneral_viewAny']));
    $link = eineVerknuepfung('Auf-Klick-2026');

    Livewire::test(KennwortFeld::class, ['linkId' => $link->id])
        ->call('zeigen')
        ->assertSet('offen', true)
        ->assertSee('Auf-Klick-2026');
});

test('ohne Recht bleibt das Kennwort verborgen', function () {
    $this->actingAs(userWithPermissions([])); // kein logingeneral_viewAny
    $link = eineVerknuepfung('Verboten-2026');

    Livewire::test(KennwortFeld::class, ['linkId' => $link->id])
        ->call('zeigen')
        ->assertForbidden();
});

test('das Verbergen räumt den Wert wieder weg', function () {
    $this->actingAs(userWithPermissions(['logingeneral_viewAny']));
    $link = eineVerknuepfung('Kommt-Und-Geht-2026');

    Livewire::test(KennwortFeld::class, ['linkId' => $link->id])
        ->call('zeigen')->assertSee('Kommt-Und-Geht-2026')
        ->call('verbergen')
        ->assertSet('wert', null)
        ->assertDontSee('Kommt-Und-Geht-2026');
});

test('die Einsicht steht im Protokoll - ohne den Wert', function () {
    $nutzer = userWithPermissions(['logingeneral_viewAny']);
    $this->actingAs($nutzer);
    $link = eineVerknuepfung('Nie-Ins-Protokoll-2026');

    Livewire::test(KennwortFeld::class, ['linkId' => $link->id])->call('zeigen');

    $eintrag = Activity::where('event', 'kennwort_angesehen')->latest('id')->first();

    expect($eintrag)->not->toBeNull();
    expect($eintrag->causer_id)->toBe($nutzer->id);
    expect($eintrag->subject_id)->toBe($link->login->id);
    // Der ganze Eintrag als Text: So faellt auch auf, wenn der Wert an eine
    // Stelle rutscht, an die hier niemand denkt.
    expect(json_encode($eintrag->toArray(), JSON_UNESCAPED_UNICODE))
        ->not->toContain('Nie-Ins-Protokoll-2026');
});

test('die Bremse stoppt das reihenweise Abgreifen', function () {
    config(['custom.kennwort.ansehen_je_minute' => 1]);
    $this->actingAs(userWithPermissions(['logingeneral_viewAny']));
    $link = eineVerknuepfung('Nur-Einmal-2026');

    $test = Livewire::test(KennwortFeld::class, ['linkId' => $link->id]);

    // Erster Abruf zählt und deckt auf.
    $test->call('zeigen')->assertSet('offen', true);

    // Zweiter Abruf läuft ins Limit: kein Wert, ein Hinweis.
    $test->call('verbergen')
        ->call('zeigen')
        ->assertSet('offen', false)
        ->assertHasErrors('kennwort');
});
