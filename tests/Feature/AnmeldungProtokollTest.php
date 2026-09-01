<?php

use App\Models\User;
use App\Support\ZweiteStufe;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

function anmeldbarerNutzer(string $kennwort = 'Ein-Gutes-Kennwort-2026'): User
{
    $nutzer = userWithPermissions([]);
    $nutzer->forceFill(['password' => Hash::make($kennwort)])->save();

    return $nutzer->fresh();
}

/** Der jüngste Protokolleintrag. Eigener Name: letzterEintrag() gibt es schon
 *  in KennwortProtokollTest, und Pest-Helfer teilen sich einen Namensraum. */
function juengsterProtokolleintrag(): ?Activity
{
    return Activity::latest('id')->first();
}

test('eine Anmeldung steht im Protokoll - mit Herkunft', function () {
    $nutzer = anmeldbarerNutzer();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    $eintrag = juengsterProtokolleintrag();

    expect($eintrag->event)->toBe('anmeldung')
        ->and($eintrag->causer_id)->toBe($nutzer->id)
        ->and($eintrag->properties['attributes']['IP'] ?? null)->toBe('127.0.0.1');
});

test('ein gescheiterter Versuch steht im Protokoll - mit dem versuchten Namen', function () {
    anmeldbarerNutzer();

    $this->post('/login', ['username' => 'gibtesnicht', 'password' => 'geraten']);

    $eintrag = juengsterProtokolleintrag();

    expect($eintrag->event)->toBe('anmeldung_gescheitert')
        ->and($eintrag->properties['objekt'] ?? null)->toBe('gibtesnicht');
});

test('das versuchte Kennwort steht nirgends im Protokoll', function () {
    // Es steckt im Failed-Ereignis mit drin. Wer sich beim Benutzernamen
    // vertippt, haette sonst sein richtiges Kennwort im Klartext im Protokoll.
    anmeldbarerNutzer();

    $this->post('/login', ['username' => 'gibtesnicht', 'password' => 'Mein-Echtes-Kennwort']);

    expect(json_encode(Activity::all()->pluck('properties')))
        ->not->toContain('Mein-Echtes-Kennwort');
});

test('die Sperre steht im Protokoll', function () {
    $nutzer = anmeldbarerNutzer();

    foreach (range(1, 6) as $ignoriert) {
        $this->post('/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    expect(Activity::where('event', 'anmeldung_gesperrt')->exists())->toBeTrue();
});

test('ein falscher zweiter Faktor steht im Protokoll', function () {
    // Das Kennwort stimmte - Laravel loest hier kein Failed aus. Genau dieser
    // Fall ist aber der interessante.
    $nutzer = anmeldbarerNutzer();
    $geheimnis = app(ZweiteStufe::class)->geheimnisErzeugen();
    $nutzer->forceFill([
        'two_factor_secret' => $geheimnis,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);
    $this->post(route('two-factor.login'), ['code' => '000000']);

    $eintrag = juengsterProtokolleintrag();

    expect($eintrag->event)->toBe('anmeldung_gescheitert')
        ->and($eintrag->properties['attributes']['Schritt'] ?? null)->toBe('Zweite Stufe');
});

test('"zuletzt angemeldet" wird mitgeschrieben', function () {
    $nutzer = anmeldbarerNutzer();

    expect($nutzer->last_login_at)->toBeNull();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    expect($nutzer->fresh()->last_login_at)->not->toBeNull()
        ->and($nutzer->fresh()->last_login_ip)->toBe('127.0.0.1');
});

test('eine Anmeldung erzeugt keinen zusätzlichen Änderungseintrag', function () {
    // Ohne saveQuietly haenge an jede Anmeldung ein "Geaendert"-Eintrag am
    // Benutzer - das Protokoll waere doppelt so lang und halb so brauchbar.
    $nutzer = anmeldbarerNutzer();

    // Vorher zaehlen: Das Anlegen des Nutzers hat selbst schon einen
    // Aenderungseintrag hinterlassen.
    $vorher = Activity::where('event', 'updated')->where('subject_id', $nutzer->id)->count();

    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    expect(Activity::where('event', 'updated')->where('subject_id', $nutzer->id)->count())->toBe($vorher);
});

test('die Protokollseite zeigt Anmeldungen an', function () {
    $nutzer = anmeldbarerNutzer();
    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    nutzerWechseln(userWithPermissions(['admin_activity']));

    $this->get(route('admin.activity.index'))
        ->assertStatus(200)
        ->assertSee('Angemeldet');
});

test('die Benutzerliste zeigt, wann sich jemand zuletzt angemeldet hat', function () {
    $nutzer = anmeldbarerNutzer();
    $this->post('/login', ['username' => $nutzer->username, 'password' => 'Ein-Gutes-Kennwort-2026']);

    nutzerWechseln(userWithPermissions(['admin_user']));

    $this->get(route('admin.user.index'))
        ->assertStatus(200)
        ->assertSee($nutzer->fresh()->last_login_at->format('d.m.Y'))
        // Und ein Zugang, der nie benutzt wurde, faellt auf.
        ->assertSee('noch nie');
});
