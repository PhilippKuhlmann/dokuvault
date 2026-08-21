<?php

use App\Livewire\AdminPapierkorb;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\IpAddress;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** Ein Objekt so alt machen, wie der Test es braucht - update() fasst deleted_at nicht an. */
function imPapierkorbSeit($objekt, int $tage): void
{
    $objekt->delete();
    DB::table($objekt->getTable())->where('id', $objekt->id)
        ->update(['deleted_at' => now()->subDays($tage)]);
}

test('der Filter zeigt nur Eintraege ueber der Altersgrenze', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['admin_trash']));

    imPapierkorbSeit(Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'uralt.de']), 400);
    imPapierkorbSeit(Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'neulich.de']), 10);

    Livewire::test(AdminPapierkorb::class)
        ->assertSee('uralt.de')
        ->assertSee('neulich.de')
        // Frei eingebbar, nicht auf feste Stufen beschraenkt.
        ->set('aelterAls', 365)
        ->assertSee('uralt.de')
        ->assertDontSee('neulich.de');
});

test('ein einzelner Eintrag laesst sich endgueltig loeschen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['admin_trash']));

    $domain = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'weg.de']);
    imPapierkorbSeit($domain, 400);

    Livewire::test(AdminPapierkorb::class)
        ->call('loeschen', 'domain', $domain->id);

    // Endgueltig heisst endgueltig - auch withTrashed findet nichts mehr.
    expect(Domain::withTrashed()->find($domain->id))->toBeNull();
});

test('das Sammelloeschen trifft nur, was der Filter zeigt', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['admin_trash']));

    $alt = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'alt.de']);
    $jung = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'jung.de']);
    imPapierkorbSeit($alt, 400);
    imPapierkorbSeit($jung, 10);

    Livewire::test(AdminPapierkorb::class)
        ->set('aelterAls', 365)
        ->call('alleLoeschen');

    expect(Domain::withTrashed()->find($alt->id))->toBeNull();
    // Wer den Filter setzt, erwartet nicht, dass etwas ausserhalb davon
    // verschwindet.
    expect(Domain::withTrashed()->find($jung->id))->not->toBeNull();
});

test('haengende IP-Adressen verschwinden mit', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['admin_trash']));

    $server = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
    ]);
    $server->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.0.7']);
    imPapierkorbSeit($server, 400);

    Livewire::test(AdminPapierkorb::class)->call('loeschen', 'server', $server->id);

    expect(IpAddress::where('ipable_id', $server->id)
        ->where('ipable_type', Server::class)->count())->toBe(0);
});

test('ohne admin_trash kommt niemand an die Seite', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    Livewire::test(AdminPapierkorb::class)->assertForbidden();
});

test('die Rueckfrage bleibt stehen, bis man sie beantwortet', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['admin_trash']));

    imPapierkorbSeit(Domain::factory()->create(['customer_id' => $customer->id]), 400);

    // Der Hook auf Eigenschaftsaenderungen hat die Rueckfrage anfangs sofort
    // wieder geschlossen - der Knopf tat dann schlicht nichts.
    Livewire::test(AdminPapierkorb::class)
        ->set('loeschenGefragt', true)
        ->assertSet('loeschenGefragt', true);
});

/** Wie AdminPapierkorb, holt aber nur einen Eintrag je Art. */
class PapierkorbMitEngerGrenze extends AdminPapierkorb
{
    protected const HOECHSTENS_JE_ART = 1;
}

test('die Seite sagt es, wenn sie nicht alles zeigt', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['admin_trash']));

    imPapierkorbSeit(Domain::factory()->create(['customer_id' => $customer->id]), 5);
    imPapierkorbSeit(Domain::factory()->create(['customer_id' => $customer->id]), 6);

    // Ohne den Hinweis haelt man die Zahl oben fuer den ganzen Bestand und
    // wundert sich, warum nach dem Loeschen noch etwas da ist.
    Livewire::test(PapierkorbMitEngerGrenze::class)->assertSee('(gekürzt)');

    Livewire::test(AdminPapierkorb::class)->assertDontSee('(gekürzt)');
});
