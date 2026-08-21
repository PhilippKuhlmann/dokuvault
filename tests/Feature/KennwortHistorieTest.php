<?php

use App\Livewire\AdminPapierkorb;
use App\Livewire\AdminProtokollHistorie;
use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\PasswordHistory;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function firewallMitKennwort(string $kennwort = 'Alt!2026'): Firewall
{
    $customer = Customer::factory()->create();

    return Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        'password' => $kennwort,
    ]);
}

test('das bisherige Kennwort bleibt nachschlagbar', function () {
    $nutzer = userWithPermissions(['firewall_update']);
    $this->actingAs($nutzer);

    $firewall = firewallMitKennwort('Richtig!2026');
    $firewall->update(['password' => 'Versehen!2026']);

    $eintrag = $firewall->kennwortVerlauf()->sole();

    expect($eintrag->field)->toBe('password');
    expect($eintrag->value)->toBe('Richtig!2026');
    expect($eintrag->user_id)->toBe($nutzer->id);
    expect($eintrag->customer_id)->toBe($firewall->customer_id);
});

test('der alte Wert liegt verschluesselt in der Tabelle', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = firewallMitKennwort('Klartext!2026');
    $firewall->update(['password' => 'Neu!2026']);

    $roh = DB::table('password_histories')->value('value');

    expect($roh)->not->toBe('Klartext!2026');
    expect(Crypt::decryptString($roh))->toBe('Klartext!2026');
});

test('beim ersten Setzen gibt es nichts aufzuheben', function () {
    $this->actingAs(userWithPermissions(['firewall_create', 'firewall_update']));

    $firewall = firewallMitKennwort('');
    $firewall->update(['password' => 'Erstes!2026']);

    expect(PasswordHistory::count())->toBe(0);
});

test('das gleiche Kennwort noch einmal legt keinen Eintrag an', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = firewallMitKennwort('Gleich!2026');
    $firewall->update(['password' => 'Gleich!2026', 'name' => 'FW-Umbenannt']);

    expect(PasswordHistory::count())->toBe(0);
});

test('das Modal zeigt den Verlauf erst auf Klick', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny', 'firewall_update']));

    $firewall = firewallMitKennwort('Vorher!2026');
    $firewall->update(['password' => 'Nachher!2026']);

    $test = Livewire::test(ObjektFormular::class, ['typ' => 'firewall', 'customer' => $firewall->customer])
        ->call('bearbeiten', 'firewall', $firewall->id);

    // Zugeklappt darf der alte Wert nirgends im Quelltext stehen. Geprueft
    // wird auf die Aktion, nicht auf den Wortlaut: Die Testumgebung laeuft auf
    // Englisch, der Knopftext ist uebersetzt.
    $test->assertDontSee('Vorher!2026')
        ->assertSee('verlaufZeigen');

    $test->call('verlaufZeigen', 'password')
        ->assertSee('Vorher!2026');

    $test->call('verlaufVerbergen', 'password')
        ->assertDontSee('Vorher!2026');
});

test('ohne Bearbeitungsrecht kein Verlauf', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny']));

    $firewall = firewallMitKennwort('Geheim!2026');
    $firewall->updateQuietly(['password' => 'Neu!2026']);

    Livewire::test(ObjektFormular::class, ['typ' => 'firewall', 'customer' => $firewall->customer])
        ->call('verlaufZeigen', 'password')
        ->assertForbidden();
});

test('nur echte Kennwortspalten lassen sich abfragen', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = firewallMitKennwort();

    // Der Feldname kommt aus dem Browser. Ohne die Whitelist liesse sich damit
    // jede Spalte als "Kennwort" ausgeben.
    $test = Livewire::test(ObjektFormular::class, ['typ' => 'firewall', 'customer' => $firewall->customer])
        ->call('bearbeiten', 'firewall', $firewall->id)
        ->call('verlaufZeigen', 'name');

    expect($test->get('gezeigterVerlauf'))->toBe([]);
});

test('endgueltiges Loeschen nimmt die alten Kennwoerter mit', function () {
    $this->actingAs(userWithPermissions(['firewall_update', 'see_hidden']));

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Neu!2026']);
    expect(PasswordHistory::count())->toBe(1);

    $firewall->delete();
    Livewire::test(AdminPapierkorb::class)
        ->call('loeschen', 'firewall', $firewall->id);

    // Sonst blieben verschluesselte Kennwoerter ohne Objekt liegen - und ohne
    // Frist, die sie je erreichen wuerden.
    expect(PasswordHistory::count())->toBe(0);
});

test('der Geraetename steht im Eintrag, nicht in einer Nachfrage', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Neu!2026']);

    // Ein Eintrag soll lesbar bleiben, wenn das Geraet laengst weg ist - und
    // ein Verweis auf eine entfernte Klasse braeche beim Aufloesen die Seite.
    expect(PasswordHistory::sole()->subject_name)->toBe($firewall->name);
});

test('die Frist gilt fuer Protokoll und Kennwoerter zusammen', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));
    Setting::setzen(Setting::PROTOKOLL_TAGE, 90);

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Neu!2026']);

    $alt = PasswordHistory::sole();
    DB::table('password_histories')->where('id', $alt->id)->update(['created_at' => now()->subDays(100)]);
    DB::table('activity_log')->update(['created_at' => now()->subDays(100)]);

    $this->artisan('protokoll:aufraeumen')->assertSuccessful();

    // Bliebe die Historie stehen, waeren die alten Werte laenger da als der
    // Eintrag, der auf sie verweist.
    expect(PasswordHistory::count())->toBe(0);
    expect(Activity::count())->toBe(0);
});

test('ohne Frist bleibt das Protokoll unangetastet', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));
    Setting::setzen(Setting::PROTOKOLL_TAGE, 0);

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Neu!2026']);
    DB::table('activity_log')->update(['created_at' => now()->subYears(5)]);
    DB::table('password_histories')->update(['created_at' => now()->subYears(5)]);

    // Ein Protokoll, das sich ungefragt selbst leert, waere keines mehr.
    $this->artisan('protokoll:aufraeumen')->assertSuccessful();

    expect(PasswordHistory::count())->toBe(1);
    expect(Activity::count())->toBeGreaterThan(0);
});

test('die Frist laesst sich einstellen', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));

    Livewire::test(AdminProtokollHistorie::class)
        ->assertSet('tage', 0)
        ->set('tage', 365)
        ->call('speichern');

    expect(Setting::protokollTage())->toBe(365);
});

test('eine unsinnige Frist wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    Setting::setzen(Setting::PROTOKOLL_TAGE, 90);

    Livewire::test(AdminProtokollHistorie::class)
        ->set('tage', -5)
        ->call('speichern')
        ->assertHasErrors('tage');

    expect(Setting::protokollTage())->toBe(90);
});

test('die Einstellseite zeigt keine Kennwoerter', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'firewall_update']));

    $firewall = firewallMitKennwort('Streng-Geheim-2026');
    $firewall->update(['password' => 'Neu!2026']);

    // Hier wird eine Frist eingestellt, nicht nachgeschlagen - die Werte stehen
    // im Protokoll, wo sie hingehoeren.
    Livewire::test(AdminProtokollHistorie::class)
        ->assertDontSee('Streng-Geheim-2026')
        ->assertSee('1');
});

test('ohne see_hidden kein Zugriff auf die Einstellung', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny']));

    Livewire::test(AdminProtokollHistorie::class)->assertForbidden();
});
