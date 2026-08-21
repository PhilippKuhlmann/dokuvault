<?php

use App\Livewire\ProtokollKennwort;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\PasswordHistory;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

function eineFirewall(array $werte = []): Firewall
{
    $customer = Customer::factory()->create();

    return Firewall::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => Site::factory()->create(['customer_id' => $customer->id])->id,
        ...$werte,
    ]);
}

/**
 * Der letzte Protokolleintrag zu einem Objekt, wahlweise zu einem Ereignis.
 *
 * Das Ereignis muss angebbar sein: Ein Speichern kann mehrere Eintraege
 * erzeugen, etwa wenn das Model nebenbei Standardwerte setzt.
 */
function letzterEintrag($objekt, ?string $ereignis = null): ?Activity
{
    return Activity::where('subject_type', $objekt::class)
        ->where('subject_id', $objekt->id)
        ->when($ereignis, fn ($abfrage) => $abfrage->where('event', $ereignis))
        ->latest('id')
        ->first();
}

test('eine Kennwortaenderung steht im Protokoll', function () {
    $nutzer = userWithPermissions(['firewall_update']);
    $this->actingAs($nutzer);

    $firewall = eineFirewall(['password' => 'Alt!2026']);
    $firewall->update(['password' => 'Neu!2026']);

    $eintrag = letzterEintrag($firewall, 'password_changed');

    expect($eintrag->event)->toBe('password_changed');
    expect($eintrag->properties['felder'])->toBe(['password']);
    expect($eintrag->causer_id)->toBe($nutzer->id);
});

test('der Wert steht nirgends im Eintrag', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = eineFirewall(['password' => 'Alt!2026']);
    $firewall->update(['password' => 'Streng-Geheim-2026']);

    // Der ganze Eintrag als Text - damit faellt auch auf, wenn der Wert an
    // einer Stelle landet, an die hier niemand denkt.
    $roh = json_encode(letzterEintrag($firewall, 'password_changed')->toArray(), JSON_UNESCAPED_UNICODE);

    expect($roh)->not->toContain('Streng-Geheim-2026');
    expect($roh)->not->toContain('Alt!2026');
});

test('das gleiche Kennwort noch einmal gespeichert ist keine Aenderung', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = eineFirewall(['password' => 'Gleich!2026']);
    // Das Formular schickt den unveraenderten Wert mit. Die Verschluesselung
    // erzeugt dabei einen anderen Chiffretext - ohne Klartext-Vergleich haette
    // jedes Speichern eine Kennwortaenderung gemeldet.
    $firewall->update(['password' => 'Gleich!2026', 'name' => 'FW-Neuer-Name']);

    expect(Activity::where('event', 'password_changed')->count())->toBe(0);
    // Der Namenswechsel wird aber sehr wohl protokolliert.
    expect(letzterEintrag($firewall, 'updated')->properties['attributes'])
        ->toHaveKey('name');
});

test('mehrere Kennwortfelder werden einzeln benannt', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = eineFirewall(['password' => 'A!2026', 'usc_pin' => '111111']);
    $firewall->update(['password' => 'B!2026', 'usc_pin' => '222222']);

    expect(letzterEintrag($firewall, 'password_changed')->properties['felder'])
        ->toBe(['password', 'usc_pin']);
});

test('auch das Anmeldekennwort eines Benutzers wird protokolliert', function () {
    $this->actingAs(userWithPermissions(['user_update']));

    $benutzer = User::factory()->create(['password' => Hash::make('alt-geheim')]);
    $benutzer->update(['password' => Hash::make('neu-geheim')]);

    $eintrag = letzterEintrag($benutzer, 'password_changed');

    expect($eintrag->event)->toBe('password_changed');
    expect(json_encode($eintrag->properties->toArray()))->not->toContain('$2y$');
});

test('ein Kennwort landet nicht im Aenderungs-Eintrag', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = eineFirewall(['usc_pin' => '111111', 'cloud_backup_password' => 'Wolke!2026']);
    $firewall->update([
        'name' => 'FW-Geaendert',
        'usc_pin' => '222222',
        'cloud_backup_password' => 'Wolke!2027',
        'pppoe_password' => null,
    ]);

    // Genau hier lag der Fehler: Die Ausschlussliste nannte "uscpin" und
    // "cloudBackupPassword", die Spalten heissen anders - und damit standen
    // beide im Klartext im Protokoll, alter und neuer Wert.
    $aenderung = Activity::where('subject_id', $firewall->id)
        ->where('event', 'updated')->latest('id')->first();

    $roh = json_encode($aenderung->properties->toArray(), JSON_UNESCAPED_UNICODE);

    foreach (['111111', '222222', 'Wolke!2026', 'Wolke!2027'] as $geheim) {
        expect($roh)->not->toContain($geheim);
    }

    expect($aenderung->properties['attributes'])->toHaveKey('name');
});

test('die Protokollseite zeigt die Kennwortaenderung', function () {
    // Das Protokoll haengt an der isAdmin-Middleware, nicht an einer
    // Berechtigung.
    $rolle = Role::find(Role::IS_ADMIN) ?? Role::factory()->create(['id' => Role::IS_ADMIN]);
    $this->actingAs(User::factory()->create(['role_id' => $rolle->id]));

    $firewall = eineFirewall(['name' => 'FW-Protokolltest', 'password' => 'Alt!2026']);
    $firewall->update(['password' => 'Neu!2026']);

    $antwort = $this->get(route('admin.activity.index'));

    $antwort->assertOk();
    $antwort->assertSee('Kennwort geändert');
    // Der Name kommt aus dem Eintrag selbst, nicht aus einer Nachfrage beim
    // Objekt - ein Eintrag ueberlebt sein Objekt.
    $antwort->assertSee('FW-Protokolltest');
});

test('das Protokoll zeigt das bisherige Kennwort auf Klick', function () {
    $this->actingAs(userWithPermissions(['admin_activity', 'firewall_update']));

    $firewall = eineFirewall(['password' => 'Das-Alte-2026']);
    $firewall->update(['password' => 'Das-Neue-2026']);

    $eintrag = letzterEintrag($firewall, 'password_changed');
    $ids = $eintrag->properties['verlauf_ids'];

    expect($ids)->toHaveCount(1);
    // Im Eintrag stehen Verweise, keine Werte.
    expect(json_encode($eintrag->properties->toArray()))->not->toContain('Das-Alte-2026');

    $test = Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['password']]);

    $test->assertDontSee('Das-Alte-2026');
    $test->call('zeigen')->assertSee('Das-Alte-2026');
    $test->call('verbergen')->assertDontSee('Das-Alte-2026');
});

test('ohne admin_activity bleibt das Kennwort im Protokoll verborgen', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = eineFirewall(['password' => 'Geheim-2026']);
    $firewall->update(['password' => 'Neu-2026']);

    $ids = letzterEintrag($firewall, 'password_changed')->properties['verlauf_ids'];

    Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['password']])
        ->call('zeigen')
        ->assertForbidden();
});

test('nach Ablauf der Frist sagt das Protokoll es ehrlich', function () {
    $this->actingAs(userWithPermissions(['admin_activity', 'firewall_update']));

    $firewall = eineFirewall(['password' => 'Weg-2026']);
    $firewall->update(['password' => 'Neu-2026']);

    $ids = letzterEintrag($firewall, 'password_changed')->properties['verlauf_ids'];
    PasswordHistory::whereIn('id', $ids)->delete();

    // Dass die Aenderung stattfand, bleibt im Protokoll - nur der Wert ist weg.
    // Geprueft wird der Zustand, nicht der Wortlaut: Der Hinweis ist uebersetzt.
    Livewire::test(ProtokollKennwort::class, ['ids' => $ids, 'felder' => ['password']])
        ->call('zeigen')
        ->assertSet('offen', true)
        ->assertSet('werte', []);
});

test('bestehende Eintraege werden nachtraeglich verbunden', function () {
    $this->actingAs(userWithPermissions(['firewall_update']));

    $firewall = eineFirewall(['password' => 'Alt-2026']);
    $firewall->update(['password' => 'Neu-2026']);

    $eintrag = letzterEintrag($firewall, 'password_changed');
    $ids = $eintrag->properties['verlauf_ids'];

    // Zustand herstellen, wie er vor dem Verweis aussah.
    $ohneVerweis = $eintrag->properties->toArray();
    unset($ohneVerweis['verlauf_ids']);
    DB::table('activity_log')->where('id', $eintrag->id)
        ->update(['properties' => json_encode($ohneVerweis)]);

    $migration = require database_path('migrations/2026_08_21_140000_link_existing_password_changes.php');
    $migration->up();

    // Ohne die Verknuepfung fehlte im Protokoll der Knopf, obwohl der Wert dalag.
    expect(Activity::find($eintrag->id)->properties['verlauf_ids'])->toBe($ids);
});
