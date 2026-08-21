<?php

use App\Livewire\AdminKennwortHistorie;
use App\Livewire\AdminPapierkorb;
use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\PasswordHistory;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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

test('bei null Tagen wird nichts aufbewahrt', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));
    Setting::setzen(Setting::PASSWORT_HISTORIE_TAGE, 0);

    $firewall = firewallMitKennwort('Geheim!2026');
    $firewall->update(['password' => 'Neu!2026']);

    // Abgeschaltet heisst: es entsteht gar kein Eintrag, nicht einer, der
    // spaeter geloescht wird.
    expect(PasswordHistory::count())->toBe(0);
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

test('der Aufraeum-Befehl haelt sich an die Frist', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));
    Setting::setzen(Setting::PASSWORT_HISTORIE_TAGE, 90);

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Mittel!2026']);
    $firewall->update(['password' => 'Neu!2026']);

    // Einen der beiden alt machen.
    $aeltester = PasswordHistory::orderBy('id')->first();
    DB::table('password_histories')->where('id', $aeltester->id)
        ->update(['created_at' => now()->subDays(100)]);

    $this->artisan('kennwoerter:aufraeumen')->assertSuccessful();

    expect(PasswordHistory::count())->toBe(1);
    expect(PasswordHistory::find($aeltester->id))->toBeNull();
});

test('bei abgeschalteter Historie raeumt der Befehl alles ab', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Neu!2026']);
    expect(PasswordHistory::count())->toBe(1);

    // Wer die Historie abschaltet, will auch das los sein, was schon da ist.
    Setting::setzen(Setting::PASSWORT_HISTORIE_TAGE, 0);
    $this->artisan('kennwoerter:aufraeumen')->assertSuccessful();

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

test('die Frist laesst sich auf der eigenen Seite einstellen', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));

    Livewire::test(AdminKennwortHistorie::class)
        ->assertSet('tage', 90)
        ->set('tage', 21)
        ->call('fristSpeichern');

    expect(Setting::passwortHistorieTage())->toBe(21);
});

test('eine unsinnige Frist wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    Setting::setzen(Setting::PASSWORT_HISTORIE_TAGE, 90);

    Livewire::test(AdminKennwortHistorie::class)
        ->set('tage', -5)
        ->call('fristSpeichern')
        ->assertHasErrors('tage');

    expect(Setting::passwortHistorieTage())->toBe(90);
});

test('die Uebersicht zeigt das alte Kennwort erst auf Klick', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'firewall_update']));

    $firewall = firewallMitKennwort('Das-Alte-2026');
    $firewall->update(['password' => 'Das-Neue-2026']);
    $eintrag = PasswordHistory::sole();

    $test = Livewire::test(AdminKennwortHistorie::class);

    // Zugeklappt steht der Wert nirgends im Quelltext - sonst laege auf einer
    // Seite die halbe Kennwortgeschichte aller Kunden offen.
    $test->assertDontSee('Das-Alte-2026')
        ->assertSee($firewall->name);

    $test->call('aufdecken', $eintrag->id)->assertSee('Das-Alte-2026');
    $test->call('verbergen', $eintrag->id)->assertDontSee('Das-Alte-2026');
});

test('die Suche findet ueber den Geraetenamen', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'firewall_update']));

    $gesucht = firewallMitKennwort('A!2026');
    $gesucht->update(['name' => 'FW-Gesucht', 'password' => 'B!2026']);

    $anderer = firewallMitKennwort('C!2026');
    $anderer->update(['name' => 'FW-Anderer', 'password' => 'D!2026']);

    Livewire::test(AdminKennwortHistorie::class)
        ->set('suche', 'Gesucht')
        ->assertSee('FW-Gesucht')
        ->assertDontSee('FW-Anderer');
});

test('ein Eintrag laesst sich einzeln loeschen', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'firewall_update']));

    $firewall = firewallMitKennwort('Weg!2026');
    $firewall->update(['password' => 'Neu!2026']);
    $eintrag = PasswordHistory::sole();

    Livewire::test(AdminKennwortHistorie::class)
        ->call('loeschen', $eintrag->id);

    expect(PasswordHistory::count())->toBe(0);
});

test('ohne see_hidden kein Zugriff auf die Uebersicht', function () {
    $this->actingAs(userWithPermissions(['firewall_viewAny']));

    Livewire::test(AdminKennwortHistorie::class)->assertForbidden();
});

test('der Geraetename steht im Eintrag, nicht in einer Nachfrage', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = firewallMitKennwort('Alt!2026');
    $firewall->update(['password' => 'Neu!2026']);

    // Ein Eintrag soll lesbar bleiben, wenn das Geraet laengst weg ist - und
    // ein Verweis auf eine entfernte Klasse braeche beim Aufloesen die Seite.
    expect(PasswordHistory::sole()->subject_name)->toBe($firewall->name);
});
