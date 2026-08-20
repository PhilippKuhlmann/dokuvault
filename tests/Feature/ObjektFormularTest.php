<?php

use App\Livewire\DeviceIpAddresses;
use App\Livewire\ObjektFormular;
use App\Livewire\ObjektListe;
use App\Models\ADUser;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Firewall;
use App\Models\InternetConnection;
use App\Models\LicenseAccess;
use App\Models\LicenseWindows;
use App\Models\Machine;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\VM;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('die Feldliste deckt ab, was der Request erlaubt', function () {
    // Der eigentliche Schutz dieses Umbaus: Faellt ein Feld aus der Definition
    // heraus, laesst es sich im Modal nicht mehr ausfuellen - ohne Fehlermeldung.
    $luecken = [];

    foreach (config('forms') as $typ => $einstellung) {
        $request = new $einstellung['request'];
        $definiert = array_column($einstellung['felder'], 'name');

        foreach (array_keys($request->rules()) as $feld) {
            if (! in_array($feld, $definiert, true)) {
                $luecken[] = "$typ.$feld";
            }
        }
    }

    expect($luecken)->toBe([], 'Im Modal fehlen Felder, die der Request erlaubt: '.implode(', ', $luecken));
});

test('anlegen im Modal legt an und meldet es der Liste', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_create']));

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->assertSet('offen', true)
        ->set('form.name', 'beispiel.de')
        ->set('form.registrar', 'Hetzner')
        ->call('speichern')
        ->assertSet('offen', false)
        ->assertDispatched('objekt-gespeichert', typ: 'domain');

    $domain = Domain::where('name', 'beispiel.de')->sole();
    expect($domain->customer_id)->toBe($customer->id);
    expect($domain->registrar)->toBe('Hetzner');
});

test('bearbeiten laedt die Werte und speichert sie zurueck', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_update']));

    $domain = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'alt.de']);

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('bearbeiten', 'domain', $domain->id)
        ->assertSet('form.name', 'alt.de')
        ->set('form.name', 'neu.de')
        ->call('speichern');

    expect($domain->fresh()->name)->toBe('neu.de');
});

test('Neu nach Bearbeiten legt an, statt den alten Eintrag zu aendern', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_create', 'domain_update']));

    $domain = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'bestand.de']);

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('bearbeiten', 'domain', $domain->id)
        ->call('neu')
        ->assertSet('bearbeiteId', null)
        ->assertSet('form.name', '')
        ->set('form.name', 'zweite.de')
        ->call('speichern');

    expect($domain->fresh()->name)->toBe('bestand.de');
    expect(Domain::where('name', 'zweite.de')->exists())->toBeTrue();
});

test('eine fremde Domain laesst sich nicht bearbeiten', function () {
    $customer = Customer::factory()->create();
    $fremd = Customer::factory()->create();
    $fremdeDomain = Domain::factory()->create(['customer_id' => $fremd->id]);

    $this->actingAs(userWithPermissions(['domain_update']));

    // Die Id kommt vom Browser - sie wird deshalb immer ueber den Kunden
    // geladen, und eine fremde laeuft ins Leere statt fremde Daten zu oeffnen.
    expect(fn () => Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('bearbeiten', 'domain', $fremdeDomain->id))
        ->toThrow(ModelNotFoundException::class);
});

test('ohne Recht kein Anlegen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->assertForbidden();
});

test('die Liste sucht und zeigt den Anlegen-Knopf', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_create']));

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'treffer.de']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'anderes.de']);

    Livewire::test(ObjektListe::class, ['typ' => 'domain', 'customer' => $customer])
        ->assertSee('treffer.de')
        ->assertSee('anderes.de')
        ->set('search', 'treffer')
        ->assertSee('treffer.de')
        ->assertDontSee('anderes.de');
});

test('jede Liste aus config/forms ist auf das Modal umgestellt', function () {
    // Bleibt eine Liste auf der alten Seite, faellt das sonst erst auf, wenn
    // jemand dort auf "Neu" klickt und auf einer Seite landet statt im Modal.
    $offen = [];

    foreach (array_keys(config('forms')) as $typ) {
        $index = resource_path("views/$typ/index.blade.php");

        if (! str_contains(file_get_contents($index), 'livewire:objekt-liste')) {
            $offen[] = $typ;
        }

        // Und die Karte bzw. Tabellenzeile muss es geben, sonst rendert die
        // Liste ins Leere.
        if (! view()->exists($typ.'._karte') && ! view()->exists($typ.'._zeile')) {
            $offen[] = $typ.' (ohne Teilstück)';
        }
    }

    expect($offen)->toBe([], 'Noch nicht umgestellt: '.implode(', ', $offen));
});

test('jede umgestellte Liste zeigt einen Bearbeiten-Knopf', function () {
    // Der Fall, der mir durchgerutscht ist: x-table.datarow kannte nur editUrl.
    // Ein unbekanntes Attribut wirft nichts - der Stift fehlte einfach.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    // Einige Factories setzen einen Fremdschluessel auf einen vorhandenen
    // Datensatz - ohne Netz scheitert die WifiFactory, bevor die Liste
    // ueberhaupt gerendert wird.
    Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);

    $ohneKnopf = [];

    foreach (config('forms') as $typ => $einstellung) {
        $this->actingAs(userWithPermissions([$typ.'_viewAny', $typ.'_update']));

        $klasse = $einstellung['model'];
        $werte = ['customer_id' => $customer->id];

        // Einige Tabellen verlangen einen Standort, andere ein Betriebssystem -
        // die Factories setzen dessen Id sonst auf gut Glueck.
        foreach (['site_id' => $site->id, 'operating_system_id' => $os->id] as $spalte => $wert) {
            if (Schema::hasColumn((new $klasse)->getTable(), $spalte)) {
                $werte[$spalte] = $wert;
            }
        }

        $klasse::factory()->create($werte);

        // Entities aufloesen: Blade schreibt die Anfuehrungszeichen im
        // wire:click als &#039;, die Suche nach dem Ausdruck ginge sonst leer aus.
        $html = html_entity_decode(
            Livewire::test(ObjektListe::class, ['typ' => $typ, 'customer' => $customer])->html()
        );

        // Auf wire:click pruefen, nicht auf den blossen Text: Ein unbekanntes
        // Attribut reicht Blade unveraendert ins Element durch - der Name des
        // Ereignisses stand also auch dann im HTML, wenn gar kein Knopf da war.
        if (! str_contains($html, 'wire:click="$dispatch(\'objekt-bearbeiten\'')) {
            $ohneKnopf[] = $typ;
        }
    }

    expect($ohneKnopf)->toBe([], 'Ohne Bearbeiten-Knopf: '.implode(', ', $ohneKnopf));
});

test('kein Teilstueck ist als Karte abgelegt, obwohl es eine Tabellenzeile ist', function () {
    // Genau das war bei Maschine und Ansprechpartner passiert: Die Datei lag als
    // _karte, enthielt aber x-table.datarow. Die generische Liste rendert eine
    // Karte ohne Rahmen - also eine Tabellenzeile ohne Tabelle und ohne
    // Spaltenkoepfe. Es sieht kaputt aus, wirft aber keinen Fehler.
    $falsch = [];

    foreach (array_keys(config('forms')) as $typ) {
        $karte = resource_path("views/$typ/_karte.blade.php");

        if (file_exists($karte) && str_contains(file_get_contents($karte), 'x-table.datarow')) {
            $falsch[] = $typ;
        }
    }

    expect($falsch)->toBe([], 'Als Karte abgelegt, ist aber eine Tabellenzeile: '.implode(', ', $falsch));
});

test('Typen mit IP-Adressen und Zugangsdaten zeigen beide Bloecke beim Bearbeiten', function () {
    // Der gemeldete Verlust: Ueber die alte Seite liessen sich IP-Adressen und
    // Zugangsdaten pflegen, im Modal fehlten sie zunaechst ganz.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $fehlend = [];

    foreach (config('forms') as $typ => $einstellung) {
        if (empty($einstellung['bloecke'])) {
            continue;
        }

        $this->actingAs(userWithPermissions([$typ.'_viewAny', $typ.'_update']));

        $klasse = $einstellung['model'];
        // Nicht jeder Typ hat einen Standortbezug - die E-Mail-Archivierung
        // etwa gehoert zum Kunden, nicht zu einem Standort.
        $werte = ['customer_id' => $customer->id];

        if (Schema::hasColumn((new $klasse)->getTable(), 'site_id')) {
            $werte['site_id'] = $site->id;
        }

        if (Schema::hasColumn((new $klasse)->getTable(), 'operating_system_id')) {
            $werte['operating_system_id'] = OperatingSystem::factory()->create(['name' => 'Debian 13'])->id;
        }

        $objekt = $klasse::factory()->create($werte);

        $html = Livewire::test(ObjektFormular::class, ['typ' => $typ, 'customer' => $customer])
            ->call('bearbeiten', $typ, $objekt->id)
            ->html();

        foreach (['IP-Adressen' => 'IP', 'Zugangsdaten' => 'Zugangsdaten'] as $block => $wort) {
            if (! str_contains($html, $wort)) {
                $fehlend[] = "$typ: $block";
            }
        }
    }

    expect($fehlend)->toBe([], 'Im Bearbeiten-Modal fehlen: '.implode(', ', $fehlend));
});

test('ein Model mit IP-Adressen oder Zugangsdaten ist als Bloecke-Typ eingetragen', function () {
    // Damit der naechste Geraetetyp die Bloecke nicht stillschweigend verliert.
    $ohne = [];

    foreach (config('forms') as $typ => $einstellung) {
        $traits = class_uses_recursive($einstellung['model']);

        $fuehrtBloecke = in_array(HasIpAddresses::class, $traits, true)
            || in_array(HasCredentials::class, $traits, true);

        if ($fuehrtBloecke && empty($einstellung['bloecke'])) {
            $ohne[] = $typ;
        }
    }

    expect($ohne)->toBe([], "Fuehrt IP-Adressen oder Zugangsdaten, hat aber 'bloecke' nicht gesetzt: ".implode(', ', $ohne));
});

test('eine im Block ergaenzte IP-Adresse meldet sich an die Liste', function () {
    // Gemeldet: Nach dem Hinzufuegen stand die Tabellenzeile weiter auf dem
    // alten Stand. Die Bloecke speicherten, sagten aber niemandem Bescheid.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['machine_viewAny', 'machine_update', 'network_viewAny']));

    $maschine = Machine::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
    ]);

    Livewire::test(DeviceIpAddresses::class, [
        'model' => $maschine, 'customer' => $customer, 'eingebettet' => true,
    ])
        ->set('address', '10.10.10.42')
        ->call('add')
        ->assertDispatched('geraet-geaendert');

    // Und die Liste zeigt sie danach auch.
    Livewire::test(ObjektListe::class, ['typ' => 'machine', 'customer' => $customer])
        ->assertSee('10.10.10.42');
});

test('die Liste zeichnet auf die Meldung der Bloecke neu', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['machine_viewAny']));

    $maschine = Machine::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
    ]);

    $liste = Livewire::test(ObjektListe::class, ['typ' => 'machine', 'customer' => $customer])
        ->assertDontSee('10.10.10.43');

    $maschine->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.10.10.43']);

    // Ohne den Zuhoerer bliebe die Zeile auf dem Stand von vorhin.
    $liste->dispatch('geraet-geaendert')->assertSee('10.10.10.43');
});

test('ein Standort des eigenen Kunden wird im Modal angenommen', function () {
    // Gemeldet: "Die Auswahl fuer Standort gehoert nicht zu diesem Kunden" - und
    // zwar bei einem Standort, der sehr wohl dazu gehoerte. Die Regel holt den
    // Kunden aus der Route; die heisst bei Livewire livewire.update und kennt
    // ihn nicht, also war er null und die Pruefung schlug immer fehl.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Halle Ost']);
    $this->actingAs(userWithPermissions(['machine_create', 'machine_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'machine', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'CNC-Fraese 7')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertSet('offen', false);

    expect(Machine::where('name', 'CNC-Fraese 7')->sole()->site_id)->toBe($site->id);
});

test('ein fremder Standort wird im Modal weiterhin abgelehnt', function () {
    // Der Schutz muss bleiben - er war ja der Sinn der Regel.
    $customer = Customer::factory()->create();
    $fremd = Customer::factory()->create();
    $fremderStandort = Site::factory()->create(['customer_id' => $fremd->id]);
    $this->actingAs(userWithPermissions(['machine_create']));

    Livewire::test(ObjektFormular::class, ['typ' => 'machine', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $fremderStandort->id)
        ->set('form.name', 'CNC-Fremd')
        ->call('speichern')
        ->assertHasErrors('form.site_id');

    expect(Machine::where('name', 'CNC-Fremd')->exists())->toBeFalse();
});

test('Anlegen, Speichern und Loeschen quittieren sich mit einer Meldung', function () {
    // Gemeldet: Die Einblendung unten rechts fehlte. Sie hoert auf 'hinweis' mit
    // einem text-Parameter - mein erstes 'success' hat niemand mitbekommen, und
    // ein Ereignis ins Leere wirft nichts.
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_create', 'domain_update', 'domain_delete', 'domain_viewAny']));

    $modal = Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'meldung.de')
        ->call('speichern')
        ->assertDispatched('hinweis', text: 'Domain angelegt.');

    $domain = Domain::where('name', 'meldung.de')->sole();

    $modal->call('bearbeiten', 'domain', $domain->id)
        ->set('form.registrar', 'Hetzner')
        ->call('speichern')
        ->assertDispatched('hinweis', text: 'Domain gespeichert.');

    $modal->call('bearbeiten', 'domain', $domain->id)
        ->call('loeschen')
        ->assertDispatched('hinweis', text: 'Domain gelöscht.');
});

test('leere Felder werden als null gespeichert, nicht als Leerstring', function () {
    // MySQL lehnt '' fuer eine date-Spalte ab: "Incorrect date value" - ein 500er
    // beim Anlegen einer Domain ohne Ablaufdatum. SQLite laesst es durch, der
    // Fehler war in den Tests also nicht zu sehen. Geprueft wird deshalb der
    // Wert selbst.
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_create', 'domain_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'ohnedatum.de')
        ->call('speichern')
        ->assertHasNoErrors();

    $roh = DB::table('domains')->where('name', 'ohnedatum.de')->first();

    expect($roh->expiry_date)->toBeNull();
    expect($roh->registrar)->toBeNull();
});

test('die Netz-Auswahl zeigt VLAN-Nummer und Bezeichnung', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['wifi_create', 'wifi_viewAny']));

    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'vlanId' => 20, 'description' => 'Clients',
    ]);

    // Ohne die Nummer sind zwei Netze mit aehnlicher Bezeichnung nicht zu
    // unterscheiden - im Netz spricht ohnehin jeder von der VLAN-Nummer.
    Livewire::test(ObjektFormular::class, ['typ' => 'wifi', 'customer' => $customer])
        ->call('neu')
        ->assertSee('VLAN 20 · Clients');
});

test('ein Netz ohne Bezeichnung laesst kein einsames Trennzeichen stehen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['wifi_create', 'wifi_viewAny']));

    Network::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'vlanId' => 99, 'description' => null,
    ]);

    Livewire::test(ObjektFormular::class, ['typ' => 'wifi', 'customer' => $customer])
        ->call('neu')
        ->assertSee('VLAN 99')
        ->assertDontSee('VLAN 99 ·');
});

test('Typen mit eigener Bearbeitungs-Oberflaeche bleiben bei ihrer Seite', function () {
    // Der Serverschrank hatte einen Editor mit Drag-und-Drop im
    // Bearbeiten-Formular. Im Modal fehlte der ersatzlos - und weil die Liste
    // trotzdem rendert und alle Felder da sind, faellt es in keinem anderen
    // Test auf. Ein Livewire-Block im alten Formular ist deshalb das Zeichen,
    // dass der Typ eine eigene Seite braucht.
    //
    // Ausgenommen sind die beiden Bloecke, die das Modal selbst mitbringt.
    $uebernommen = ['device-ip-addresses', 'device-credentials'];

    $falsch = [];

    foreach (array_keys(config('forms')) as $typ) {
        $edit = resource_path("views/$typ/edit.blade.php");

        if (! file_exists($edit)) {
            continue;
        }

        $inhalt = file_get_contents($edit);

        preg_match_all('/<livewire:([\w-]+)/', $inhalt, $treffer);
        $bloecke = array_diff(array_unique($treffer[1]), $uebernommen);

        // Die Dienste-Auswahl stand hier eine Zeit lang mit drin: Sie bringt
        // Katalog, Kacheln und ein Freitextfeld mit, und im Modal blieb davon
        // nur ein Textfeld. Inzwischen bindet das Modal dieselbe Komponente ein
        // (Feldart "dienste"), deshalb ist sie kein Ausschlussgrund mehr.

        foreach ($bloecke as $block) {
            $falsch[] = "$typ ($block)";
        }
    }

    expect($falsch)->toBe([], 'Diese Typen bringen eine eigene Oberfläche mit und gehören nicht ins Modal: '.implode(', ', $falsch));
});

test('die Dienste-Auswahl steht im Modal, nicht nur ein Textfeld', function () {
    // Der Fall, der mir zweimal durchgerutscht ist: Die Felder waren
    // vollstaendig, die Oberflaeche nicht. Ein Textfeld statt Katalog und
    // Kacheln faellt in keinem Feld-Test auf.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    // Keine ServiceFactory im Projekt - der Katalogeintrag wird direkt angelegt.
    Service::create(['name' => 'Docker', 'description' => 'Container']);

    $this->actingAs(userWithPermissions(['vm_viewAny', 'vm_update']));

    $vm = VM::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => $os->id,
    ]);

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'vm', 'customer' => $customer])
        ->call('bearbeiten', 'vm', $vm->id)
        ->html();

    expect($html)->toContain('Aus dem Katalog')
        ->and($html)->toContain('Noch keine Dienste')
        ->and($html)->toContain('Nicht im Katalog');
});

test('das Server-Modal ist zweispaltig und zeigt alle Sonderfelder', function () {
    // Zwanzig Felder untereinander waeren eine Scrollstrecke, bei der man den
    // Anfang aus den Augen verliert.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    Service::create(['name' => 'Docker', 'description' => 'Container']);

    $this->actingAs(userWithPermissions(['server_viewAny', 'server_update']));

    $server = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => $os->id,
    ]);

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'server', 'customer' => $customer])
        ->call('bearbeiten', 'server', $server->id)
        ->html();

    expect($html)->toContain('sm:grid-cols-2')
        // Die Dienste spannen ueber beide Spalten - Katalog und Kacheln passen
        // nicht in eine halbe.
        ->and($html)->toContain('sm:col-span-2')
        ->and($html)->toContain('Aus dem Katalog')
        // Einbautiefe und Hoeheneinheiten nur beim Rackeinbau.
        ->and($html)->toContain("form.form_factor === 'rack'")
        ->and($html)->toContain('Weitere IP-Adressen');
});

test('einspaltige Typen bleiben einspaltig', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_create']));

    // Sechs Felder brauchen keine zweite Spalte - sonst steht das Modal
    // unnoetig breit im Bild.
    $html = Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->html();

    expect($html)->not->toContain('sm:grid-cols-2');
});

test('eine feste Optionsliste ist beim Anlegen vorbelegt', function () {
    // Gemeldet als "Server geht nicht": Das Auswahlfeld zeigte "19-Zoll", der
    // Wert im Formular war aber leer. Folge: Die Felder, die an der Bauform
    // haengen, blieben unsichtbar, und beim Speichern kam "ist erforderlich"
    // fuer etwas, das sichtbar ausgefuellt aussah.
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['server_create']));

    Livewire::test(ObjektFormular::class, ['typ' => 'server', 'customer' => $customer])
        ->call('neu')
        ->assertSet('form.form_factor', 'rack')
        ->assertSet('form.full_depth', '1');
});

test('Fehlermeldungen nennen die Beschriftung, nicht den Feldnamen', function () {
    // "Das Feld form.form factor ist erforderlich" - der Request kennt fuer
    // dieses Feld keine Bezeichnung, also muss die Felddefinition einspringen.
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['server_create']));

    $modal = Livewire::test(ObjektFormular::class, ['typ' => 'server', 'customer' => $customer])
        ->call('neu')
        ->set('form.form_factor', '')
        ->call('speichern');

    $fehler = $modal->errors()->get('form.form_factor');

    expect($fehler[0] ?? '')->toContain('Bauform')
        ->and($fehler[0] ?? '')->not->toContain('form factor');
});

test('ein leeres Pflichtfeld mit Standardwert scheitert nicht an null', function () {
    // Gemeldet als "Server geht nicht": Das Anlegen brach still ab. Im Log stand
    // "Column 'height_units' cannot be null" - mein Fix, leere Felder als null
    // zu speichern, traf hier auf eine Spalte, die kein null zulaesst. Jetzt
    // wird der Schluessel weggelassen und die Datenbank setzt ihren Standard.
    //
    // SQLite ist an dieser Stelle strenger als sonst und lehnt null ebenfalls
    // ab, der Test greift also auch hier.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['server_create', 'server_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'server', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'SRV-OhneHE')
        ->set('form.operating_system_id', $os->id)
        ->set('form.height_units', '')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertSet('offen', false);

    $server = Server::where('name', 'SRV-OhneHE')->sole();

    expect($server->height_units)->not->toBeNull();
});

test('eine Securepoint-Firewall laesst sich im Modal anlegen', function () {
    // Der Weg, an dem der Server gescheitert ist: anlegen, nicht bearbeiten.
    // Dort ist alles leer, und genau da zeigen sich Vorbelegung, Pflichtfelder
    // und Standardwerte.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['firewall_create', 'firewall_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'firewall', 'customer' => $customer])
        ->call('neu')
        ->assertSet('form.form_factor', 'appliance')
        ->set('form.site_id', $site->id)
        ->set('form.name', 'FW-Modal')
        ->set('form.manufacturer', 'Securepoint GmbH')
        ->set('form.usc_pin', '112233')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertSet('offen', false);

    $firewall = Firewall::where('name', 'FW-Modal')->sole();

    expect($firewall->usc_pin)->toBe('112233')
        ->and($firewall->height_units)->not->toBeNull();
});

test('die Securepoint-Felder erscheinen nur beim passenden Hersteller', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['firewall_create']));

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'firewall', 'customer' => $customer])
        ->call('neu')
        ->html();

    // Der Hersteller ist Freitext - deshalb wird verglichen, nicht auf
    // Gleichheit geprueft: "Securepoint GmbH" ist dasselbe wie "Securepoint".
    expect($html)->toContain("toLowerCase().includes('securepoint')")
        // Und der Ausdruck darf nicht escaped im Attribut landen, sonst
        // vergleicht der Browser gegen &#039;.
        ->and($html)->not->toContain('&#039;securepoint&#039;');
});

test('ein AD-Benutzer laesst sich im Modal anlegen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['aduser_create', 'aduser_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('neu')
        // Ein neuer Benutzer ist aktiv, nicht "nichts ausgewaehlt".
        ->assertSet('form.enabled', '1')
        ->set('form.username', 'm.mustermann')
        ->set('form.firstName', 'Max')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertSet('offen', false);

    expect(ADUser::where('username', 'm.mustermann')->sole()->enabled)->toBeTruthy();
});

test('ein technisches Feld wird nicht gezeichnet, bleibt aber erhalten', function () {
    // "hidden" steuert, ob der Benutzer in Listen auftaucht - es gehoert ins
    // Formular, aber nicht vor die Augen. Wuerde es beim Speichern verloren
    // gehen, taeuchten verborgene Benutzer nach jeder Bearbeitung wieder auf.
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));

    $benutzer = ADUser::factory()->create([
        'customer_id' => $customer->id, 'username' => 'verborgen', 'hidden' => 1,
    ]);

    $modal = Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id);

    expect($modal->html())->not->toContain('form.hidden');

    $modal->set('form.firstName', 'Geändert')->call('speichern');

    expect($benutzer->fresh()->hidden)->toEqual(1);
});

test('ein Internetanschluss laesst sich im Modal anlegen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['internetconnection_create', 'internetconnection_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'internetconnection', 'customer' => $customer])
        ->call('neu')
        ->set('form.site_id', $site->id)
        ->set('form.provider', 'Telekom')
        ->set('form.bandwidth_down', '250')
        ->set('form.bandwidth_up', '40')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertSet('offen', false);

    $anschluss = InternetConnection::where('provider', 'Telekom')->sole();

    // Die Einheit ist Beschriftung, kein Eingabewert - sie darf nicht mit in
    // die Datenbank wandern.
    expect($anschluss->bandwidth_down)->toBe('250')
        ->and($anschluss->bandwidth_up)->toBe('40');
});

test('die Bandbreitenfelder tragen ihre Einheit', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['internetconnection_create']));

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'internetconnection', 'customer' => $customer])
        ->call('neu')
        ->html();

    expect(substr_count($html, 'Mbit/s'))->toBe(2)
        ->and($html)->toContain('wire:model="form.bandwidth_down"');
});

test('eine E-Mail-Archivierung laesst sich im Modal anlegen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['securepointuma_create', 'securepointuma_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'securepointuma', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'UMA-Modal')
        ->set('form.manufacturer', 'Reddoxx')
        ->set('form.urlAdmin', 'https://10.0.0.9:11115')
        ->set('form.username', 'admin')
        ->set('form.password', 'Geheim!2026')
        ->set('form.encryptionkey', 'AAAA-BBBB-CCCC-DDDD')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertSet('offen', false);

    $uma = SecurepointUMA::where('name', 'UMA-Modal')->sole();

    expect($uma->encryptionkey)->toBe('AAAA-BBBB-CCCC-DDDD');

    // Der Schluessel gehoert verschluesselt in die Tabelle - wer die Datenbank
    // sieht, soll ihn nicht lesen koennen.
    $roh = DB::table('securepoint_umas')->where('id', $uma->id)->value('encryptionkey');
    expect($roh)->not->toBe('AAAA-BBBB-CCCC-DDDD');
});

test('das Modal kommt ohne customer-Relation am Model aus', function () {
    // Die E-Mail-Archivierung hat keine customer()-Relation. Die eingebetteten
    // Bloecke bekommen den Kunden deshalb von der Komponente, nicht ueber das
    // Objekt - sonst bricht das Bearbeiten mit "property id on null".
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['securepointuma_update', 'securepointuma_viewAny']));

    $uma = SecurepointUMA::factory()->create(['customer_id' => $customer->id]);

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'securepointuma', 'customer' => $customer])
        ->call('bearbeiten', 'securepointuma', $uma->id)
        ->html();

    expect($html)->toContain('Weitere IP-Adressen');
});

test('ein Standort laesst sich im Modal anlegen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['site_create', 'site_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'site', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'Lager Süd')
        ->set('form.street', 'Industrieweg')
        ->set('form.house_number', '7')
        ->set('form.zip', '21079')
        ->set('form.city', 'Hamburg')
        ->call('speichern')
        ->assertHasNoErrors();

    $standort = Site::where('name', 'Lager Süd')->sole();

    expect($standort->city)->toBe('Hamburg')
        ->and($standort->customer_id)->toBe($customer->id);
});

test('der Standort laedt die Seite neu, andere Typen nicht', function () {
    // Der Standort steht im Umschalter der Seitenleiste und in der Auswahl
    // jedes Geraeteformulars. Beides liegt ausserhalb der Komponente und zeigte
    // sonst weiter den alten Stand - ein neuer Standort waere erst nach einem
    // Neuladen zu gebrauchen.
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['site_create', 'domain_create']));

    Livewire::test(ObjektFormular::class, ['typ' => 'site', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'Neuer Standort')
        ->call('speichern')
        ->assertJs('window.location.reload()');

    // Bei einer Domain waere das Neuladen unnoetig - sie steht nirgends sonst.
    expect(config('forms.domain.seite_neu_laden') ?? false)->toBeFalse();
});

test('eine Datei laesst sich im Modal hochladen', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create(['slug' => 'testkunde']);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $this->actingAs(userWithPermissions(['licensewindows_create', 'licensewindows_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'licensewindows', 'customer' => $customer])
        ->call('neu')
        ->set('form.operating_system_id', $os->id)
        ->set('form.key', 'XXXXX-YYYYY-ZZZZZ')
        ->set('form.file_name', 'Lizenzurkunde')
        ->set('datei', UploadedFile::fake()->create('urkunde.pdf', 12))
        ->call('speichern')
        ->assertHasNoErrors();

    $lizenz = LicenseWindows::where('key', 'XXXXX-YYYYY-ZZZZZ')->sole();

    expect($lizenz->file_path)->not->toBeNull();
    // Der Ablageort folgt dem bisherigen Controller: {kunde}/{typ}/ - der Slug
    // kommt vom Model, nicht aus der Vorgabe der Factory.
    expect($lizenz->file_path)->toStartWith($customer->fresh()->slug.'/licensewindows/');
    Storage::disk('local')->assertExists($lizenz->file_path);
});

test('eine neue Datei ersetzt die alte und laesst keine Leiche zurueck', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create(['slug' => 'testkunde']);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $this->actingAs(userWithPermissions(['licensewindows_update', 'licensewindows_viewAny']));

    $lizenz = LicenseWindows::create([
        'customer_id' => $customer->id, 'operating_system_id' => $os->id,
        'key' => 'ALT', 'file_name' => 'Alt', 'file_path' => $customer->slug.'/licensewindows/alt.pdf',
    ]);
    Storage::disk('local')->put($customer->slug.'/licensewindows/alt.pdf', 'Inhalt');

    Livewire::test(ObjektFormular::class, ['typ' => 'licensewindows', 'customer' => $customer])
        ->call('bearbeiten', 'licensewindows', $lizenz->id)
        ->set('datei', UploadedFile::fake()->create('neu.pdf', 12))
        ->call('speichern')
        ->assertHasNoErrors();

    $neu = $lizenz->fresh();

    expect($neu->file_path)->not->toBe($customer->slug.'/licensewindows/alt.pdf');
    Storage::disk('local')->assertExists($neu->file_path);
    // Ohne das Loeschen sammeln sich Dateien, die niemand mehr zuordnen kann.
    Storage::disk('local')->assertMissing($customer->slug.'/licensewindows/alt.pdf');
});

test('ohne neue Datei bleibt die hinterlegte erhalten', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create(['slug' => 'testkunde']);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $this->actingAs(userWithPermissions(['licensewindows_update', 'licensewindows_viewAny']));

    $lizenz = LicenseWindows::create([
        'customer_id' => $customer->id, 'operating_system_id' => $os->id,
        'key' => 'ALT', 'file_path' => $customer->slug.'/licensewindows/bleibt.pdf',
    ]);

    // Wer nur den Key korrigiert, darf die Datei nicht verlieren.
    Livewire::test(ObjektFormular::class, ['typ' => 'licensewindows', 'customer' => $customer])
        ->call('bearbeiten', 'licensewindows', $lizenz->id)
        ->set('form.key', 'NEU')
        ->call('speichern');

    expect($lizenz->fresh()->file_path)->toBe($customer->slug.'/licensewindows/bleibt.pdf');
});

test('die Dateiwahl schlaegt eine Bezeichnung vor', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create();
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $this->actingAs(userWithPermissions(['licensewindows_create']));

    Livewire::test(ObjektFormular::class, ['typ' => 'licensewindows', 'customer' => $customer])
        ->call('neu')
        ->set('datei', UploadedFile::fake()->create('Lizenzurkunde 2026.pdf', 12))
        // Ohne Endung: Die kommt beim Ablegen von selbst dazu.
        ->assertSet('form.file_name', 'Lizenzurkunde 2026');
});

test('eine eingetragene Bezeichnung bleibt beim Hochladen stehen', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create();
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $this->actingAs(userWithPermissions(['licensewindows_create']));

    // Wer schon etwas eingetragen hat, hat sich dabei etwas gedacht.
    Livewire::test(ObjektFormular::class, ['typ' => 'licensewindows', 'customer' => $customer])
        ->call('neu')
        ->set('form.file_name', 'Von Hand benannt')
        ->set('datei', UploadedFile::fake()->create('egal.pdf', 12))
        ->assertSet('form.file_name', 'Von Hand benannt');
});

test('eine CAL-Lizenz laesst sich mit Datei anlegen', function () {
    Storage::fake('local');

    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['licenseaccess_create', 'licenseaccess_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'licenseaccess', 'customer' => $customer])
        ->call('neu')
        ->set('form.name', 'RDS CAL 2022')
        ->set('form.key', 'AAAAA-BBBBB')
        ->set('datei', UploadedFile::fake()->create('CAL Nachweis.pdf', 12))
        // Der Vorschlag gilt fuer jeden Typ mit Dateifeld, nicht nur fuer Windows.
        ->assertSet('form.file_name', 'CAL Nachweis')
        ->call('speichern')
        ->assertHasNoErrors();

    $lizenz = LicenseAccess::where('name', 'RDS CAL 2022')->sole();

    expect($lizenz->file_path)->toStartWith($customer->fresh()->slug.'/licenseaccess/');
    Storage::disk('local')->assertExists($lizenz->file_path);
});
