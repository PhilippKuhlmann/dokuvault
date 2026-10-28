<?php

use App\Models\ADDomain;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\Server;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use App\Support\MobilObjekte;
use App\Support\ZweiteStufe;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

const MOBIL_KENNWORT = 'Ein-Gutes-Kennwort-2026';

function mobilNutzer(array $rechte = [], ?Customer $kunde = null): User
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill([
        'password' => Hash::make(MOBIL_KENNWORT),
        'customer_id' => $kunde?->id,
    ])->save();

    return $nutzer->fresh();
}

/** Ein Token, wie es die App nach der Anmeldung haelt. */
function mobilToken(User $nutzer): string
{
    return $nutzer->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function mobilServer(Customer $kunde, string $name = 'SRV-01'): Server
{
    return Server::create([
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => $name,
        'serialNumber' => 'SN-'.$name,
        'bmcPassword' => 'BMC-GEHEIM',
        'form_factor' => 'rack',
        'height_units' => 1,
    ]);
}

/** Ein Login am Server - die Verknuepfung, ueber die die App das Kennwort holt. */
function mobilZugang(Server $server, string $kennwort = 'LOGIN-GEHEIM')
{
    $login = LoginGeneral::create([
        'customer_id' => $server->customer_id,
        'name' => 'Administrator',
        'username' => 'admin',
        'password' => $kennwort,
    ]);

    return $server->credentialLinks()->create([
        'customer_id' => $server->customer_id,
        'login_general_id' => $login->id,
    ]);
}

function mobilZweiteStufe(User $nutzer): string
{
    $geheimnis = app(ZweiteStufe::class)->geheimnisErzeugen();

    $nutzer->forceFill([
        'two_factor_secret' => $geheimnis,
        'two_factor_recovery_codes' => app(ZweiteStufe::class)->wiederherstellungscodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $geheimnis;
}

// --- Anmeldung ----------------------------------------------------------------

test('die Anmeldung gibt ein Token, das fuer die App gilt', function () {
    $nutzer = mobilNutzer();

    $antwort = $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => MOBIL_KENNWORT,
        'geraet' => 'iPhone von Test',
    ])->assertOk()->assertJsonStructure(['token', 'laeuft_ab', 'nutzer' => ['name', 'username', 'kunde', 'rechte']]);

    expect($nutzer->tokens()->first()->name)->toBe('App iPhone von Test')
        ->and($nutzer->tokens()->first()->expires_at)->not->toBeNull();

    $this->withToken($antwort->json('token'))->getJson('/api/mobile/me')
        ->assertOk()
        ->assertJsonPath('username', $nutzer->username);

    // Dasselbe Protokoll wie im Browser.
    expect(Activity::where('event', 'anmeldung')->where('causer_id', $nutzer->id)->exists())->toBeTrue()
        ->and($nutzer->fresh()->last_login_at)->not->toBeNull();
});

test('ein falsches Kennwort gibt kein Token und steht im Protokoll', function () {
    $nutzer = mobilNutzer();

    $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => 'falsch',
    ])->assertStatus(422)->assertJsonValidationErrors('username');

    expect($nutzer->tokens()->count())->toBe(0)
        ->and(Activity::where('event', 'anmeldung_gescheitert')->exists())->toBeTrue();
});

test('ein gesperrter Zugang meldet sich auch aus der App nicht an', function () {
    $nutzer = mobilNutzer();
    $nutzer->forceFill(['deactivated_at' => now()])->save();

    $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => MOBIL_KENNWORT,
    ])->assertStatus(422);

    expect($nutzer->tokens()->count())->toBe(0);
});

test('ein Token eines spaeter gesperrten Zugangs gilt nicht mehr', function () {
    $nutzer = mobilNutzer();
    $token = mobilToken($nutzer);
    $nutzer->forceFill(['deactivated_at' => now()])->save();

    $this->withToken($token)->getJson('/api/mobile/me')->assertStatus(403);
});

test('dieselbe Bremse wie im Browser: nach zu vielen Fehlversuchen ist zu', function () {
    $nutzer = mobilNutzer();

    foreach (range(1, Setting::anmeldungVersuche()) as $versuch) {
        $this->postJson('/api/mobile/login', ['username' => $nutzer->username, 'password' => 'falsch']);
    }

    // Auch mit dem richtigen Kennwort.
    $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => MOBIL_KENNWORT,
    ])->assertStatus(422);

    expect($nutzer->tokens()->count())->toBe(0);
});

// --- Zweite Stufe -----------------------------------------------------------

test('mit zweiter Stufe gibt es erst nach dem Code ein Token', function () {
    $nutzer = mobilNutzer();
    $geheimnis = mobilZweiteStufe($nutzer);

    $vorgang = $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => MOBIL_KENNWORT,
    ])->assertOk()
        ->assertJsonPath('zweite_stufe', true)
        ->assertJsonMissingPath('token')
        ->json('vorgang');

    expect($nutzer->tokens()->count())->toBe(0);

    $this->postJson('/api/mobile/login/two-factor', ['vorgang' => $vorgang, 'code' => '000000'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->postJson('/api/mobile/login/two-factor', [
        'vorgang' => $vorgang,
        'code' => (new Google2FA)->getCurrentOtp($geheimnis),
    ])->assertOk()->assertJsonStructure(['token']);

    expect($nutzer->tokens()->count())->toBe(1);
});

test('ein Vorgang gibt nur ein einziges Token', function () {
    $nutzer = mobilNutzer();
    $geheimnis = mobilZweiteStufe($nutzer);

    $vorgang = $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => MOBIL_KENNWORT,
    ])->json('vorgang');

    $code = (new Google2FA)->getCurrentOtp($geheimnis);

    $this->postJson('/api/mobile/login/two-factor', ['vorgang' => $vorgang, 'code' => $code])->assertOk();

    // The next code, since the same one is refused as a replay.
    $google2fa = new Google2FA;
    $naechster = $google2fa->oathTotp($geheimnis, $google2fa->getTimestamp() + 1);

    $this->postJson('/api/mobile/login/two-factor', ['vorgang' => $vorgang, 'code' => $naechster])
        ->assertStatus(422)->assertJsonValidationErrors('vorgang');

    expect($nutzer->tokens()->count())->toBe(1);
});

test('ein abgelaufener oder erfundener Vorgang fuehrt nirgendwohin', function () {
    $nutzer = mobilNutzer();
    $geheimnis = mobilZweiteStufe($nutzer);
    $code = (new Google2FA)->getCurrentOtp($geheimnis);

    $abgelaufen = Crypt::encryptString(json_encode(['id' => $nutzer->id, 'bis' => now()->subMinute()->timestamp]));

    $this->postJson('/api/mobile/login/two-factor', ['vorgang' => $abgelaufen, 'code' => $code])
        ->assertStatus(422)->assertJsonValidationErrors('vorgang');

    $this->postJson('/api/mobile/login/two-factor', ['vorgang' => 'irgendwas', 'code' => $code])
        ->assertStatus(422)->assertJsonValidationErrors('vorgang');

    expect($nutzer->tokens()->count())->toBe(0);
});

test('wer die zweite Stufe einrichten muss, wird in den Browser geschickt', function () {
    $nutzer = mobilNutzer();
    $nutzer->forceFill(['two_factor_required' => true])->save();

    $this->postJson('/api/mobile/login', [
        'username' => $nutzer->username,
        'password' => MOBIL_KENNWORT,
    ])->assertStatus(403);

    expect($nutzer->tokens()->count())->toBe(0);
});

test('abmelden widerruft nur das eigene Token', function () {
    $nutzer = mobilNutzer();
    $dieses = mobilToken($nutzer);
    mobilToken($nutzer);

    $this->withToken($dieses)->postJson('/api/mobile/logout')->assertNoContent();

    expect($nutzer->tokens()->count())->toBe(1);
});

// --- Nachschlagen -------------------------------------------------------------

test('ein Kundennutzer sieht nur seinen Kunden', function () {
    $eigener = Customer::factory()->create(['name' => 'Eigener Kunde']);
    $fremder = Customer::factory()->create(['name' => 'Fremder Kunde']);
    $fremderServer = mobilServer($fremder);

    $token = mobilToken(mobilNutzer(['server_viewAny'], $eigener));

    $this->withToken($token)->getJson('/api/mobile/customers')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.slug', $eigener->slug);

    $this->withToken($token)->getJson('/api/mobile/customers/'.$fremder->slug)->assertForbidden();
    $this->withToken($token)->getJson('/api/mobile/customers/'.$fremder->slug.'/server')->assertForbidden();

    // 404 statt 403: Er soll nicht einmal erfahren, dass es die Nummer gibt.
    $this->withToken($token)->getJson('/api/mobile/objects/server/'.$fremderServer->id)->assertNotFound();
});

test('der Kunde zeigt nur Arten, die man sehen darf und die es gibt', function () {
    $kunde = Customer::factory()->create();
    mobilServer($kunde);

    $token = mobilToken(mobilNutzer(['server_viewAny']));

    $typen = collect($this->withToken($token)->getJson('/api/mobile/customers/'.$kunde->slug)->assertOk()->json('typen'));

    expect($typen->pluck('typ')->all())->toBe(['server'])
        ->and($typen->first()['anzahl'])->toBe(1);
});

// Je Nutzer ein eigener Test: Der Sanctum-Guard haelt im selben Test den
// ersten Nutzer fest, auch wenn danach ein anderes Token kommt.

test('die Liste einer Art zeigt die Objekte des Kunden', function () {
    $kunde = Customer::factory()->create();
    mobilServer($kunde, 'SRV-A');

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/customers/'.$kunde->slug.'/server')
        ->assertOk()
        ->assertJsonPath('eintraege.0.name', 'SRV-A');
});

test('AD-Benutzer und AD-Gruppen lassen sich auflisten und oeffnen', function () {
    $kunde = Customer::factory()->create();
    $benutzer = ADUser::create(['customer_id' => $kunde->id, 'username' => 'max.muster', 'enabled' => true]);
    $gruppe = ADGroup::create(['customer_id' => $kunde->id, 'name' => 'GG-Buchhaltung']);
    $token = mobilToken(mobilNutzer(['aduser_viewAny', 'adgroup_viewAny']));

    $this->withToken($token)->getJson('/api/mobile/customers/'.$kunde->slug.'/aduser')
        ->assertOk()->assertJsonPath('eintraege.0.name', 'max.muster');
    $this->withToken($token)->getJson('/api/mobile/customers/'.$kunde->slug.'/adgroup')
        ->assertOk()->assertJsonPath('eintraege.0.name', 'GG-Buchhaltung');
    $this->withToken($token)->getJson('/api/mobile/objects/aduser/'.$benutzer->id)
        ->assertOk()->assertJsonPath('kunde.slug', $kunde->slug);
    $this->withToken($token)->getJson('/api/mobile/objects/adgroup/'.$gruppe->id)
        ->assertOk()->assertJsonPath('kunde.slug', $kunde->slug);
});

// Liste und Detail laden den Kunden mit - ohne die Beziehung brach die Liste
// mit einem 500er ab (AD-Benutzer, Logins, Kontakte ...).
test('jede Art der App kennt ihren Kunden', function () {
    foreach ((new MobilObjekte)->typen() as $slug => [$klasse]) {
        expect(method_exists($klasse, 'customer'))->toBeTrue("{$slug} ohne customer()");
    }
});

test('die Liste einer Art braucht das Recht dazu', function () {
    $kunde = Customer::factory()->create();
    mobilServer($kunde, 'SRV-A');

    $this->withToken(mobilToken(mobilNutzer(['vm_viewAny'])))
        ->getJson('/api/mobile/customers/'.$kunde->slug.'/server')
        ->assertNotFound();
});

test('die Suche findet dasselbe wie im Browser', function () {
    $kunde = Customer::factory()->create();
    mobilServer($kunde, 'HH-SRV-07');

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/search?q=SRV-07')
        ->assertOk()
        ->assertJsonPath('0.typ', 'server')
        ->assertJsonPath('0.treffer.0.name', 'HH-SRV-07')
        ->assertJsonPath('0.treffer.0.kunde.slug', $kunde->slug);
});

test('die Suche achtet die Rechte', function () {
    mobilServer(Customer::factory()->create(), 'HH-SRV-07');

    $this->withToken(mobilToken(mobilNutzer(['vm_viewAny'])))
        ->getJson('/api/mobile/search?q=SRV-07')
        ->assertOk()
        ->assertJsonCount(0);
});

test('die Detailansicht nennt Geheimnisse, verraet sie aber nicht', function () {
    $kunde = Customer::factory()->create();
    $server = mobilServer($kunde);
    mobilZugang($server);

    $antwort = $this->withToken(mobilToken(mobilNutzer(['server_viewAny', 'logingeneral_viewAny'])))
        ->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->assertJsonPath('name', 'SRV-01')
        ->assertJsonPath('zugangsdaten.0.benutzer', 'admin')
        ->assertJsonPath('zugangsdaten.0.hat_kennwort', true);

    $felder = collect($antwort->json('gruppen'))->flatMap(fn ($gruppe) => $gruppe['felder'])->keyBy('name');

    expect($felder['bmcPassword']['art'])->toBe('geheim')
        ->and($felder['bmcPassword']['wert'])->toBeNull()
        ->and($felder['serialNumber']['wert'])->toBe('SN-SRV-01')
        ->and($antwort->getContent())->not->toContain('BMC-GEHEIM')
        ->and($antwort->getContent())->not->toContain('LOGIN-GEHEIM');
});

test('die Detailansicht blendet aus, was zur Bauform nicht passt', function () {
    $server = mobilServer(Customer::factory()->create());
    $server->forceFill(['form_factor' => 'tower', 'height_units' => 2])->save();

    $felder = collect($this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->json('gruppen'))->flatMap(fn ($gruppe) => $gruppe['felder'])->pluck('name');

    // Wie im Modal: Ein Standserver hat keine Hoeheneinheiten.
    expect($felder)->toContain('form_factor')->not->toContain('height_units');
});

test('ohne Login-Recht stehen keine Zugaenge in der Detailansicht', function () {
    $server = mobilServer(Customer::factory()->create());
    mobilZugang($server);

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->assertJsonCount(0, 'zugangsdaten');
});

// --- Kennwoerter ----------------------------------------------------------------

test('das Kennwort kommt einzeln und steht danach im Protokoll', function () {
    $server = mobilServer(Customer::factory()->create());
    $link = mobilZugang($server);
    $nutzer = mobilNutzer(['server_viewAny', 'logingeneral_viewAny']);

    $this->withToken(mobilToken($nutzer))
        ->postJson('/api/mobile/credentials/'.$link->id.'/password')
        ->assertOk()
        ->assertJsonPath('wert', 'LOGIN-GEHEIM');

    $eintrag = Activity::where('event', 'kennwort_angesehen')->latest('id')->first();

    expect($eintrag)->not->toBeNull()
        ->and($eintrag->causer_id)->toBe($nutzer->id)
        ->and(json_encode($eintrag->properties))->not->toContain('LOGIN-GEHEIM');
});

test('ohne Login-Recht gibt es kein Kennwort', function () {
    $link = mobilZugang(mobilServer(Customer::factory()->create()));

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->postJson('/api/mobile/credentials/'.$link->id.'/password')
        ->assertForbidden();
});

test('ein Kundennutzer holt kein Kennwort eines fremden Kunden', function () {
    $eigener = Customer::factory()->create();
    $link = mobilZugang(mobilServer(Customer::factory()->create()));

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny', 'logingeneral_viewAny'], $eigener)))
        ->postJson('/api/mobile/credentials/'.$link->id.'/password')
        ->assertForbidden();

    expect(Activity::where('event', 'kennwort_angesehen')->exists())->toBeFalse();
});

test('die Bremse gilt auch in der App', function () {
    $link = mobilZugang(mobilServer(Customer::factory()->create()));
    $token = mobilToken(mobilNutzer(['server_viewAny', 'logingeneral_viewAny']));

    foreach (range(1, Setting::kennwortAbrufe()) as $abruf) {
        $this->withToken($token)->postJson('/api/mobile/credentials/'.$link->id.'/password')->assertOk();
    }

    $this->withToken($token)->postJson('/api/mobile/credentials/'.$link->id.'/password')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('ein Geheimnis am Geraet kommt einzeln, ein gewoehnliches Feld gar nicht', function () {
    $server = mobilServer(Customer::factory()->create());
    $token = mobilToken(mobilNutzer(['server_viewAny']));

    $this->withToken($token)
        ->postJson('/api/mobile/objects/server/'.$server->id.'/secret/bmcPassword')
        ->assertOk()
        ->assertJsonPath('wert', 'BMC-GEHEIM');

    // Nur Geheimnis-Spalten: Ueber diesen Weg kommt kein beliebiges Attribut.
    $this->withToken($token)
        ->postJson('/api/mobile/objects/server/'.$server->id.'/secret/name')
        ->assertNotFound();
});

test('ein Kundennutzer holt kein Geheimnis eines fremden Geraets', function () {
    $server = mobilServer(Customer::factory()->create());
    $eigener = Customer::factory()->create();

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny'], $eigener)))
        ->postJson('/api/mobile/objects/server/'.$server->id.'/secret/bmcPassword')
        ->assertNotFound();
});

// --- Was ueber die Formularfelder hinausgeht -----------------------------------

/** Die Felder der Detailansicht, nach Name. */
function mobilFelder($antwort)
{
    return collect($antwort->json('gruppen'))->flatMap(fn ($gruppe) => $gruppe['felder'])->keyBy('name');
}

test('AD-Benutzer und -Gruppen zeigen ihre Verknuepfungen', function () {
    $kunde = Customer::factory()->create();
    $benutzer = ADUser::create(['customer_id' => $kunde->id, 'username' => 'max.muster', 'enabled' => true]);
    $gruppen = collect(['GG-Vertrieb', 'GG-Buchhaltung'])
        ->map(fn ($name) => ADGroup::create(['customer_id' => $kunde->id, 'name' => $name]));
    $benutzer->groups()->sync($gruppen->pluck('id'));
    $token = mobilToken(mobilNutzer(['aduser_viewAny', 'adgroup_viewAny']));

    $felder = mobilFelder($this->withToken($token)->getJson('/api/mobile/objects/aduser/'.$benutzer->id)->assertOk());

    // Als Text, damit die App sie ohne Aenderung zeigt; die Liste zum Falten.
    expect($felder['groups']['art'])->toBe('text')
        ->and($felder['groups']['wert'])->toBe('GG-Buchhaltung, GG-Vertrieb')
        ->and($felder['groups']['liste'])->toBe(['GG-Buchhaltung', 'GG-Vertrieb']);

    $felder = mobilFelder($this->withToken($token)->getJson('/api/mobile/objects/adgroup/'.$gruppen[0]->id)->assertOk());

    expect($felder['users']['liste'])->toBe(['max.muster']);
});

test('ein Host-Feld verweist auf das Geraet', function () {
    $kunde = Customer::factory()->create();
    $server = mobilServer($kunde, 'SRV-DC01');
    $domaene = ADDomain::factory()->create(['customer_id' => $kunde->id]);
    $domaene->syncHosts('dc', ['server:'.$server->id]);

    $felder = mobilFelder($this->withToken(mobilToken(mobilNutzer(['addomain_viewAny'])))
        ->getJson('/api/mobile/objects/addomain/'.$domaene->id)
        ->assertOk());

    expect($felder['domain_controllers']['wert'])->toBe('SRV-DC01')
        ->and($felder['domain_controllers']['verweise'])->toBe([['typ' => 'server', 'id' => $server->id, 'name' => 'SRV-DC01']]);
});

test('Dienste kommen als eine Zeile', function () {
    $server = mobilServer(Customer::factory()->create());
    $server->forceFill(['services' => 'AD,DNS,DHCP'])->save();

    $felder = mobilFelder($this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk());

    expect($felder['services']['wert'])->toBe('AD · DNS · DHCP');
});

test('Dienste kommen mit ihren Kachelfarben', function () {
    Service::create(['name' => 'DNS', 'color' => '#1f73d6']);
    Service::create(['name' => 'DHCP', 'color' => '#fde68a']);
    $server = mobilServer(Customer::factory()->create());
    $server->forceFill(['services' => 'AD,dns,DHCP'])->save();

    $felder = mobilFelder($this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk());

    expect($felder['services']['kacheln'])->toBe([
        ['name' => 'AD', 'farbe' => null, 'schrift' => null],
        ['name' => 'dns', 'farbe' => '#1f73d6', 'schrift' => '#ffffff'],
        ['name' => 'DHCP', 'farbe' => '#fde68a', 'schrift' => '#111827'],
    ])->and($felder['services']['liste'])->toBe(['AD', 'dns', 'DHCP']);
});

test('der Fingerprint steht am Schluessel und unter den Zugaengen', function () {
    $server = mobilServer(Customer::factory()->create());
    $schluessel = SshKey::create([
        'customer_id' => $server->customer_id, 'name' => 'Admin ed25519', 'username' => 'root',
        'key_type' => 'ed25519', 'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExample admin@buero',
    ]);
    $server->credentialLinks()->create(['customer_id' => $server->customer_id, 'login_general_id' => $schluessel->id]);
    $token = mobilToken(mobilNutzer(['server_viewAny', 'sshkey_viewAny']));

    $this->withToken($token)->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->assertJsonPath('zugangsdaten.0.art', 'schluessel')
        ->assertJsonPath('zugangsdaten.0.fingerprint', $schluessel->fingerprint);

    $felder = mobilFelder($this->withToken($token)->getJson('/api/mobile/objects/sshkey/'.$schluessel->id)->assertOk());

    expect($felder['fingerprint']['wert'])->toStartWith('SHA256:');
});

test('ein eingebautes Geraet nennt seinen Schrank', function () {
    $kunde = Customer::factory()->create();
    $server = mobilServer($kunde);
    $rack = Rack::create(['customer_id' => $kunde->id, 'site_id' => $server->site_id, 'name' => 'Rack HH-01', 'height_units' => 42]);
    $rack->items()->create(['side' => 'front', 'position' => 3, 'height_units' => 2, 'device_type' => Server::class, 'device_id' => $server->id]);

    $this->withToken(mobilToken(mobilNutzer(['server_viewAny'])))
        ->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->assertJsonPath('einbauort.text', 'Rack HH-01 · HE 3–4 · Vorderseite')
        ->assertJsonPath('einbauort.rack_id', $rack->id)
        ->assertJsonPath('einbauort.kunde.slug', $kunde->slug);
});

test('das Support-Ende kommt mit seinem Zustand', function () {
    $server = mobilServer(Customer::factory()->create());
    $server->operatingSystem->update(['eol_date' => now()->subMonth()->toDateString()]);
    $token = mobilToken(mobilNutzer(['server_viewAny']));

    $this->withToken($token)->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->assertJsonPath('support_ende.zustand', 'abgelaufen')
        ->assertJsonPath('support_ende.betriebssystem', 'Debian 13');

    $server->operatingSystem->update(['eol_date' => null]);

    $this->withToken($token)->getJson('/api/mobile/objects/server/'.$server->id)
        ->assertOk()
        ->assertJsonPath('support_ende', null)
        ->assertJsonPath('einbauort', null);
});
