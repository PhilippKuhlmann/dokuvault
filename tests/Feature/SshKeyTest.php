<?php

use App\Livewire\DeviceCredentials;
use App\Livewire\ObjektFormular;
use App\Livewire\ObjektListe;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use Livewire\Livewire;

/**
 * SSH-Schluessel liegen in derselben Tabelle wie die Logins.
 *
 * Getrennt sind sie ueber 'kind', und zwar in beide Richtungen: Ein Schluessel
 * darf nicht in der Login-Liste auftauchen und ein Kennwort nicht in der
 * Schluesselliste. Die Verknuepfung zu Geraeten muss dagegen beide finden.
 */
function sshUmgebung(): array
{
    $customer = Customer::factory()->create();

    $schluessel = SshKey::create([
        'customer_id' => $customer->id,
        'name' => 'Admin ed25519',
        'username' => 'root',
        'key_type' => 'ed25519',
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExample admin@buero',
        'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nbeispiel\n-----END OPENSSH PRIVATE KEY-----",
        'password' => 'passphrase123',
    ]);

    return [$customer, $schluessel];
}

test('ein neu angelegter Schluessel bekommt seine Art selbst', function () {
    [$customer, $schluessel] = sshUmgebung();

    // Ohne den creating-Haken entstuende ein Eintrag mit kind=password -
    // und waere durch den eigenen Filter sofort wieder unsichtbar.
    expect($schluessel->fresh()->kind)->toBe('sshkey');
    expect(SshKey::where('customer_id', $customer->id)->count())->toBe(1);
});

test('die beiden Listen sehen einander nicht', function () {
    [$customer, $schluessel] = sshUmgebung();

    LoginGeneral::create([
        'customer_id' => $customer->id, 'name' => 'DATEV',
        'username' => 'buchhaltung', 'password' => 'geheim123',
    ]);

    expect(LoginGeneral::where('customer_id', $customer->id)->pluck('name')->all())->toBe(['DATEV']);
    expect(SshKey::where('customer_id', $customer->id)->pluck('name')->all())->toBe(['Admin ed25519']);
});

test('der private Schluessel steht verschluesselt in der Datenbank, der oeffentliche nicht', function () {
    [$customer, $schluessel] = sshUmgebung();

    $roh = $schluessel->fresh()->getRawOriginal('private_key');

    expect(str_contains($roh, 'BEGIN OPENSSH'))
        ->toBeFalse('Der private Schluessel darf nicht im Klartext in der Datenbank stehen.');
    expect($schluessel->fresh()->private_key)->toContain('BEGIN OPENSSH PRIVATE KEY');

    // Der oeffentliche bleibt lesbar: Er ist zum Verteilen da und muss
    // durchsuchbar sein, sonst findet man ihn in keiner authorized_keys wieder.
    expect($schluessel->fresh()->getRawOriginal('public_key'))->toContain('ssh-ed25519');
});

test('ein Schluessel laesst sich an mehrere Server haengen', function () {
    $this->actingAs(userWithPermissions(['server_update', 'logingeneral_viewAny']));
    [$customer, $schluessel] = sshUmgebung();

    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    foreach (['SRV-01', 'SRV-02'] as $name) {
        $server = Server::create([
            'customer_id' => $customer->id, 'site_id' => $site->id,
            'name' => $name, 'operating_system_id' => $os->id,
        ]);

        Livewire::test(DeviceCredentials::class, ['model' => $server, 'customer' => $customer])
            ->set('login_id', $schluessel->id)
            ->call('attach');
    }

    // Der Punkt: Ein Schluessel wird einmal dokumentiert und mehrfach verknuepft.
    expect($schluessel->fresh()->verwendetBei())->toBe('SRV-01 (Server), SRV-02 (Server)');
});

test('ein am Server haengender Schluessel bleibt dort sichtbar', function () {
    $this->actingAs(userWithPermissions(['server_update', 'logingeneral_viewAny']));
    [$customer, $schluessel] = sshUmgebung();

    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $server = Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-01',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Debian 13'])->id,
    ]);
    $server->credentialLinks()->create([
        'customer_id' => $customer->id, 'login_general_id' => $schluessel->id,
    ]);

    // Ohne withoutGlobalScopes an CredentialLink::login faende die Verknuepfung
    // den Schluessel nicht - der Server stuende dann ohne Zugangsdaten da.
    expect($server->fresh()->zugangsdaten())->toHaveCount(1);
    expect($server->fresh()->zugangsdaten()->first()->login->name)->toBe('Admin ed25519');
});

test('die Liste zeigt Name, Benutzer und Verfahren, aber nicht den privaten Schluessel', function () {
    $this->actingAs(userWithPermissions(['sshkey_viewAny', 'sshkey_update']));
    [$customer, $schluessel] = sshUmgebung();

    $html = Livewire::test(ObjektListe::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->assertSee('Admin ed25519')
        ->assertSee('Ed25519')
        ->html();

    expect(str_contains($html, 'BEGIN OPENSSH PRIVATE KEY'))
        ->toBeFalse('Der private Schluessel gehoert nicht in die Liste.');
});

test('ein Schluessel laesst sich im Modal anlegen', function () {
    $this->actingAs(userWithPermissions(['sshkey_create', 'sshkey_viewAny']));
    $customer = Customer::factory()->create();

    Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('neu', 'sshkey')
        ->set('form.name', 'Deploy ed25519')
        ->set('form.username', 'deploy')
        ->set('form.key_type', 'ed25519')
        ->set('form.public_key', 'ssh-ed25519 AAAAC3Nza deploy@ci')
        ->set('form.private_key', '-----BEGIN OPENSSH PRIVATE KEY-----')
        ->call('speichern')
        ->assertHasNoErrors();

    $schluessel = SshKey::where('customer_id', $customer->id)->firstOrFail();
    expect($schluessel->name)->toBe('Deploy ed25519');
    expect($schluessel->key_type)->toBe('ed25519');
    // Und er landet nicht versehentlich in der Login-Liste.
    expect(LoginGeneral::where('customer_id', $customer->id)->count())->toBe(0);
});

test('ein unbekanntes Verfahren wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['sshkey_create', 'sshkey_viewAny']));
    $customer = Customer::factory()->create();

    Livewire::test(ObjektFormular::class, ['typ' => 'sshkey', 'customer' => $customer])
        ->call('neu', 'sshkey')
        ->set('form.name', 'Krumm')
        ->set('form.key_type', 'dsa')
        ->call('speichern')
        ->assertHasErrors('form.key_type');
});

test('ohne Recht bleibt die Liste verschlossen', function () {
    $this->actingAs(userWithPermissions(['logingeneral_viewAny']));
    $customer = Customer::factory()->create();

    $this->get(route('sshkey.index', $customer))->assertForbidden();
});
