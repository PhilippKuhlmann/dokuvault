<?php

use App\Livewire\GlobalSearch;
use App\Livewire\ObjektFormular;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\DECT;
use App\Models\PatchPanel;
use App\Models\PatchPort;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Woran man ein Objekt erkennt - im Protokoll, im Papierkorb und in der Suche.
 *
 * Ein Testlauf mit echten Daten brachte es zutage: Ein Telefon hiess "#14",
 * ausgerechnet in der Suche nach seiner MAC-Adresse. Ein Ansprechpartner
 * hiess "#15", und die TK-Anlage hiess "admin" - der Rueckfall auf den
 * Benutzernamen, und damit schlimmer als eine Nummer.
 */
function bezeichnungsUmgebung(): array
{
    $customer = Customer::factory()->create();

    return [$customer, Site::factory()->create(['customer_id' => $customer->id])];
}

test('Telefon, DECT und Ansprechpartner heissen nach dem, was sie ausmacht', function () {
    [$customer, $site] = bezeichnungsUmgebung();

    $telefon = Phone::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'extension' => '21 - Einkauf', 'username' => 'admin',
    ]);
    $dect = DECT::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'role' => 'Halle Filetierung', 'username' => 'admin',
    ]);
    $kontakt = ContactPerson::create([
        'customer_id' => $customer->id,
        'first_name' => 'Torben', 'last_name' => 'Ahlers',
    ]);

    expect($telefon->protokollName())->toBe('21 - Einkauf');
    expect($dect->protokollName())->toBe('Halle Filetierung');
    expect($kontakt->protokollName())->toBe('Torben Ahlers');
});

test('die TK-Anlage heisst nach Hersteller und Modell, nicht nach ihrem Benutzernamen', function () {
    [$customer, $site] = bezeichnungsUmgebung();

    $anlage = PhoneSystem::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'manufacturer' => 'Auerswald', 'type' => 'COMmander', 'model' => '6000R',
        'username' => 'admin',
    ]);

    expect($anlage->protokollName())->toBe('Auerswald 6000R');

    // Gegenprobe: Ohne die eigene Methode faellt die zentrale Liste auf
    // username zurueck - im Protokoll stuende dann "admin".
    expect($anlage->protokollName())->not->toBe('admin');
});

test('die Nebenstelle steht vor dem Benutzernamen', function () {
    $felder = config('custom.name_fields');

    // Reihenfolge ist die Aussage: Ein Telefon fuehrt beides, und "admin"
    // waere die schlechtere Auskunft.
    expect(array_search('extension', $felder, true))->toBeLessThan(array_search('username', $felder, true));
    expect(array_search('role', $felder, true))->toBeLessThan(array_search('username', $felder, true));
});

test('die globale Suche zeigt den Namen und nicht die Nummer', function () {
    $this->actingAs(userWithPermissions(['phone_viewAny']));
    [$customer, $site] = bezeichnungsUmgebung();

    $telefon = Phone::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'extension' => '21 - Einkauf', 'mac' => '00:1A:2B:3C:4D:21',
    ]);

    Livewire::test(GlobalSearch::class)
        ->set('search', '00:1A:2B:3C:4D:21')
        ->assertSee('21 - Einkauf')
        ->assertDontSee('#'.$telefon->id);
});

test('jede Trefferart der Suche kann sich benennen', function () {
    // Die Ansicht ruft protokollName() auf jedem Treffer auf. Ein Model ohne
    // die Methode brach die Seite nicht, zeigte aber eine Nummer - so kam die
    // Netzwerkdose beinahe abhanden.
    $spiegel = new ReflectionClass(GlobalSearch::class);
    $typen = collect($spiegel->getConstants())->first(fn ($wert) => is_array($wert) && count($wert) > 5);

    expect($typen)->not->toBeEmpty();

    foreach ($typen as $slug => $eintrag) {
        expect(method_exists($eintrag[0], 'protokollName'))
            ->toBeTrue("{$slug} ({$eintrag[0]}) kennt protokollName() nicht und erschiene als Nummer.");
    }
});

test('eine Netzwerkdose heisst nach ihrem Aufdruck', function () {
    [$customer, $site] = bezeichnungsUmgebung();

    $feld = PatchPanel::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'PF-EG-01', 'port_count' => 24,
    ]);
    $feld->syncPorts();

    $dose = PatchPort::where('patch_panel_id', $feld->id)->where('number', 3)->firstOrFail();
    $dose->update(['outlet' => '2.14', 'label' => 'Besprechung']);

    expect($dose->fresh()->protokollName())->toContain('2.14');
});

// --- Kennwortregeln ---

test('ein Gerät darf ohne Kennwort dokumentiert werden, ein Zugangsdatensatz nicht', function () {
    // Der Anlass: Ein Etikettendrucker im Netz hat oft gar keinen Login, liess
    // sich aber nicht speichern - waehrend Switch, Kamera und Accesspoint es
    // erlaubten. Fuenf verschiedene Schreibweisen fuer dieselbe Frage.
    $nurZugang = ['LoginWebsiteRequest', 'DynDNSRequest'];
    $abweichend = [];

    foreach (glob(app_path('Http/Requests/*Request.php')) as $datei) {
        $inhalt = file_get_contents($datei);

        if (! preg_match("/'password' => '([^']*)'/", $inhalt, $treffer)) {
            continue;
        }

        $name = basename($datei, '.php');
        $soll = in_array($name, $nurZugang, true) ? 'required|max:255' : 'nullable|max:255';

        if ($treffer[1] !== $soll) {
            $abweichend[] = "{$name}: '{$treffer[1]}' statt '{$soll}'";
        }
    }

    expect($abweichend)->toBe([], 'Uneinheitliche Kennwortregeln: '.implode(' | ', $abweichend));
});

test('ein Drucker ohne Kennwort lässt sich anlegen', function () {
    $this->actingAs(userWithPermissions(['printer_create', 'printer_viewAny']));
    [$customer, $site] = bezeichnungsUmgebung();

    Livewire::test(ObjektFormular::class, ['typ' => 'printer', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'ETI-VERSAND')
        ->set('form.manufacturer', 'Zebra')
        ->set('form.model', 'ZT411')
        ->call('speichern')
        ->assertHasNoErrors();

    expect(Printer::where('name', 'ETI-VERSAND')->exists())->toBeTrue();
});

test('ein Webzugang ohne Kennwort wird abgelehnt', function () {
    // Gegenprobe: Bei einem reinen Zugangsdatensatz ist das Kennwort der
    // Gegenstand - ohne ihn dokumentiert man nichts.
    $this->actingAs(userWithPermissions(['loginwebsite_create', 'loginwebsite_viewAny']));
    [$customer] = bezeichnungsUmgebung();

    Livewire::test(ObjektFormular::class, ['typ' => 'loginwebsite', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'Strato')
        ->set('form.url', 'https://strato.de')
        ->set('form.username', 'kunde')
        ->call('speichern')
        ->assertHasErrors('form.password');
});
