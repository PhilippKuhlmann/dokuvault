<?php

use App\Livewire\AdminAgentenEinstellungen;
use App\Livewire\ObjektListe;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Setting;
use App\Support\CertificateCheck;
use App\Support\DomainCheck;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** DNS answers without a network: [host][type] => records. */
function fakeDns(array $zone): void
{
    app()->instance(DomainCheck::class, new class($zone) extends DomainCheck
    {
        public function __construct(private array $zone) {}

        protected function lookup(string $host, int $type): array
        {
            return $this->zone[$host][$type] ?? [];
        }
    });
}

/** RDAP bootstrap plus one answer for every .com domain. */
function fakeRdap(array $domainAnswer, int $status = 200): void
{
    Http::fake([
        DomainCheck::BOOTSTRAP_URL => Http::response(['services' => [[['com'], ['https://rdap.test/']]]]),
        'rdap.test/*' => Http::response($domainAnswer, $status),
    ]);
}

function fakeCertificate(?array $parsed): void
{
    app()->instance(CertificateCheck::class, new class($parsed) extends CertificateCheck
    {
        public function __construct(private ?array $parsed) {}

        protected function peerCertificate(string $host, int $port): array
        {
            if ($this->parsed === null) {
                throw new RuntimeException("Keine Verbindung zu {$host}:{$port}");
            }

            return $this->parsed;
        }
    });
}

function exampleZone(): array
{
    return [
        'example.com' => [
            DNS_NS => [['target' => 'ns2.provider.net'], ['target' => 'NS1.provider.net']],
            DNS_MX => [['target' => 'mx2.example.com', 'pri' => 20], ['target' => 'mx1.example.com', 'pri' => 10]],
            DNS_TXT => [['txt' => 'google-site-verification=x'], ['txt' => 'v=spf1 mx -all', 'entries' => ['v=spf1 mx -all']]],
        ],
    ];
}

test('Domainprüfung übernimmt Ablauf, Nameserver und Mail-Einträge', function () {
    fakeDns(exampleZone());
    fakeRdap([
        'events' => [['eventAction' => 'registration', 'eventDate' => '2010-01-01T00:00:00Z'], ['eventAction' => 'expiration', 'eventDate' => '2027-03-15T10:00:00Z']],
        'entities' => [['roles' => ['registrar'], 'vcardArray' => ['vcard', [['version', [], 'text', '4.0'], ['fn', [], 'text', 'Example Registrar Inc.']]]]],
    ]);

    $domain = Domain::factory()->for(Customer::factory())->create(['name' => 'example.com', 'registrar' => null, 'expiry_date' => null]);

    $this->artisan('domains:check')->assertSuccessful();

    $domain->refresh();
    expect($domain->expiry_date)->toBe('2027-03-15')
        // The registry's registrar goes beside, never into the typed field.
        ->and($domain->registrar)->toBeNull()
        ->and($domain->registry_registrar)->toBe('Example Registrar Inc.')
        ->and($domain->nameserver1)->toBe('ns1.provider.net')
        ->and($domain->nameserver2)->toBe('ns2.provider.net')
        ->and($domain->mx)->toBe('mx1.example.com, mx2.example.com')
        ->and($domain->spf)->toBe('v=spf1 mx -all')
        ->and($domain->dmarc)->toBeNull()
        ->and($domain->check_error)->toBeNull()
        ->and($domain->checked_at)->not->toBeNull();
});

test('ohne Ablaufdatum im RDAP (wie bei .de) bleibt das gepflegte Datum', function () {
    fakeDns(exampleZone());
    fakeRdap(['events' => [['eventAction' => 'last changed', 'eventDate' => '2024-01-01T00:00:00Z']]]);

    $domain = Domain::factory()->for(Customer::factory())->create(['name' => 'example.com', 'registrar' => 'INWX', 'expiry_date' => '2026-12-31']);

    $this->artisan('domains:check')->assertSuccessful();

    $domain->refresh();
    expect($domain->expiry_date)->toBe('2026-12-31')
        // A registrar someone typed in is kept.
        ->and($domain->registrar)->toBe('INWX')
        ->and($domain->check_error)->toBeNull();
});

test('schlägt die Abfrage fehl, bleiben die gespeicherten Werte stehen', function () {
    fakeDns([]);
    fakeRdap([], 503);

    $domain = Domain::factory()->for(Customer::factory())->create([
        'name' => 'example.com', 'expiry_date' => '2026-12-31',
        'nameserver1' => 'ns1.alt.de', 'spf' => 'v=spf1 -all',
    ]);

    $this->artisan('domains:check')->assertSuccessful();

    $domain->refresh();
    expect($domain->expiry_date)->toBe('2026-12-31')
        ->and($domain->nameserver1)->toBe('ns1.alt.de')
        ->and($domain->spf)->toBe('v=spf1 -all')
        ->and($domain->check_error)->toBe('Keine Nameserver gefunden.');
});

test('Zertifikatsprüfung übernimmt Aussteller und Gültigkeit', function () {
    fakeCertificate([
        'subject' => ['CN' => 'www.example.com'],
        'issuer' => ['O' => "Let's Encrypt", 'CN' => 'R11'],
        'validFrom_time_t' => strtotime('2026-08-01 12:00:00 UTC'),
        'validTo_time_t' => strtotime('2026-10-30 12:00:00 UTC'),
    ]);

    $certificate = Certificate::factory()->for(Customer::factory())->create(['common_name' => 'www.example.com', 'issuer' => null]);

    $this->artisan('domains:check')->assertSuccessful();

    $certificate->refresh();
    expect($certificate->issuer)->toBe("Let's Encrypt (R11)")
        ->and($certificate->issued_date)->toBe('2026-08-01')
        ->and($certificate->expiry_date)->toBe('2026-10-30')
        ->and($certificate->check_error)->toBeNull();
});

test('Wildcard ohne Prüf-Host wird nicht abgefragt', function () {
    fakeCertificate(null);

    $certificate = Certificate::factory()->for(Customer::factory())->create(['common_name' => '*.example.com', 'expiry_date' => '2026-12-01']);

    $this->artisan('domains:check')->assertSuccessful();

    $certificate->refresh();
    expect($certificate->check_error)->toContain('Host angeben')
        ->and($certificate->expiry_date)->toBe('2026-12-01');
});

test('nicht erreichbarer Host lässt das Zertifikat unverändert', function () {
    fakeCertificate(null);

    $certificate = Certificate::factory()->for(Customer::factory())->create(['common_name' => 'www.example.com', 'expiry_date' => '2026-12-01']);

    $this->artisan('domains:check')->assertSuccessful();

    $certificate->refresh();
    expect($certificate->check_error)->toContain('Keine Verbindung')
        ->and($certificate->expiry_date)->toBe('2026-12-01');
});

test('ausgenommene Einträge und abgeschaltete Prüfung werden übersprungen', function () {
    fakeCertificate(null);

    $aus = Certificate::factory()->for(Customer::factory())->create(['common_name' => 'a.example.com', 'auto_check' => false]);
    $an = Certificate::factory()->for(Customer::factory())->create(['common_name' => 'b.example.com']);

    $this->artisan('domains:check')->assertSuccessful();
    expect($aus->refresh()->checked_at)->toBeNull()
        ->and($an->refresh()->checked_at)->not->toBeNull();

    Setting::setzen(Setting::AUTO_CHECK, '0');
    $an->update(['checked_at' => null]);

    $this->artisan('domains:check')->assertSuccessful();
    expect($an->refresh()->checked_at)->toBeNull();
});

test('die tägliche Prüfung schreibt keinen Protokolleintrag', function () {
    fakeCertificate(null);
    $certificate = Certificate::factory()->for(Customer::factory())->create(['common_name' => 'www.example.com']);
    $vorher = Activity::count();

    $this->artisan('domains:check')->assertSuccessful();
    $this->artisan('domains:check')->assertSuccessful();

    expect(Activity::count())->toBe($vorher)
        ->and($certificate->refresh()->check_error)->not->toBeNull();
});

test('Jetzt prüfen auf der Karte prüft nur Einträge des eigenen Kunden', function () {
    fakeCertificate([
        'subject' => ['CN' => 'www.example.com'],
        'issuer' => ['CN' => 'Test CA'],
        'validTo_time_t' => strtotime('2027-01-01 12:00:00 UTC'),
    ]);

    $this->actingAs(userWithPermissions(['certificate_viewAny', 'certificate_update']));
    $customer = Customer::factory()->create();
    $certificate = Certificate::factory()->for(Customer::factory())->create(['customer_id' => $customer->id, 'common_name' => 'www.example.com']);
    $fremd = Certificate::factory()->for(Customer::factory())->create(['common_name' => 'www.example.com']);

    $liste = Livewire::test(ObjektListe::class, ['typ' => 'certificate', 'customer' => $customer]);

    $liste->call('check', $certificate->id)->assertDispatched('hinweis');
    expect($certificate->refresh()->expiry_date)->toBe('2027-01-01');

    expect(fn () => $liste->call('check', $fremd->id))->toThrow(ModelNotFoundException::class);
    expect($fremd->refresh()->checked_at)->toBeNull();
});

test('ohne Bearbeiten-Recht kein Jetzt prüfen', function () {
    fakeCertificate(null);
    $this->actingAs(userWithPermissions(['certificate_viewAny']));
    $customer = Customer::factory()->create();
    $certificate = Certificate::factory()->for(Customer::factory())->create(['customer_id' => $customer->id]);

    Livewire::test(ObjektListe::class, ['typ' => 'certificate', 'customer' => $customer])
        ->call('check', $certificate->id)
        ->assertForbidden();
});

test('Admin schaltet die Prüfung ab', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminAgentenEinstellungen::class)
        ->assertSet('autoCheck', true)
        ->set('autoCheck', false);

    expect(Setting::autoCheck())->toBeFalse();
});

test('DKIM wird unter üblichen und eigenen Selektoren gefunden', function () {
    fakeDns(exampleZone() + [
        // Microsoft 365: selector1 is a CNAME into Microsoft's zone; the
        // resolver follows it and answers with the key behind it.
        'selector1._domainkey.example.com' => [DNS_TXT => [['txt' => 'v=DKIM1; k=rsa; p=MIGf']]],
        'mail2024._domainkey.example.com' => [DNS_TXT => [['txt' => 'v=DKIM1; k=ed25519; p=11qYAYKxCrfVS/7TyWQHOg7hcvPapiMlrwIaaPcHURo=']]],
    ]);
    fakeRdap([]);

    $domain = Domain::factory()->for(Customer::factory())->create(['name' => 'example.com', 'dkim_selectors' => 'mail2024, ung ültig']);

    $this->artisan('domains:check')->assertSuccessful();

    expect($domain->refresh()->dkim)->toBe([
        ['selector' => 'mail2024', 'type' => 'ed25519', 'bits' => 256, 'revoked' => false],
        // "MIGf" is no parseable key - found, length unknown.
        ['selector' => 'selector1', 'type' => 'rsa', 'bits' => null, 'revoked' => false],
    ]);
});

test('PTR der Mailserver wird vorwärts bestätigt', function () {
    fakeDns(exampleZone() + [
        'mx1.example.com' => [DNS_A => [['ip' => '192.0.2.10']]],
        'mx2.example.com' => [DNS_A => [['ip' => '192.0.2.20']]],
        '10.2.0.192.in-addr.arpa' => [DNS_PTR => [['target' => 'mx1.example.com']]],
        // Points somewhere that does not resolve back - receivers distrust that.
        '20.2.0.192.in-addr.arpa' => [DNS_PTR => [['target' => 'static.provider.net']]],
    ]);
    fakeRdap([]);

    $domain = Domain::factory()->for(Customer::factory())->create(['name' => 'example.com']);

    $this->artisan('domains:check')->assertSuccessful();

    expect($domain->refresh()->ptr)->toBe([
        ['host' => 'mx1.example.com', 'ip' => '192.0.2.10', 'ptr' => 'mx1.example.com', 'ok' => true],
        ['host' => 'mx2.example.com', 'ip' => '192.0.2.20', 'ptr' => 'static.provider.net', 'ok' => false],
    ]);
});

test('die Karte warnt bei fehlendem DKIM und falschem PTR', function () {
    $this->actingAs(userWithPermissions(['domain_viewAny']));
    $customer = Customer::factory()->create();
    Domain::factory()->create([
        'customer_id' => $customer->id, 'name' => 'example.com',
        'nameserver1' => 'ns1.provider.net', 'mx' => 'mx1.example.com', 'spf' => 'v=spf1 mx -all', 'dmarc' => 'v=DMARC1; p=none',
        'dkim' => null, 'checked_at' => now(),
        'ptr' => [['host' => 'mx1.example.com', 'ip' => '192.0.2.10', 'ptr' => null, 'ok' => false]],
    ]);

    $this->get("/{$customer->slug}/domain")
        ->assertOk()
        ->assertSee('Kein DKIM-Schlüssel')
        ->assertSee('Reverse DNS eines Mailservers')
        ->assertDontSee('Kein DMARC-Eintrag');
});

test('ein Wildcard-CNAME täuscht keine DKIM-Schlüssel vor', function () {
    app()->instance(DomainCheck::class, new class extends DomainCheck
    {
        protected function lookup(string $host, int $type): array
        {
            if ($host === 'example.com') {
                return exampleZone()['example.com'][$type] ?? [];
            }

            // Every name under _domainkey answers with the same CNAME.
            return $type === DNS_CNAME && str_ends_with($host, '._domainkey.example.com')
                ? [['target' => 'catchall.provider.net']] : [];
        }
    });
    fakeRdap([]);

    $domain = Domain::factory()->for(Customer::factory())->create(['name' => 'example.com']);

    $this->artisan('domains:check')->assertSuccessful();

    expect($domain->refresh()->dkim)->toBeNull();
});

test('läuft das Zeitbudget ab, bleiben DKIM und PTR stehen und der Grund steht da', function () {
    app()->instance(DomainCheck::class, new class extends DomainCheck
    {
        private int $calls = 0;

        // As if the DNS hung: the budget is gone after two DKIM lookups.
        protected function timeLeft(): bool
        {
            if (++$this->calls <= 2) {
                return true;
            }

            $this->incomplete = true;

            return false;
        }

        protected function lookup(string $host, int $type): array
        {
            return exampleZone()[$host][$type] ?? [];
        }
    });
    fakeRdap([]);

    $domain = Domain::factory()->for(Customer::factory())->create([
        'name' => 'example.com', 'dkim' => [['selector' => 'selector1', 'type' => 'rsa', 'bits' => 2048, 'revoked' => false]],
        'ptr' => [['host' => 'mx1.example.com', 'ip' => '192.0.2.10', 'ptr' => 'mx1.example.com', 'ok' => true]],
    ]);

    $this->artisan('domains:check')->assertSuccessful();

    $domain->refresh();
    expect($domain->dkim[0]['selector'])->toBe('selector1')
        ->and($domain->ptr[0]['ok'])->toBeTrue()
        ->and($domain->mx)->toBe('mx1.example.com, mx2.example.com')
        ->and($domain->check_error)->toContain('DNS antwortet langsam');
});

test('Schlüssellänge wird erkannt, ein schwacher oder zurückgezogener Schlüssel gemeldet', function () {
    $schluessel = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $pem = openssl_pkey_get_details($schluessel)['key'];
    $p = preg_replace('/-----[^-]+-----|\s+/', '', $pem);

    expect(DomainCheck::dkimKey("v=DKIM1; k=rsa; p={$p}"))->toBe(['type' => 'rsa', 'bits' => 1024, 'revoked' => false])
        ->and(DomainCheck::dkimKey('v=DKIM1; p='))->toBe(['type' => 'rsa', 'bits' => null, 'revoked' => true]);

    $this->actingAs(userWithPermissions(['domain_viewAny']));
    $customer = Customer::factory()->create();
    Domain::factory()->create([
        'customer_id' => $customer->id, 'name' => 'example.com',
        'nameserver1' => 'ns1.provider.net', 'mx' => 'mx1.example.com', 'spf' => 'v=spf1 mx -all', 'dmarc' => 'v=DMARC1; p=none',
        'checked_at' => now(),
        'dkim' => [
            ['selector' => 'selector1', 'type' => 'rsa', 'bits' => 1024, 'revoked' => false],
            ['selector' => 'alt', 'type' => 'rsa', 'bits' => null, 'revoked' => true],
            ['selector' => 'neu', 'type' => 'rsa', 'bits' => 4096, 'revoked' => false],
        ],
    ]);

    $this->get("/{$customer->slug}/domain")
        ->assertOk()
        ->assertSee('Selektor neu')
        ->assertSee('eingerichtet · RSA 4096 Bit')
        ->assertSee('zurückgezogen (leerer Schlüssel)')
        ->assertSee('unter Selektor selector1 hat nur 1024 Bit')
        ->assertDontSee('Kein DKIM-Schlüssel');
});
