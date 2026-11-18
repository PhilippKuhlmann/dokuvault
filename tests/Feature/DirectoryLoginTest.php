<?php

use App\Models\DirectoryGroupRole;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Directory\DirectoryClient;
use App\Support\Directory\DirectoryUnavailable;
use App\Support\Directory\DirectoryUser;
use App\Support\Directory\LdapConfig;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/*
 * Signing in through an on-premises Active Directory next to local users.
 * A fake stands in for the domain controller: right password "richtig",
 * anything else is wrong.
 */

const AD_GUID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

function adEinschalten(): void
{
    Setting::setzen(LdapConfig::ENABLED, '1');
    Setting::setzen(LdapConfig::HOST, 'dc01.firma.local');
    Setting::setzen(LdapConfig::BASE_DN, 'DC=firma,DC=local');
    Setting::setzen(LdapConfig::DOMAIN, 'firma');
}

/** @param  array<int, string>  $gruppen  sAMAccountNames */
function adKonto(array $gruppen = ['DokuVault-Techniker'], string $guid = AD_GUID, string $sam = 'pkuhlmann'): DirectoryUser
{
    return new DirectoryUser(
        guid: $guid,
        username: $sam,
        upn: $sam.'@firma.de',
        name: 'Philipp Kuhlmann',
        email: $sam.'@firma.de',
        groups: array_map(fn ($g) => ['dn' => 'CN='.$g.',OU=Gruppen,DC=firma,DC=local', 'name' => $g, 'cn' => $g], $gruppen),
    );
}

function adAttrappe(?DirectoryUser $konto = null, bool $weg = false): object
{
    $attrappe = new class implements DirectoryClient
    {
        public ?DirectoryUser $konto = null;

        public bool $weg = false;

        /** @var array<int, string> */
        public array $gefragt = [];

        public function authenticate(string $login, string $password): ?DirectoryUser
        {
            $this->gefragt[] = $login;

            if ($this->weg) {
                throw new DirectoryUnavailable("Can't contact LDAP server");
            }

            return $password === 'richtig' ? $this->konto : null;
        }

        public function test(): string
        {
            if ($this->weg) {
                throw new DirectoryUnavailable("Can't contact LDAP server");
            }

            return 'Verbindung steht.';
        }

        public function find(string $guid): ?DirectoryUser
        {
            if ($this->weg) {
                throw new DirectoryUnavailable("Can't contact LDAP server");
            }

            return $this->konto?->guid === $guid ? $this->konto : null;
        }

        public function groups(): array
        {
            if ($this->weg) {
                throw new DirectoryUnavailable("Can't contact LDAP server");
            }

            return [
                ['dn' => 'CN=DokuVault-Admins,OU=Gruppen,DC=firma,DC=local', 'name' => 'DokuVault-Admins'],
                ['dn' => 'CN=DokuVault-Techniker,OU=Gruppen,DC=firma,DC=local', 'name' => 'DokuVault-Techniker'],
            ];
        }
    };

    $attrappe->konto = $konto;
    $attrappe->weg = $weg;
    app()->instance(DirectoryClient::class, $attrappe);

    return $attrappe;
}

function adRollen(): array
{
    $admin = Role::firstOrCreate(['id' => Role::IS_ADMIN], ['name' => 'Admin']);
    $techniker = Role::factory()->create(['name' => 'Techniker AD']);

    // Higher role first: someone in both groups becomes an admin.
    DirectoryGroupRole::create(['source' => 'ldap', 'group' => 'DokuVault-Admins', 'role_id' => $admin->id, 'position' => 1]);
    DirectoryGroupRole::create(['source' => 'ldap', 'group' => 'DokuVault-Techniker', 'role_id' => $techniker->id, 'position' => 2]);

    return [$admin, $techniker];
}

test('a technician signs in with the directory and gets the role of their group', function () {
    adEinschalten();
    [, $techniker] = adRollen();
    adAttrappe(adKonto());

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])->assertSessionHasNoErrors();

    $nutzer = User::where('auth_source', 'ldap')->sole();
    $this->assertAuthenticatedAs($nutzer);
    expect($nutzer->username)->toBe('FIRMA\pkuhlmann')
        ->and($nutzer->name)->toBe('Philipp Kuhlmann')
        ->and($nutzer->email)->toBe('pkuhlmann@firma.de')
        ->and($nutzer->external_id)->toBe(AD_GUID)
        ->and($nutzer->role_id)->toBe($techniker->id)
        ->and($nutzer->customer_id)->toBeNull()
        // The directory checks only the password: DokuVault's second factor on top.
        ->and($nutzer->two_factor_required)->toBeTrue();
});

test('all three spellings of the username reach the directory', function (string $eingabe) {
    adEinschalten();
    adRollen();
    $attrappe = adAttrappe(adKonto());

    $this->post('/login', ['username' => $eingabe, 'password' => 'richtig'])->assertSessionHasNoErrors();

    expect($attrappe->gefragt)->toBe([$eingabe]);
    $this->assertAuthenticated();
})->with(['pkuhlmann', 'FIRMA\pkuhlmann', 'pkuhlmann@firma.de']);

test('in two groups the higher role wins', function () {
    adEinschalten();
    [$admin] = adRollen();
    adAttrappe(adKonto(['DokuVault-Techniker', 'DokuVault-Admins']));

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig']);

    expect(User::where('auth_source', 'ldap')->sole()->role_id)->toBe($admin->id);
});

test('without a mapped group there is no access and no account', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto(['Domain Users']));

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])
        ->assertSessionHasErrors(['username' => __('Ihr Konto ist in keiner Gruppe, die DokuVault freigibt. Wenden Sie sich an Ihre Administration.')]);

    $this->assertGuest();
    expect(User::where('auth_source', 'ldap')->exists())->toBeFalse();
});

test('a wrong password is an ordinary failed sign-in', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto());

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'falsch'])
        ->assertSessionHasErrors(['username' => trans('auth.failed')]);

    $this->assertGuest();
});

test('a local account of the same name signs in locally, the directory is not asked', function () {
    adEinschalten();
    adRollen();
    $attrappe = adAttrappe(adKonto());
    $lokal = User::factory()->create(['username' => 'pkuhlmann', 'password' => Hash::make('lokal-geheim')]);

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'lokal-geheim'])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($lokal);
    expect($attrappe->gefragt)->toBe([]);
});

test('with DOMAIN\\name the technician reaches the directory despite a local namesake', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto());
    $lokal = User::factory()->create(['username' => 'pkuhlmann']);

    $this->post('/login', ['username' => 'FIRMA\pkuhlmann', 'password' => 'richtig'])->assertSessionHasNoErrors();

    $nutzer = User::where('auth_source', 'ldap')->sole();
    $this->assertAuthenticatedAs($nutzer);
    // Kept apart: the local account is untouched.
    expect($nutzer->id)->not->toBe($lokal->id)
        ->and($lokal->fresh()->auth_source)->toBe('local');
});

test('the role follows the groups on every sign-in, a renamed account stays the same user', function () {
    adEinschalten();
    [, $techniker] = adRollen();
    adAttrappe(adKonto(['DokuVault-Admins']));
    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig']);
    $id = User::where('auth_source', 'ldap')->sole()->id;
    auth()->logout();

    // Demoted and renamed in the directory - same objectGUID.
    adAttrappe(adKonto(['DokuVault-Techniker'], AD_GUID, 'p.kuhlmann'));
    $this->post('/login', ['username' => 'p.kuhlmann', 'password' => 'richtig']);

    $nutzer = User::where('auth_source', 'ldap')->sole();
    expect($nutzer->id)->toBe($id)
        ->and($nutzer->role_id)->toBe($techniker->id)
        ->and($nutzer->username)->toBe('FIRMA\p.kuhlmann');
});

test('a directory out of reach says so, and the local admin still gets in', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto(), weg: true);
    $admin = User::factory()->create(['username' => 'admin', 'password' => Hash::make('notfall')]);

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();

    $this->post('/login', ['username' => 'admin', 'password' => 'notfall'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($admin);
});

test('a directory account never signs in with a local password, not even with the directory off', function () {
    $ad = User::factory()->create([
        'username' => 'FIRMA\pkuhlmann', 'auth_source' => 'ldap', 'external_id' => AD_GUID,
        'password' => Hash::make('irgendwie-gesetzt'),
    ]);

    // Directory switched off: only local accounts may sign in locally.
    $this->post('/login', ['username' => 'FIRMA\pkuhlmann', 'password' => 'irgendwie-gesetzt'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
    expect($ad->fresh()->last_login_at)->toBeNull();
});

test('a directory account locked in DokuVault stays out', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto());
    User::factory()->create(['username' => 'FIRMA\pkuhlmann', 'auth_source' => 'ldap', 'external_id' => AD_GUID, 'deactivated_at' => now()]);

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])
        ->assertSessionHasErrors(['username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.')]);
    $this->assertGuest();
});

test('the app sign-in goes through the directory too and asks for the second factor first', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto());

    // New directory account: second factor required, not set up yet.
    $this->postJson('/api/mobile/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])
        ->assertStatus(403)
        ->assertJsonPath('message', __('Ihr Administrator verlangt die zweite Stufe. Richten Sie sie zuerst im Browser in Ihrem Profil ein.'));

    $this->postJson('/api/mobile/login', ['username' => 'pkuhlmann', 'password' => 'falsch'])
        ->assertStatus(422);
});

test('no password reset for a directory account', function () {
    Notification::fake();
    $ad = User::factory()->create(['username' => 'FIRMA\pkuhlmann', 'email' => 'pk@firma.de', 'auth_source' => 'ldap', 'external_id' => AD_GUID]);

    $this->post('/forgot-password', ['username' => 'FIRMA\pkuhlmann']);

    Notification::assertNothingSentTo($ad);
});

test('the settings page saves the connection, the password encrypted and kept when left empty', function () {
    $this->actingAs(adminUser());

    $this->patch(route('admin.directory.update'), [
        'enabled' => '1', 'host' => 'dc01.firma.local', 'encryption' => 'ldaps', 'verify_cert' => '1',
        'base_dn' => 'DC=firma,DC=local', 'bind_dn' => 'CN=svc,DC=firma,DC=local', 'bind_password' => 'Dienst!2026', 'domain' => 'FIRMA',
    ])->assertRedirect(route('admin.directory.index'))->assertSessionHasNoErrors();

    expect(LdapConfig::enabled())->toBeTrue()
        ->and(LdapConfig::bindPassword())->toBe('Dienst!2026')
        ->and(Setting::wert(LdapConfig::BIND_PASSWORD))->not->toBe('Dienst!2026');

    // Saved again without a password: it stays.
    $this->patch(route('admin.directory.update'), [
        'enabled' => '1', 'host' => 'dc02.firma.local', 'encryption' => 'starttls',
        'base_dn' => 'DC=firma,DC=local', 'bind_dn' => 'CN=svc,DC=firma,DC=local', 'bind_password' => '',
    ])->assertSessionHasNoErrors();
    expect(LdapConfig::bindPassword())->toBe('Dienst!2026')
        ->and(LdapConfig::host())->toBe('dc02.firma.local')
        ->and(LdapConfig::verifyCert())->toBeFalse();

    $this->get(route('admin.directory.index'))->assertOk()
        ->assertSee('dc02.firma.local')
        ->assertDontSee('Dienst!2026');
});

test('without encryption is possible, warned about on the page and logged', function () {
    $this->actingAs(adminUser());
    $felder = ['enabled' => '1', 'host' => 'dc01.firma.local', 'base_dn' => 'DC=firma,DC=local', 'bind_dn' => 'CN=svc,DC=firma,DC=local'];

    $this->patch(route('admin.directory.update'), $felder + ['encryption' => 'none'])->assertSessionHasNoErrors();

    expect(LdapConfig::encryption())->toBe('none')
        ->and(LdapConfig::unencrypted())->toBeTrue()
        // Plain LDAP listens on 389.
        ->and(LdapConfig::port())->toBe(389);

    $this->get(route('admin.directory.index'))->assertOk()
        ->assertSee('Die AD-Anmeldung läuft ohne Verschlüsselung.')
        ->assertSee('<option value="none" selected', false);

    // Back to encrypted: the warning goes, both steps stand in the log.
    $this->patch(route('admin.directory.update'), $felder + ['encryption' => 'ldaps'])->assertSessionHasNoErrors();
    $this->get(route('admin.directory.index'))->assertDontSee('Die AD-Anmeldung läuft ohne Verschlüsselung.');

    expect(Activity::where('event', 'einstellung')->pluck('description')->all())
        ->toBe(['AD-Anmeldung ohne Verschlüsselung eingestellt', 'AD-Anmeldung wieder verschlüsselt']);
});

test('switched on, server and base DN are required', function () {
    $this->actingAs(adminUser());

    $this->patch(route('admin.directory.update'), ['enabled' => '1', 'encryption' => 'ldaps'])
        ->assertSessionHasErrors(['host', 'base_dn', 'bind_dn']);
});

test('the connection test reports success or the reason it failed', function () {
    $this->actingAs(adminUser());

    adAttrappe(adKonto());
    $this->post(route('admin.directory.test'))->assertSessionHas('success', 'Verbindung steht.');

    adAttrappe(adKonto(), weg: true);
    $this->post(route('admin.directory.test'))->assertSessionHasErrors('verbindung');
});

test('the test sign-in shows groups and role and creates no user', function () {
    adEinschalten();
    adRollen();
    adAttrappe(adKonto(['DokuVault-Techniker', 'Domain Users']));
    $this->actingAs(adminUser());

    $this->post(route('admin.directory.test-user'), ['test_username' => 'pkuhlmann', 'test_password' => 'richtig'])
        ->assertSessionHas('ad_test', fn ($e) => $e['rolle'] === 'Techniker AD'
            && $e['username'] === 'FIRMA\pkuhlmann'
            && $e['gruppen'] === ['DokuVault-Techniker', 'Domain Users']);

    expect(User::where('auth_source', 'ldap')->exists())->toBeFalse();

    $this->post(route('admin.directory.test-user'), ['test_username' => 'pkuhlmann', 'test_password' => 'falsch'])
        ->assertSessionHasErrors('test_username');
});

test('groups are mapped, reordered and removed', function () {
    $this->actingAs(adminUser());
    $lesen = Role::factory()->create(['name' => 'Lesen']);
    $technik = Role::factory()->create(['name' => 'Technik']);

    $this->post(route('admin.directory.groups.store'), ['group' => 'DV-Lesen', 'role_id' => $lesen->id])->assertSessionHasNoErrors();
    $this->post(route('admin.directory.groups.store'), ['group' => 'DV-Technik', 'role_id' => $technik->id]);

    $reihenfolge = fn () => DirectoryGroupRole::orderBy('position')->pluck('group')->all();
    expect($reihenfolge())->toBe(['DV-Lesen', 'DV-Technik']);

    // The higher role up: it now wins for someone in both groups.
    $technikZeile = DirectoryGroupRole::where('group', 'DV-Technik')->sole();
    $this->post(route('admin.directory.groups.move', [$technikZeile, 'up']));
    expect($reihenfolge())->toBe(['DV-Technik', 'DV-Lesen'])
        ->and(DirectoryGroupRole::roleFor('ldap', [['dn' => 'x', 'name' => 'DV-Lesen', 'cn' => null], ['dn' => 'y', 'name' => 'dv-technik', 'cn' => null]])->id)->toBe($technik->id);

    $this->delete(route('admin.directory.groups.destroy', $technikZeile));
    expect($reihenfolge())->toBe(['DV-Lesen']);
});

test('in the user admin a directory account can be locked, but not given another role or password', function () {
    [, $techniker] = adRollen();
    $andere = Role::factory()->create(['name' => 'Andere']);
    $ad = User::factory()->create([
        'username' => 'FIRMA\pkuhlmann', 'auth_source' => 'ldap', 'external_id' => AD_GUID, 'role_id' => $techniker->id,
    ]);
    $hash = $ad->password;
    $this->actingAs(adminUser());

    $this->get(route('admin.user.edit', $ad))->assertOk()->assertSee('Dieses Konto kommt aus dem Active Directory.');

    $this->patch(route('admin.user.update', $ad), [
        'name' => 'Umbenannt', 'username' => 'neu', 'email' => 'neu@firma.de',
        'role_id' => $andere->id, 'password' => 'Neues!Kennwort2026', 'deactivated' => '1',
    ]);

    $ad->refresh();
    expect($ad->isDisabled())->toBeTrue()
        ->and($ad->role_id)->toBe($techniker->id)
        ->and($ad->username)->toBe('FIRMA\pkuhlmann')
        ->and($ad->password)->toBe($hash);
});

test('a directory account gets no invitation', function () {
    Notification::fake();
    $ad = User::factory()->create(['username' => 'FIRMA\pkuhlmann', 'email' => 'pk@firma.de', 'auth_source' => 'ldap', 'external_id' => AD_GUID]);
    $this->actingAs(adminUser());

    $this->post(route('admin.user.invite', $ad))->assertSessionHasErrors('invitation');

    Notification::assertNothingSentTo($ad);
    expect($ad->fresh()->invited_at)->toBeNull();
});

function adVerbindungEintragen(): void
{
    adEinschalten();
    Setting::setzen(LdapConfig::BIND_DN, 'CN=svc,DC=firma,DC=local');
    LdapConfig::setBindPassword('Dienst!2026');
}

test('once connected the groups are picked from a list, stored by DN and shown by name', function () {
    adVerbindungEintragen();
    adAttrappe(adKonto());
    $techniker = Role::factory()->create(['name' => 'Technik']);
    $this->actingAs(adminUser());

    $this->get(route('admin.directory.index'))->assertOk()
        ->assertSee('<option value="CN=DokuVault-Techniker,OU=Gruppen,DC=firma,DC=local"', false)
        ->assertSee('Gruppe nicht in der Liste? Von Hand eingeben');

    $this->post(route('admin.directory.groups.store'), [
        'group' => 'CN=DokuVault-Techniker,OU=Gruppen,DC=firma,DC=local', 'role_id' => $techniker->id,
    ])->assertSessionHasNoErrors();

    expect(DirectoryGroupRole::sole()->group)->toBe('CN=DokuVault-Techniker,OU=Gruppen,DC=firma,DC=local');

    // The list shows the name; a technician in that group gets the role.
    $this->get(route('admin.directory.index'))->assertSeeInOrder(['1.', 'DokuVault-Techniker', 'Technik']);
    expect(DirectoryGroupRole::roleFor('ldap', adKonto()->groups)->id)->toBe($techniker->id);
});

test('a name typed by hand wins over the picked group', function () {
    adVerbindungEintragen();
    adAttrappe(adKonto());
    $rolle = Role::factory()->create();
    $this->actingAs(adminUser());

    $this->post(route('admin.directory.groups.store'), [
        'group' => 'CN=DokuVault-Admins,OU=Gruppen,DC=firma,DC=local', 'group_manual' => 'Sonder-Gruppe', 'role_id' => $rolle->id,
    ])->assertSessionHasNoErrors();

    expect(DirectoryGroupRole::sole()->group)->toBe('Sonder-Gruppe');
});

test('with the directory out of reach the text field stays and says why', function () {
    adVerbindungEintragen();
    adAttrappe(adKonto(), weg: true);
    $this->actingAs(adminUser());

    $this->get(route('admin.directory.index'))->assertOk()
        ->assertSee('Die Gruppen ließen sich nicht laden')
        ->assertDontSee('<option value="CN=', false);
});

function adNutzer(Role $rolle, array $mehr = []): User
{
    return User::factory()->create($mehr + [
        'username' => 'FIRMA\pkuhlmann', 'auth_source' => 'ldap', 'external_id' => AD_GUID, 'role_id' => $rolle->id,
    ]);
}

test('the hourly sync lets the role follow the groups', function () {
    adEinschalten();
    [$admin, $techniker] = adRollen();
    $nutzer = adNutzer($techniker);
    adAttrappe(adKonto(['DokuVault-Admins']));

    $this->artisan('directory:sync')->expectsOutputToContain('1 geprüft, 1 aktualisiert, 0 Zugang beendet.')->assertSuccessful();

    expect($nutzer->fresh()->role_id)->toBe($admin->id)
        ->and($nutzer->fresh()->directory_revoked_at)->toBeNull();
});

test('disabled in AD, the sync ends sessions and app tokens at once, and only once', function () {
    adEinschalten();
    [, $techniker] = adRollen();
    $nutzer = adNutzer($techniker, ['remember_token' => 'alt']);
    // As the app sign-in creates it (LoginController): ability "mobil".
    $nutzer->createToken('App iPhone', ['mobil']);
    $hash = $nutzer->password;
    // Not found: deleted or disabled in the directory.
    adAttrappe(null);

    $this->artisan('directory:sync')->expectsOutputToContain('1 Zugang beendet')->assertSuccessful();

    $nutzer->refresh();
    expect($nutzer->password)->not->toBe($hash)
        ->and($nutzer->remember_token)->not->toBe('alt')
        ->and($nutzer->directory_revoked_at)->not->toBeNull()
        ->and($nutzer->tokens()->count())->toBe(0)
        // The account stays, with its history.
        ->and($nutzer->isDisabled())->toBeFalse();

    expect(Activity::where('event', 'ad_abgleich')->pluck('description')->all())
        ->toBe(['Zugang beendet: im AD gesperrt oder gelöscht']);

    // The next hour does not do it again.
    $this->artisan('directory:sync')->expectsOutputToContain('0 geprüft')->assertSuccessful();
    expect(Activity::where('event', 'ad_abgleich')->count())->toBe(1);
});

test('out of every mapped group, access ends as well', function () {
    adEinschalten();
    [, $techniker] = adRollen();
    $nutzer = adNutzer($techniker);
    adAttrappe(adKonto(['Domain Users']));

    $this->artisan('directory:sync')->assertSuccessful();

    expect($nutzer->fresh()->directory_revoked_at)->not->toBeNull()
        ->and(Activity::where('event', 'ad_abgleich')->value('description'))
        ->toBe('Zugang beendet: in keiner freigegebenen Gruppe mehr');
});

test('with AD out of reach the sync changes nothing', function () {
    adEinschalten();
    [, $techniker] = adRollen();
    $nutzer = adNutzer($techniker);
    $hash = $nutzer->password;
    adAttrappe(adKonto(), weg: true);

    $this->artisan('directory:sync')->assertFailed();

    expect($nutzer->fresh()->password)->toBe($hash)
        ->and($nutzer->fresh()->directory_revoked_at)->toBeNull();
});

test('back in the group, a sign-in lifts the mark', function () {
    adEinschalten();
    [, $techniker] = adRollen();
    $nutzer = adNutzer($techniker, ['directory_revoked_at' => now()]);
    adAttrappe(adKonto());

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])->assertSessionHasNoErrors();

    expect($nutzer->fresh()->directory_revoked_at)->toBeNull();
    $this->assertAuthenticatedAs($nutzer->fresh());
});

test('with the AD sign-in off the sync does nothing', function () {
    [, $techniker] = adRollen();
    $nutzer = adNutzer($techniker);
    adAttrappe(null);

    $this->artisan('directory:sync')->expectsOutputToContain('nichts zu tun')->assertSuccessful();

    expect($nutzer->fresh()->directory_revoked_at)->toBeNull();
});

test('without the right to change settings the page stays closed', function () {
    $this->actingAs(userWithPermissions([]));

    $this->get(route('admin.directory.index'))->assertForbidden();
});

test('without the directory switched on nothing changes', function () {
    $attrappe = adAttrappe(adKonto());

    $this->post('/login', ['username' => 'pkuhlmann', 'password' => 'richtig'])
        ->assertSessionHasErrors(['username' => trans('auth.failed')]);

    expect($attrappe->gefragt)->toBe([]);
});
