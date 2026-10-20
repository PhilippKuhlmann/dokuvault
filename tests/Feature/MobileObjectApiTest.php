<?php

use App\Models\ADDomain;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Customer;
use App\Models\LicenseAccess;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function objektAppToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function objektAppServer(Customer $kunde): Server
{
    return Server::create([
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => 'SRV-01',
        'bmcPassword' => 'BMC-GEHEIM',
        'form_factor' => 'tower',
    ]);
}

const SERVER_RECHTE = ['server_viewAny', 'server_create', 'server_update', 'server_delete'];

// --- Formular ------------------------------------------------------------------

test('jedes Formular laesst sich fuer die App aufbauen', function () {
    $kunde = Customer::factory()->create();
    $token = objektAppToken(collect(config('forms'))->keys()->map(fn ($typ) => $typ.'_create')->all());

    foreach (array_keys(config('forms')) as $typ) {
        $this->withToken($token)
            ->getJson("/api/mobile/customers/{$kunde->slug}/forms/{$typ}")
            ->assertOk()
            ->assertJsonPath('typ', $typ)
            ->assertJsonStructure(['einzahl', 'felder' => [['name', 'label', 'art', 'gruppe']], 'werte']);
    }
});

test('das Formular nennt Auswahl, Pflicht und Geheimnisse', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id, 'name' => 'Zentrale']);
    Site::factory()->create(['customer_id' => Customer::factory()->create()->id, 'name' => 'Fremd']);

    $felder = collect($this->withToken(objektAppToken(SERVER_RECHTE))
        ->getJson("/api/mobile/customers/{$kunde->slug}/forms/server")
        ->assertOk()
        ->json('felder'))->keyBy('name');

    expect($felder['name']['pflicht'])->toBeTrue()
        ->and($felder['bmcPassword']['geheim'])->toBeTrue()
        // Explizit false statt weggelassen - robuster fuer Swift.
        ->and($felder['name']['geheim'])->toBeFalse()
        ->and($felder['name']['mehrfach'])->toBeFalse()
        // Nur die Standorte dieses Kunden.
        ->and($felder['site_id']['optionen'])->toBe([['wert' => (string) $standort->id, 'label' => 'Zentrale']]);
});

test('ohne Anlegen-Recht gibt es kein leeres Formular', function () {
    $kunde = Customer::factory()->create();

    $this->withToken(objektAppToken(['server_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/forms/server")
        ->assertForbidden();
});

test('ein unbekannter Typ ist ein 404', function () {
    $kunde = Customer::factory()->create();

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->getJson("/api/mobile/customers/{$kunde->slug}/forms/gibtsnicht")
        ->assertNotFound();
});

test('das Bearbeiten-Formular verraet kein Geheimnis', function () {
    $server = objektAppServer(Customer::factory()->create());

    $antwort = $this->withToken(objektAppToken(SERVER_RECHTE))
        ->getJson("/api/mobile/objects/server/{$server->id}/form")
        ->assertOk()
        ->assertJsonPath('werte.name', 'SRV-01')
        ->assertJsonPath('werte.bmcPassword', null)
        ->assertJsonPath('geheim_gesetzt.bmcPassword', true)
        ->assertJsonPath('darf_loeschen', true);

    expect($antwort->getContent())->not->toContain('BMC-GEHEIM');
});

// --- Anlegen -------------------------------------------------------------------

test('die App legt einen Server an', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $os = OperatingSystem::firstOrCreate(['name' => 'Debian 13']);

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->postJson("/api/mobile/customers/{$kunde->slug}/server", [
            'name' => 'SRV-NEU', 'site_id' => $standort->id, 'operating_system_id' => $os->id, 'form_factor' => 'tower',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'SRV-NEU')
        ->assertJsonPath('kunde.slug', $kunde->slug);

    expect(Server::where('customer_id', $kunde->id)->where('name', 'SRV-NEU')->exists())->toBeTrue();
});

test('dieselbe Pruefung wie im Browser', function () {
    $kunde = Customer::factory()->create();
    $fremderStandort = Site::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->postJson("/api/mobile/customers/{$kunde->slug}/server", ['site_id' => $fremderStandort->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'site_id']);

    expect(Server::count())->toBe(0);
});

test('ohne Recht wird nichts angelegt', function () {
    $kunde = Customer::factory()->create();

    $this->withToken(objektAppToken(['server_viewAny']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/server", ['name' => 'X'])
        ->assertForbidden();

    expect(Server::count())->toBe(0);
});

test('ein Kundennutzer legt beim fremden Kunden nichts an', function () {
    $kunde = Customer::factory()->create();
    $eigener = Customer::factory()->create();

    $this->withToken(objektAppToken(SERVER_RECHTE, $eigener))
        ->postJson("/api/mobile/customers/{$kunde->slug}/server", ['name' => 'X'])
        ->assertForbidden();

    expect(Server::count())->toBe(0);
});

test('eine Datei kommt mit dem Anlegen', function () {
    Storage::fake('local');
    $kunde = Customer::factory()->create();

    $this->withToken(objektAppToken(['licenseaccess_viewAny', 'licenseaccess_create']))
        ->post("/api/mobile/customers/{$kunde->slug}/licenseaccess", [
            'name' => 'Lizenz', 'key' => 'ABC-123', 'file_name' => 'Urkunde',
            'datei' => UploadedFile::fake()->create('urkunde.pdf', 10),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $lizenz = LicenseAccess::firstOrFail();

    expect($lizenz->file_path)->toStartWith($kunde->slug.'/licenseaccess/');
    Storage::disk('local')->assertExists($lizenz->file_path);
});

// --- Aendern -------------------------------------------------------------------

test('eine Aenderung schreibt nur, was die App schickt', function () {
    $server = objektAppServer(Customer::factory()->create());
    $kennwortVorher = $server->getRawOriginal('bmcPassword');

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->putJson("/api/mobile/objects/server/{$server->id}", ['name' => 'SRV-UMBENANNT'])
        ->assertOk()
        ->assertJsonPath('name', 'SRV-UMBENANNT');

    $server->refresh();

    // Das Kennwort hat die App nie gesehen - es bleibt, wie es war.
    expect($server->name)->toBe('SRV-UMBENANNT')
        ->and($server->getRawOriginal('bmcPassword'))->toBe($kennwortVorher);
});

test('ein Pflichtfeld laesst sich nicht leeren', function () {
    $server = objektAppServer(Customer::factory()->create());

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->putJson("/api/mobile/objects/server/{$server->id}", ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($server->fresh()->name)->toBe('SRV-01');
});

test('Gruppen eines AD-Benutzers lassen sich setzen, nur die des Kunden', function () {
    $kunde = Customer::factory()->create();
    $benutzer = ADUser::create(['customer_id' => $kunde->id, 'username' => 'max.muster', 'enabled' => true]);
    $gruppe = ADGroup::create(['customer_id' => $kunde->id, 'name' => 'GG-Vertrieb']);
    $fremd = ADGroup::create(['customer_id' => Customer::factory()->create()->id, 'name' => 'GG-Fremd']);
    $token = objektAppToken(['aduser_viewAny', 'aduser_update']);

    $this->withToken($token)
        ->putJson("/api/mobile/objects/aduser/{$benutzer->id}", ['groups' => [$fremd->id]])
        ->assertUnprocessable();

    $this->withToken($token)
        ->putJson("/api/mobile/objects/aduser/{$benutzer->id}", ['groups' => [$gruppe->id]])
        ->assertOk();

    expect($benutzer->fresh()->groups->pluck('name')->all())->toBe(['GG-Vertrieb']);
});

test('Domaenencontroller lassen sich ueber das Host-Feld setzen', function () {
    $kunde = Customer::factory()->create();
    $server = objektAppServer($kunde);
    $domaene = ADDomain::factory()->create(['customer_id' => $kunde->id]);

    $this->withToken(objektAppToken(['addomain_viewAny', 'addomain_update']))
        ->putJson("/api/mobile/objects/addomain/{$domaene->id}", ['domain_controllers' => ['server:'.$server->id]])
        ->assertOk();

    expect($domaene->fresh()->hostNames('dc'))->toBe('SRV-01');
});

test('ein Kundennutzer aendert nichts beim fremden Kunden', function () {
    $server = objektAppServer(Customer::factory()->create());

    $this->withToken(objektAppToken(SERVER_RECHTE, Customer::factory()->create()))
        ->putJson("/api/mobile/objects/server/{$server->id}", ['name' => 'X'])
        ->assertNotFound();

    expect($server->fresh()->name)->toBe('SRV-01');
});

// --- Loeschen ------------------------------------------------------------------

test('loeschen braucht das Recht', function () {
    $server = objektAppServer(Customer::factory()->create());

    $this->withToken(objektAppToken(['server_viewAny', 'server_update']))
        ->deleteJson("/api/mobile/objects/server/{$server->id}")
        ->assertForbidden();

    expect($server->fresh())->not->toBeNull();
});

test('geloescht landet im Papierkorb', function () {
    $server = objektAppServer(Customer::factory()->create());

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->deleteJson("/api/mobile/objects/server/{$server->id}")
        ->assertNoContent();

    expect(Server::find($server->id))->toBeNull()
        ->and(Server::withTrashed()->find($server->id))->not->toBeNull();
});

test('ohne Erzeuger gibt es nichts zu erzeugen', function () {
    $kunde = Customer::factory()->create();

    $this->withToken(objektAppToken(SERVER_RECHTE))
        ->postJson("/api/mobile/customers/{$kunde->slug}/forms/server/generate")
        ->assertNotFound();
});
