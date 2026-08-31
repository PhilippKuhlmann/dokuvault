<?php

use App\Livewire\DateiListe;
use App\Livewire\DeviceCredentials;
use App\Livewire\DeviceIpAddresses;
use App\Livewire\DocumentationWizard;
use App\Livewire\GlobalSearch;
use App\Livewire\NetworkList;
use App\Livewire\NetworkQuickCreate;
use App\Livewire\ObjektFormular;
use App\Livewire\ObjektListe;
use App\Livewire\PatchPanelPorts;
use App\Livewire\PdfExportStatus;
use App\Livewire\RackEditor;
use App\Livewire\SearchCustomer;
use App\Models\Camera;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\PatchPanel;
use App\Models\PdfExport;
use App\Models\Permission;
use App\Models\Rack;
use App\Models\Role;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Kein Kunde darf Daten eines anderen Kunden sehen.
 *
 * Die isCustomer-Middleware haengt an den Routen - und Livewire ruft nicht
 * ueber sie, sondern ueber /livewire/update. Jede Komponente, die einen
 * Bezeichner von aussen annimmt, muss deshalb selbst pruefen. Genau das
 * stellen diese Tests auf die Probe.
 *
 * Der Angreifer bekommt hier absichtlich ALLE Rechte: Was ihn aufhaelt, soll
 * die Mandantentrennung sein und nicht ein fehlendes Recht, das morgen
 * jemand vergibt.
 */
function fremdeUmgebung(): array
{
    $meins = Customer::factory()->create(['name' => 'Kunde A']);
    $fremd = Customer::factory()->create(['name' => 'Kunde B']);

    $meinStandort = Site::factory()->create(['customer_id' => $meins->id, 'name' => 'Standort A']);
    $fremderStandort = Site::factory()->create(['customer_id' => $fremd->id, 'name' => 'Standort B']);

    return [$meins, $fremd, $meinStandort, $fremderStandort];
}

/**
 * Ein Nutzer von Kunde A - mit allen Rechten, damit nur der Mandant ihn
 * aufhaelt.
 *
 * Die Rechte kommen aus dem Seeder und damit aus derselben Liste, aus der sie
 * auch in der Rollenverwaltung entstehen. Sie hier von Hand zu bilden ging
 * einmal schief: config('custom.permissions') ist eine Liste von Bereichen,
 * keine Zuordnung - der Angreifer hiess dann "0_viewAny" und hatte gar keine
 * Rechte. Die Tests waeren gruen gewesen, ohne irgendetwas zu beweisen.
 */
function kundenNutzerMitAllenRechten(Customer $kunde): User
{
    (new PermissionRoleSeeder)->run();

    $rolle = Role::factory()->create(['id' => (Role::max('id') ?? 0) + 100]);
    $rolle->permissions()->attach(Permission::pluck('id'));

    return User::factory()->create(['role_id' => $rolle->id, 'customer_id' => $kunde->id]);
}

test('der Angreifer hat wirklich alle Rechte', function () {
    // Sonst prueft dieser ganze Test nur, dass jemand ohne Rechte nichts sieht.
    [$meins] = fremdeUmgebung();
    $nutzer = kundenNutzerMitAllenRechten($meins);

    expect($nutzer->role->permissions)->toHaveCount(Permission::count());
    expect(Permission::count())->toBeGreaterThan(150);
    expect($nutzer->can('server_viewAny'))->toBeTrue();
    expect($nutzer->can('logingeneral_viewAny'))->toBeTrue();
    expect($nutzer->can('camera_delete'))->toBeTrue();
});

test('die Geräteliste eines fremden Kunden bleibt zu', function () {
    [$meins, $fremd, , $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    NetworkSwitch::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id, 'name' => 'SW-GEHEIM',
    ]);

    // Ueber die Route: Die Middleware greift.
    $this->get("/{$fremd->slug}/networkswitch")->assertForbidden();

    // Und direkt an der Komponente vorbei, wie es /livewire/update tut.
    Livewire::test(ObjektListe::class, ['typ' => 'networkswitch', 'customer' => $fremd])
        ->assertForbidden();
});

test('das Formular eines fremden Kunden öffnet nicht', function () {
    [$meins, $fremd] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    Livewire::test(ObjektFormular::class, ['typ' => 'server', 'customer' => $fremd])
        ->assertForbidden();
});

test('ein fremdes Objekt lässt sich nicht über die eigene Liste bearbeiten', function () {
    [$meins, $fremd, , $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $fremderSwitch = NetworkSwitch::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id,
        'name' => 'SW-GEHEIM', 'password' => 'streng-geheim',
    ]);

    // Der gefaehrlichste Weg: eigenes Formular, fremde Nummer. Es endet in
    // einem 404 statt einem 403, weil die Abfrage nach dem eigenen Kunden
    // sucht und nichts findet - fuer die Trennung ist beides gleichwertig,
    // und ein 404 verraet sogar weniger als ein 403.
    expect(fn () => Livewire::test(ObjektFormular::class, ['typ' => 'networkswitch', 'customer' => $meins])
        ->call('bearbeiten', 'networkswitch', $fremderSwitch->id))
        ->toThrow(ModelNotFoundException::class);

    expect($fremderSwitch->fresh()->name)->toBe('SW-GEHEIM');
});

test('Zugangsdaten und IP-Adressen eines fremden Geräts bleiben zu', function () {
    [$meins, $fremd, , $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022 Standard']);
    $fremderServer = Server::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id,
        'name' => 'SRV-GEHEIM', 'operating_system_id' => $os->id,
    ]);

    Livewire::test(DeviceCredentials::class, ['model' => $fremderServer, 'customer' => $fremd])
        ->assertForbidden();

    Livewire::test(DeviceIpAddresses::class, ['model' => $fremderServer, 'customer' => $fremd])
        ->assertForbidden();

    // Und mit untergeschobenem eigenem Kunden - das Geraet gehoert trotzdem nicht ihm.
    Livewire::test(DeviceCredentials::class, ['model' => $fremderServer, 'customer' => $meins])
        ->assertForbidden();
});

test('der Serverschrank eines fremden Kunden bleibt zu', function () {
    [$meins, $fremd, , $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $fremdesRack = Rack::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id,
        'name' => 'Rack GEHEIM', 'height_units' => 42,
    ]);

    Livewire::test(RackEditor::class, ['rack' => $fremdesRack, 'customer' => $fremd])
        ->assertForbidden();

    Livewire::test(RackEditor::class, ['rack' => $fremdesRack, 'customer' => $meins])
        ->assertForbidden();
});

test('die Dosen eines fremden Patchfelds bleiben zu', function () {
    [$meins, $fremd, , $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $fremdesFeld = PatchPanel::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id,
        'name' => 'PF-GEHEIM', 'port_count' => 24,
    ]);

    Livewire::test(PatchPanelPorts::class, ['panel' => $fremdesFeld, 'customer' => $fremd])
        ->assertForbidden();
});

test('Dateien, Netze, Assistent und PDF-Status eines fremden Kunden bleiben zu', function () {
    [$meins, $fremd] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    Livewire::test(DateiListe::class, ['customer' => $fremd])->assertForbidden();
    Livewire::test(NetworkList::class, ['customer' => $fremd])->assertForbidden();
    Livewire::test(DocumentationWizard::class, ['customer' => $fremd])->assertForbidden();
    Livewire::test(NetworkQuickCreate::class, ['customer' => $fremd])->assertForbidden();
});

test('die globale Suche zeigt nur die eigenen Objekte', function () {
    [$meins, $fremd, $meinStandort, $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $os = OperatingSystem::factory()->create(['name' => 'Debian 12']);
    Server::create(['customer_id' => $meins->id, 'site_id' => $meinStandort->id, 'name' => 'SRV-EIGEN', 'operating_system_id' => $os->id]);
    Server::create(['customer_id' => $fremd->id, 'site_id' => $fremderStandort->id, 'name' => 'SRV-FREMD', 'operating_system_id' => $os->id]);
    Camera::create(['customer_id' => $fremd->id, 'site_id' => $fremderStandort->id, 'name' => 'CAM-FREMD',
        'serialNumber' => 'GEHEIM-1', 'username' => 'admin', 'password' => 'geheim']);
    LoginGeneral::create(['customer_id' => $fremd->id, 'name' => 'Fremder Zugang', 'username' => 'fremd', 'password' => 'geheim']);
    Network::create(['customer_id' => $fremd->id, 'site_id' => $fremderStandort->id, 'description' => 'Fremdes VLAN', 'vlanId' => 999, 'network' => '10.99.99.0', 'cidr' => 24]);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'SRV')
        ->assertSee('SRV-EIGEN')
        ->assertDontSee('SRV-FREMD');

    // Auch nicht ueber die Seriennummer eines fremden Geraets.
    Livewire::test(GlobalSearch::class)
        ->set('search', 'GEHEIM-1')
        ->assertDontSee('CAM-FREMD');
});

test('die Kundensuche bietet keinen fremden Kunden an', function () {
    [$meins, $fremd] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    Livewire::test(SearchCustomer::class)
        ->set('search', 'Kunde')
        ->assertDontSee('Kunde B');
});

test('das PDF eines fremden Kunden lässt sich nicht abholen', function () {
    [$meins, $fremd] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $export = PdfExport::create([
        'customer_id' => $fremd->id,
        'user_id' => User::factory()->create()->id,
        'status' => 'fertig',
        'path' => 'pdf-exports/'.$fremd->id.'/geheim.pdf',
    ]);

    // 403 vom Mandanten-Schutz, 404 von der gebundenen Route - beides weist
    // ab, und mehr als abweisen soll hier nichts geschehen.
    expect($this->get("/{$fremd->slug}/pdf/{$export->id}")->status())->toBeIn([403, 404]);
    // Und mit der eigenen Adresse davor - der Export gehoert trotzdem nicht ihm.
    expect($this->get("/{$meins->slug}/pdf/{$export->id}")->status())->toBeIn([403, 404]);
});

test('ein fremdes Objekt lässt sich nicht aus dem Papierkorb zurückholen', function () {
    [$meins, $fremd, , $fremderStandort] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $fremdeKamera = Camera::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id, 'name' => 'CAM-FREMD',
        'username' => 'admin', 'password' => 'geheim',
    ]);
    $fremdeKamera->delete();

    expect($this->post("/{$meins->slug}/trash/camera/{$fremdeKamera->id}/restore")->status())
        ->toBeIn([403, 404]);

    expect(Camera::withTrashed()->find($fremdeKamera->id)->trashed())->toBeTrue();
});

test('keine Komponente gibt einem fremden Kundennutzer Inhalte heraus', function () {
    // Der Kern der Sache, als Inhaltsprüfung statt als Statusprüfung: Ein
    // 403 ist gut, aber entscheidend ist, dass nichts Fremdes im HTML landet.
    //
    // Hier fiel es auf: ObjektListe lieferte die Geräte des fremden Kunden
    // aus, samt Benutzername, Kennwort und Seriennummer. Die Route war
    // geschützt - Livewire ruft aber über /livewire/update, und dort läuft
    // die isCustomer-Middleware nicht.
    [$meins, $fremd, $meinStandort, $fremderStandort] = fremdeUmgebung();
    $fremderStandort->update(['name' => 'STANDORT-GEHEIM']);

    NetworkSwitch::create([
        'customer_id' => $fremd->id, 'site_id' => $fremderStandort->id,
        'name' => 'SW-GEHEIM', 'username' => 'geheim-user', 'password' => 'streng-geheim',
        'serialNumber' => 'SN-GEHEIM-1',
    ]);

    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $geheim = ['SW-GEHEIM', 'geheim-user', 'streng-geheim', 'SN-GEHEIM-1', 'STANDORT-GEHEIM', 'Kunde B'];

    $ohneGeheimnis = function (string $was, callable $ruf) use ($geheim) {
        try {
            $html = $ruf();
        } catch (HttpException $e) {
            return; // abgewiesen ist das beste Ergebnis
        }

        foreach ($geheim as $wort) {
            expect(str_contains($html, $wort))->toBeFalse("{$was} gibt \"{$wort}\" heraus.");
        }
    };

    $ohneGeheimnis('ObjektListe', fn () => Livewire::test(ObjektListe::class, ['typ' => 'networkswitch', 'customer' => $fremd])->html());
    $ohneGeheimnis('ObjektFormular', fn () => Livewire::test(ObjektFormular::class, ['typ' => 'networkswitch', 'customer' => $fremd])->html());
    $ohneGeheimnis('DateiListe', fn () => Livewire::test(DateiListe::class, ['customer' => $fremd])->html());
    $ohneGeheimnis('NetworkList', fn () => Livewire::test(NetworkList::class, ['customer' => $fremd])->html());
    $ohneGeheimnis('DocumentationWizard', fn () => Livewire::test(DocumentationWizard::class, ['customer' => $fremd])->html());
    $ohneGeheimnis('NetworkQuickCreate', fn () => Livewire::test(NetworkQuickCreate::class, ['customer' => $fremd])->html());
    $ohneGeheimnis('PdfExportStatus', fn () => Livewire::test(PdfExportStatus::class, ['customer' => $fremd])->html());
    $ohneGeheimnis('SearchCustomer', fn () => Livewire::test(SearchCustomer::class)->set('search', 'Kunde')->html());
    $ohneGeheimnis('GlobalSearch', fn () => Livewire::test(GlobalSearch::class)->set('search', 'GEHEIM')->html());
});

test('jede Komponente mit einem Kunden als Parameter weist einen fremden ab', function () {
    // Eine Invariante statt einer Aufzählung: Wer morgen eine Komponente
    // hinzufügt, die einen Kunden entgegennimmt, muss sie prüfen - sonst
    // schlägt dieser Test fehl, ohne dass ihn jemand erweitern musste.
    [$meins, $fremd] = fremdeUmgebung();
    $this->actingAs(kundenNutzerMitAllenRechten($meins));

    $ungeprueft = [];

    foreach (glob(app_path('Livewire/*.php')) as $datei) {
        $klasse = 'App\\Livewire\\'.basename($datei, '.php');
        $spiegel = new ReflectionClass($klasse);

        if (! $spiegel->hasMethod('mount')) {
            continue;
        }

        $nimmtKunden = collect($spiegel->getMethod('mount')->getParameters())
            ->contains(fn ($p) => $p->getName() === 'customer');

        if (! $nimmtKunden) {
            continue;
        }

        $quelle = file_get_contents($datei);

        // Entweder die Komponente prüft beim Einhängen (der Trait) oder bei
        // jeder Aktion (RackEditor, PatchPanelPorts, der Assistent). Beides
        // ist in Ordnung - keines von beidem nicht.
        $prueft = str_contains($quelle, 'nurEigenerKunde(')
            // RackEditor und PatchPanelPorts pruefen bei jeder Aktion statt
            // beim Einhaengen - beide mit derselben Wendung.
            || str_contains($quelle, 'abort_if($user->customer_id')
            || str_contains($quelle, 'guard(');

        if (! $prueft) {
            $ungeprueft[] = class_basename($klasse);
        }
    }

    expect($ungeprueft)->toBe([], 'Ohne Mandantenprüfung: '.implode(', ', $ungeprueft));
});
