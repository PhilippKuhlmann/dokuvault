<?php

use App\Models\Checklist;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use App\Support\Adresse;
use App\Support\BildTyp;
use App\Support\ChecklistAdScript;
use App\Support\CredentialLinking;
use Laravel\Sanctum\PersonalAccessToken;

// Security audit, small hardening notes.

test('Adresse::pfad rejects tabs and line breaks that browsers drop from a URL', function () {
    expect(Adresse::pfad("/\t/example.test"))->toBeNull()
        ->and(Adresse::pfad("/\n/example.test"))->toBeNull()
        ->and(Adresse::pfad('/kunden/1'))->toBe('/kunden/1');
});

test('BildTyp serves only a fixed list of image types', function () {
    expect(BildTyp::fuer('logos/a.PNG'))->toBe('image/png')
        ->and(BildTyp::fuer('logos/a.jpg'))->toBe('image/jpeg')
        ->and(BildTyp::fuer('logos/a.svg'))->toBe('application/octet-stream')
        ->and(BildTyp::fuer('logos/a'))->toBe('application/octet-stream');
});

test('the checklist script file name contains only plain characters', function () {
    $checklist = new Checklist(['type' => 'onboarding', 'username' => "x\"\r\nSet-Cookie: a=b"]);

    expect(ChecklistAdScript::filename($checklist))->toMatch('/^[A-Za-z0-9._-]+$/');
});

test('the PDF retention is capped on read', function () {
    Setting::setzen(Setting::PDF_STUNDEN, 999999);

    expect(Setting::pdfStunden())->toBe(8760);
});

test('deleting a user removes their API tokens', function () {
    $nutzer = User::factory()->create();
    $nutzer->createToken('x');

    $nutzer->delete();

    expect(PersonalAccessToken::where('tokenable_id', $nutzer->id)->count())->toBe(0);
});

test('every hand script that uploads checks for https first', function () {
    foreach (glob(resource_path('agents/*.{sh,ps1}'), GLOB_BRACE) as $datei) {
        $inhalt = file_get_contents($datei);

        if (! str_contains($inhalt, 'Sende Dokumentation') && ! str_contains($inhalt, 'vmware')) {
            continue;
        }

        expect($inhalt)->toContain('Unsichere Adresse');
    }
});

test('the remote search shows only device kinds the role may see', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    Server::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id, 'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id, 'name' => 'SRV-GEHEIM', 'remoteID' => '123', 'remotePassword' => 'x']);

    // remote_search alone: no server_viewAny.
    $this->actingAs(userWithPermissions(['remote_search']));
    $this->get(route('search.remote', ['search' => 'SRV']))->assertOk()->assertDontSee('SRV-GEHEIM');

    auth()->logout();
    $this->flushSession();
    $this->actingAs(userWithPermissions(['remote_search', 'server_viewAny']));
    $this->get(route('search.remote', ['search' => 'SRV']))->assertOk()->assertSee('SRV-GEHEIM');
});

test('detaching a credential link respects the kind the user may see', function () {
    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);
    $server = Server::factory()->create(['customer_id' => $kunde->id, 'site_id' => $standort->id, 'operating_system_id' => OperatingSystem::firstOrCreate(['name' => 'Debian 13'])->id]);
    $schluessel = SshKey::create([
        'customer_id' => $kunde->id, 'name' => 'Admin ed25519', 'username' => 'root',
        'key_type' => 'ed25519', 'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExample admin@buero',
    ]);
    $link = $server->credentialLinks()->create(['customer_id' => $kunde->id, 'login_general_id' => $schluessel->id]);

    $ohneSchluesselRecht = userWithPermissions(['logingeneral_viewAny']);
    app(CredentialLinking::class)->detach($server, $link->id, $ohneSchluesselRecht);
    expect($server->credentialLinks()->count())->toBe(1);

    $mitRecht = userWithPermissions(['logingeneral_viewAny', 'sshkey_viewAny']);
    app(CredentialLinking::class)->detach($server, $link->id, $mitRecht);
    expect($server->credentialLinks()->count())->toBe(0);
});
