<?php

use App\Models\Customer;
use App\Models\Domain;
use App\Models\LoginGeneral;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\PatchPanel;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Models\Server;
use App\Models\Site;

function geraetAppToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function geraetAppServer(Customer $kunde): Server
{
    return Server::create([
        'customer_id' => $kunde->id,
        'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id,
        'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id,
        'name' => 'SRV-01',
    ]);
}

const GERAET_RECHTE = ['server_viewAny', 'server_update', 'logingeneral_viewAny', 'logingeneral_create'];

// --- Zugangsdaten --------------------------------------------------------------

test('die App haengt einen vorhandenen Zugang an', function () {
    $server = geraetAppServer(Customer::factory()->create());
    $login = LoginGeneral::create(['customer_id' => $server->customer_id, 'name' => 'Administrator', 'username' => 'admin']);
    $token = geraetAppToken(GERAET_RECHTE);

    $this->withToken($token)->getJson("/api/mobile/objects/server/{$server->id}/credentials")
        ->assertOk()
        ->assertJsonPath('verfuegbar.0.name', 'Administrator')
        ->assertJsonPath('darf_neu', true);

    $this->withToken($token)
        ->postJson("/api/mobile/objects/server/{$server->id}/credentials", ['login_id' => $login->id, 'note' => 'iLO'])
        ->assertCreated()
        ->assertJsonPath('0.name', 'Administrator')
        ->assertJsonPath('0.notiz', 'iLO');
});

test('ein Zugang eines fremden Kunden laesst sich nicht anhaengen', function () {
    $server = geraetAppServer(Customer::factory()->create());
    $fremd = LoginGeneral::create(['customer_id' => Customer::factory()->create()->id, 'name' => 'Fremd']);

    $this->withToken(geraetAppToken(GERAET_RECHTE))
        ->postJson("/api/mobile/objects/server/{$server->id}/credentials", ['login_id' => $fremd->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('login_id');

    expect($server->credentialLinks()->count())->toBe(0);
});

test('ein neuer Zugang entsteht und haengt gleich am Geraet', function () {
    $server = geraetAppServer(Customer::factory()->create());

    $this->withToken(geraetAppToken(GERAET_RECHTE))
        ->postJson("/api/mobile/objects/server/{$server->id}/credentials", ['name' => 'root', 'username' => 'root', 'password' => 'geheim'])
        ->assertCreated()
        ->assertJsonPath('0.name', 'root')
        ->assertJsonPath('0.hat_kennwort', true);

    expect(LoginGeneral::where('customer_id', $server->customer_id)->where('name', 'root')->exists())->toBeTrue();
});

test('ohne Login-Anlegen-Recht entsteht kein neuer Zugang', function () {
    $server = geraetAppServer(Customer::factory()->create());

    $this->withToken(geraetAppToken(['server_viewAny', 'server_update', 'logingeneral_viewAny']))
        ->postJson("/api/mobile/objects/server/{$server->id}/credentials", ['name' => 'root'])
        ->assertForbidden();

    expect(LoginGeneral::count())->toBe(0);
});

test('loesen nimmt nur die Verknuepfung, der Zugang bleibt', function () {
    $server = geraetAppServer(Customer::factory()->create());
    $login = LoginGeneral::create(['customer_id' => $server->customer_id, 'name' => 'Administrator']);
    $link = $server->credentialLinks()->create(['customer_id' => $server->customer_id, 'login_general_id' => $login->id]);

    $this->withToken(geraetAppToken(GERAET_RECHTE))
        ->deleteJson("/api/mobile/objects/server/{$server->id}/credentials/{$link->id}")
        ->assertNoContent();

    expect($server->credentialLinks()->count())->toBe(0)
        ->and($login->fresh())->not->toBeNull();
});

test('ohne Bearbeiten-Recht am Geraet aendert sich nichts', function () {
    $server = geraetAppServer(Customer::factory()->create());

    $this->withToken(geraetAppToken(['server_viewAny', 'logingeneral_viewAny', 'logingeneral_create']))
        ->postJson("/api/mobile/objects/server/{$server->id}/credentials", ['name' => 'root'])
        ->assertForbidden();
});

test('ein Kundennutzer kommt nicht an fremde Geraete', function () {
    $server = geraetAppServer(Customer::factory()->create());

    $this->withToken(geraetAppToken(GERAET_RECHTE, Customer::factory()->create()))
        ->getJson("/api/mobile/objects/server/{$server->id}/credentials")
        ->assertNotFound();
});

// --- IP-Adressen ---------------------------------------------------------------

test('die App traegt eine Adresse ein und schlaegt die naechste freie vor', function () {
    $server = geraetAppServer(Customer::factory()->create());
    $netz = Network::factory()->create(['customer_id' => $server->customer_id, 'site_id' => $server->site_id,
        'network' => '10.20.30.0', 'cidr' => '24', 'gateway' => '10.20.30.1']);
    $token = geraetAppToken(['server_viewAny', 'server_update']);

    $this->withToken($token)->getJson("/api/mobile/objects/server/{$server->id}/ip-addresses")
        ->assertOk()
        ->assertJsonPath('netze.0.id', $netz->id)
        ->assertJsonStructure(['eintraege', 'netze' => [['id', 'name', 'naechste_freie']]]);

    $this->withToken($token)
        ->postJson("/api/mobile/objects/server/{$server->id}/ip-addresses", ['address' => '10.20.30.5', 'network_id' => $netz->id, 'label' => 'LAN'])
        ->assertCreated()
        ->assertJsonPath('0.adresse', '10.20.30.5')
        ->assertJsonPath('0.dhcp', false);
});

test('eine Adresse gibt es beim Kunden nur einmal', function () {
    $kunde = Customer::factory()->create();
    $server = geraetAppServer($kunde);
    $server->ipAddresses()->create(['customer_id' => $kunde->id, 'address' => '10.0.0.5']);

    $this->withToken(geraetAppToken(['server_viewAny', 'server_update']))
        ->postJson("/api/mobile/objects/server/{$server->id}/ip-addresses", ['address' => '10.0.0.5'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('address');
});

test('DHCP braucht ein Netz und keine Adresse', function () {
    $server = geraetAppServer(Customer::factory()->create());
    $netz = Network::factory()->create(['customer_id' => $server->customer_id, 'site_id' => $server->site_id]);
    $token = geraetAppToken(['server_viewAny', 'server_update']);

    $this->withToken($token)
        ->postJson("/api/mobile/objects/server/{$server->id}/ip-addresses", ['dhcp' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('network_id');

    $this->withToken($token)
        ->postJson("/api/mobile/objects/server/{$server->id}/ip-addresses", ['dhcp' => true, 'network_id' => $netz->id])
        ->assertCreated()
        ->assertJsonPath('0.dhcp', true);
});

test('eine Adresse laesst sich wieder entfernen', function () {
    $server = geraetAppServer(Customer::factory()->create());
    $ip = $server->ipAddresses()->create(['customer_id' => $server->customer_id, 'address' => '10.0.0.5']);

    $this->withToken(geraetAppToken(['server_viewAny', 'server_update']))
        ->deleteJson("/api/mobile/objects/server/{$server->id}/ip-addresses/{$ip->id}")
        ->assertNoContent();

    expect($server->ipAddresses()->count())->toBe(0);
});

test('ein Typ ohne Adressen hat diesen Block nicht', function () {
    $domain = Domain::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    $this->withToken(geraetAppToken(['domain_viewAny', 'domain_update']))
        ->getJson("/api/mobile/objects/domain/{$domain->id}/ip-addresses")
        ->assertNotFound();
});

// --- Scan-Ziele ----------------------------------------------------------------

test('am Scanner entsteht ein Scan-Ziel und laesst sich wieder loesen', function () {
    $kunde = Customer::factory()->create();
    $scanner = Scanner::factory()->create(['customer_id' => $kunde->id, 'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id]);
    $token = geraetAppToken(['scanner_viewAny', 'scanner_update', 'scantarget_viewAny', 'scantarget_create']);

    $this->withToken($token)
        ->postJson("/api/mobile/objects/scanner/{$scanner->id}/scan-targets", ['kind' => 'smb', 'target' => '\\\\srv-fs01\\scans'])
        ->assertCreated()
        ->assertJsonPath('0.ziel', '\\\\srv-fs01\\scans')
        ->assertJsonPath('0.art', 'smb');

    $ziel = ScanTarget::firstOrFail();

    $this->withToken($token)->getJson("/api/mobile/objects/scanner/{$scanner->id}")
        ->assertOk()->assertJsonPath('scan_ziele.0.id', $ziel->id);

    $this->withToken($token)
        ->deleteJson("/api/mobile/objects/scanner/{$scanner->id}/scan-targets/{$ziel->id}")
        ->assertNoContent();

    // Nur geloest, das Ziel selbst bleibt in der Liste der Scan-Ziele.
    expect($scanner->scanTargets()->count())->toBe(0)
        ->and($ziel->fresh())->not->toBeNull();
});

test('ein vorhandenes Scan-Ziel wird verknuepft, ein fremdes nicht', function () {
    $kunde = Customer::factory()->create();
    $scanner = Scanner::factory()->create(['customer_id' => $kunde->id, 'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id]);
    $eigenes = ScanTarget::create(['customer_id' => $kunde->id, 'kind' => 'email', 'target' => 'scan@firma.de']);
    $fremdes = ScanTarget::create(['customer_id' => Customer::factory()->create()->id, 'kind' => 'email', 'target' => 'x@fremd.de']);
    $token = geraetAppToken(['scanner_viewAny', 'scanner_update', 'scantarget_viewAny']);

    $this->withToken($token)
        ->postJson("/api/mobile/objects/scanner/{$scanner->id}/scan-targets", ['ziel_id' => $fremdes->id])
        ->assertUnprocessable();

    $this->withToken($token)
        ->postJson("/api/mobile/objects/scanner/{$scanner->id}/scan-targets", ['ziel_id' => $eigenes->id])
        ->assertCreated()
        ->assertJsonPath('0.id', $eigenes->id);
});

// --- Patchfelder ---------------------------------------------------------------

const PATCH_RECHTE = ['patchpanel_viewAny', 'patchpanel_create', 'patchpanel_update', 'patchpanel_delete'];

test('ein Patchfeld entsteht mit seinen Ports', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    $this->withToken(geraetAppToken(PATCH_RECHTE))
        ->postJson("/api/mobile/customers/{$kunde->slug}/patchpanels", [
            'site_id' => $standort->id, 'name' => 'PF-01', 'port_count' => 24, 'height_units' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'PF-01')
        ->assertJsonCount(24, 'ports')
        ->assertJsonPath('ports.0.nummer', 1);
});

test('Ports werden beschriftet, nur mit Switches des Kunden', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $panel = PatchPanel::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id, 'port_count' => 24]);
    $panel->syncPorts();
    $port = $panel->ports()->where('number', 1)->first();
    $switch = NetworkSwitch::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id]);
    $fremd = NetworkSwitch::factory()->create(['customer_id' => Customer::factory()->create()->id, 'site_id' => $standort->id]);
    $basis = "/api/mobile/customers/{$kunde->slug}/patchpanels/{$panel->id}/ports";
    $token = geraetAppToken(PATCH_RECHTE);

    $this->withToken($token)
        ->putJson($basis, ['ports' => [['id' => $port->id, 'network_switch_id' => $fremd->id]]])
        ->assertUnprocessable();

    $this->withToken($token)
        ->putJson($basis, ['ports' => [['id' => $port->id, 'outlet' => '1.01', 'label' => 'EG Empfang', 'network_switch_id' => $switch->id, 'switch_port' => '7']]])
        ->assertOk()
        ->assertJsonPath('ports.0.dose', '1.01')
        ->assertJsonPath('ports.0.raum', 'EG Empfang')
        ->assertJsonPath('ports.0.switch.id', $switch->id);

    // Nur was geschickt wird: Die Dose bleibt, der Raum wird geleert.
    $this->withToken($token)
        ->putJson($basis, ['ports' => [['id' => $port->id, 'label' => '']]])
        ->assertOk()
        ->assertJsonPath('ports.0.dose', '1.01')
        ->assertJsonPath('ports.0.raum', null);
});

test('ein Patchfeld wird nicht kleiner, solange oben etwas dokumentiert ist', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $panel = PatchPanel::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id, 'port_count' => 24, 'height_units' => 1]);
    $panel->syncPorts();
    $panel->ports()->where('number', 20)->update(['outlet' => '1.20']);

    $this->withToken(geraetAppToken(PATCH_RECHTE))
        ->putJson("/api/mobile/customers/{$kunde->slug}/patchpanels/{$panel->id}", [
            'site_id' => $standort->id, 'name' => $panel->name, 'port_count' => 12, 'height_units' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('port_count');

    expect($panel->fresh()->port_count)->toBe(24);
});

test('ein fremdes Patchfeld ist nicht zu finden', function () {
    $kunde = Customer::factory()->create();
    $anderer = Customer::factory()->create();
    $panel = PatchPanel::factory()->create(['customer_id' => $anderer->id, 'site_id' => Site::factory()->create(['customer_id' => $anderer->id])->id]);

    $this->withToken(geraetAppToken(PATCH_RECHTE))
        ->deleteJson("/api/mobile/customers/{$kunde->slug}/patchpanels/{$panel->id}")
        ->assertNotFound();

    expect($panel->fresh())->not->toBeNull();
});

test('ein Patchfeld landet geloescht im Papierkorb', function () {
    $kunde = Customer::factory()->create();
    $panel = PatchPanel::factory()->create(['customer_id' => $kunde->id, 'site_id' => Site::factory()->create(['customer_id' => $kunde->id])->id]);

    $this->withToken(geraetAppToken(PATCH_RECHTE))
        ->deleteJson("/api/mobile/customers/{$kunde->slug}/patchpanels/{$panel->id}")
        ->assertNoContent();

    expect(PatchPanel::find($panel->id))->toBeNull()
        ->and(PatchPanel::withTrashed()->find($panel->id))->not->toBeNull();
});
