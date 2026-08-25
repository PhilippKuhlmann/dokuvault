<?php

use App\Livewire\DeviceCredentials;
use App\Livewire\ObjektListe;
use App\Models\Customer;
use App\Models\FTPServer;
use App\Models\LoginGeneral;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * Ein FTP-Server fuehrt seine Zugaenge nicht selbst.
 *
 * Frueher hingen genau ein Benutzername und ein Kennwort als Spalten am Server.
 * Wer einen zweiten Zugang dokumentieren wollte, legte den Server ein zweites
 * Mal an. Jetzt gilt derselbe Mechanismus wie bei Server, VM oder NAS:
 * Eintraege aus "Logins Allgemein", per credential_links verknuepft.
 */
function ftpUmgebung(): array
{
    $customer = Customer::factory()->create();
    $server = FTPServer::create([
        'customer_id' => $customer->id,
        'host' => 'ftp.beispiel.de',
        'description' => 'Datenaustausch Steuerberater',
    ]);

    return [$customer, $server];
}

test('der Server fuehrt keine eigenen Zugangsdaten mehr als Spalten', function () {
    expect(Schema::hasColumn('ftp_servers', 'username'))->toBeFalse();
    expect(Schema::hasColumn('ftp_servers', 'password'))->toBeFalse();
});

test('ein FTP-Server nimmt mehrere Zugaenge auf', function () {
    $this->actingAs(userWithPermissions(['ftpserver_update', 'logingeneral_viewAny', 'logingeneral_create']));
    [$customer, $server] = ftpUmgebung();

    foreach (['ftp-steuerberater', 'ftp-backup', 'ftp-lieferant'] as $benutzer) {
        Livewire::test(DeviceCredentials::class, ['model' => $server, 'customer' => $customer])
            ->set('name', 'FTP '.$benutzer)
            ->set('username', $benutzer)
            ->set('password', 'geheim123')
            ->call('create');
    }

    expect($server->fresh()->zugangsdaten())->toHaveCount(3);
});

test('dasselbe Konto haengt an zwei FTP-Servern', function () {
    $this->actingAs(userWithPermissions(['ftpserver_update', 'logingeneral_viewAny']));
    [$customer, $server] = ftpUmgebung();
    $zweiter = FTPServer::create(['customer_id' => $customer->id, 'host' => 'ftp2.beispiel.de']);

    $login = LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'FTP Backup extern',
        'username' => 'backup', 'password' => 'geheim123',
    ]);

    foreach ([$server, $zweiter] as $ziel) {
        Livewire::test(DeviceCredentials::class, ['model' => $ziel, 'customer' => $customer])
            ->set('login_id', $login->id)
            ->call('attach');
    }

    // Der Punkt der Umstellung: ein Kennwort, zwei Server, eine Stelle zum Aendern.
    expect($login->links()->count())->toBe(2);
    expect($login->fresh()->verwendetBei())->toBe('ftp.beispiel.de (FTP-Server), ftp2.beispiel.de (FTP-Server)');
});

test('der Server erscheint unter "Verwendet bei" mit seinem Host, nicht mit einer Nummer', function () {
    $this->actingAs(userWithPermissions(['ftpserver_update', 'logingeneral_viewAny']));
    [$customer, $server] = ftpUmgebung();

    $login = LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'FTP Deploy',
        'username' => 'deploy', 'password' => 'geheim123',
    ]);

    $server->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $login->id,
    ]);

    // Ein FTP-Server hat keine name-Spalte; ohne den host-Zweig in
    // CredentialLink::zielBezeichnung stuende hier "#1 (FTP-Server)".
    expect($login->fresh()->verwendetBei())->toBe('ftp.beispiel.de (FTP-Server)');
});

test('ein Zugang eines fremden Kunden laesst sich nicht anhaengen', function () {
    $this->actingAs(userWithPermissions(['ftpserver_update', 'logingeneral_viewAny']));
    [$customer, $server] = ftpUmgebung();

    $fremd = Customer::factory()->create();
    $fremderLogin = LoginGeneral::create([
        'customer_id' => $fremd->id, 'name' => 'Fremd',
        'username' => 'fremd', 'password' => 'geheim123',
    ]);

    Livewire::test(DeviceCredentials::class, ['model' => $server, 'customer' => $customer])
        ->set('login_id', $fremderLogin->id)
        ->call('attach')
        ->assertHasErrors('login_id');

    expect($server->fresh()->credentialLinks()->count())->toBe(0);
});

test('die Liste zeigt die Benutzernamen des Servers und sonst "kein Zugang"', function () {
    $this->actingAs(userWithPermissions(['ftpserver_viewAny', 'ftpserver_update', 'logingeneral_viewAny']));
    [$customer, $server] = ftpUmgebung();
    FTPServer::create(['customer_id' => $customer->id, 'host' => 'ftp-ohne.beispiel.de']);

    $login = LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'FTP Steuerberater',
        'username' => 'ftp-steuerberater', 'password' => 'geheim123',
    ]);
    $server->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $login->id,
    ]);

    Livewire::test(ObjektListe::class, ['typ' => 'ftpserver', 'customer' => $customer])
        ->assertSee('ftp-steuerberater')
        ->assertSee('kein Zugang');
});

test('das Kennwort steht verschluesselt in der Datenbank', function () {
    $this->actingAs(userWithPermissions(['ftpserver_update', 'logingeneral_viewAny', 'logingeneral_create']));
    [$customer, $server] = ftpUmgebung();

    Livewire::test(DeviceCredentials::class, ['model' => $server, 'customer' => $customer])
        ->set('name', 'FTP Steuerberater')
        ->set('username', 'ftp-steuerberater')
        ->set('password', 'streng-geheim-42')
        ->call('create');

    $login = LoginGeneral::where('customer_id', $customer->id)->firstOrFail();

    expect(str_contains($login->getRawOriginal('password'), 'streng-geheim-42'))
        ->toBeFalse('Das Kennwort darf nicht im Klartext in der Datenbank stehen.');
    expect($login->password)->toBe('streng-geheim-42');
});
