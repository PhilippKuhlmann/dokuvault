<?php

use App\Livewire\DocumentationWizard;
use App\Models\ADDomain;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\Network;
use App\Models\OperatingSystem;
use App\Models\Router;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use App\Models\Wifi;
use Livewire\Livewire;

/**
 * Deckt genau die Risiken ab, die beim Entwurf gefunden wurden (siehe Plan):
 * BelongsToCustomer funktioniert unter /livewire/update nicht (R1), isCustomer-Middleware läuft
 * bei Livewire-Aktionen nicht (R2), $guarded = [] macht $form angreifbar (R3), verschlüsselnde
 * Setter dürfen keinen Leerstring bekommen (R4), operating_system_id/wifis.password sind in der
 * DB strenger als die FormRequests (R8).
 */
test('jeder Schritt fragt jedes Pflichtfeld seines FormRequest ab', function () {
    // Schutz gegen den Fehler, den dieses Feature beheben soll: ein Schritt, der nicht
    // speicherbar ist, weil ein Pflichtfeld fehlt.
    foreach (config('custom.wizard_steps') as $step) {
        $request = new $step['request'];
        $required = collect($request->rules())
            ->filter(fn ($rules, $name) => $name !== 'site_id'
                && collect((array) $rules)->contains(fn ($r) => $r === 'required' || (is_string($r) && str_starts_with($r, 'required'))))
            ->keys();

        $askedFields = array_column($step['fields'], 'name');
        $missing = $required->diff($askedFields)->values()->all();

        expect($missing)->toBe([], "Schritt '{$step['key']}' fragt Pflichtfeld(er) nicht ab: ".implode(', ', $missing));
    }
});

test('Seite lädt für Nutzer mit mindestens einem Anlege-Recht', function () {
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();

    $this->get("/{$customer->slug}/assistent")->assertStatus(200);
});

test('ohne jedes Anlege-Recht gibt es 403', function () {
    $this->actingAs(userWithPermissions(['site_viewAny'])); // nur lesen, nichts anlegen
    $customer = Customer::factory()->create();

    $this->get("/{$customer->slug}/assistent")->assertStatus(403);
});

test('Schritte ohne Berechtigung werden nicht angezeigt', function () {
    $this->actingAs(userWithPermissions(['server_create']));
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->assertDontSee('Router')
        ->assertDontSee('Standorte');
});

test('ein Eintrag wird sofort angelegt, der Schritt bleibt aber stehen', function () {
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'Zentrale Hamburg')
        ->set('form.city', 'Hamburg')
        ->call('save')
        ->assertHasNoErrors();

    expect(Site::where('customer_id', $customer->id)->count())->toBe(1);

    $run = DocumentationRun::where('customer_id', $customer->id)->first();
    expect($run->current_step)->toBe('site');
});

test('Standort setzt run.site_id, Folgeschritt erbt sie', function () {
    $this->actingAs(userWithPermissions(['site_create', 'router_create']));
    $customer = Customer::factory()->create();

    $component = Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'Zentrale Hamburg')
        ->call('save')
        ->call('nextStep');

    $site = Site::where('customer_id', $customer->id)->firstOrFail();
    $run = DocumentationRun::where('customer_id', $customer->id)->first();
    expect($run->site_id)->toBe($site->id);

    $component
        ->set('form.name', 'RTR-Core')
        ->set('form.ip_address', '10.10.30.1')
        ->set('form.port', '443')
        ->set('form.username', 'admin')
        ->set('form.password', 'geheim123')
        ->call('save')
        ->assertHasNoErrors();

    $router = Router::where('customer_id', $customer->id)->first();
    expect($router)->not->toBeNull();
    expect($router->site_id)->toBe($site->id);

    // Die IP ist keine Spalte am Geraet mehr: Der Assistent legt sie im Block
    // "Weitere IP-Adressen" an, wo sie auch das Formular erwartet.
    expect($router->ipAddresses()->pluck('address')->all())->toBe(['10.10.30.1']);
});

test('Pflichtfeld leer -> Validierungsfehler, kein Datensatz', function () {
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors('form.name');

    expect(Site::where('customer_id', $customer->id)->count())->toBe(0);
});

test('WLAN mit VLAN eines fremden Kunden wird abgelehnt (BelongsToCustomer-Ersatz)', function () {
    $this->actingAs(userWithPermissions(['wifi_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $run = DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'site_id' => $site->id, 'current_step' => 'wifi',
        'completed_steps' => [], 'skipped_steps' => [],
    ]);

    $fremderKunde = Customer::factory()->create();
    $fremdeSite = Site::factory()->create(['customer_id' => $fremderKunde->id]);
    $fremdesNetz = Network::factory()->create(['customer_id' => $fremderKunde->id, 'site_id' => $fremdeSite->id]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.ssid', 'Gäste-WLAN')
        ->set('form.password', 'geheim123')
        ->set('form.network_id', $fremdesNetz->id)
        ->set('form.encryption', 'WPA2')
        ->call('save')
        ->assertHasErrors('form.network_id');

    expect(Wifi::where('customer_id', $customer->id)->count())->toBe(0);
});

test('WLAN mit eigenem VLAN wird angenommen', function () {
    $this->actingAs(userWithPermissions(['wifi_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $network = Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'site_id' => $site->id, 'current_step' => 'wifi',
        'completed_steps' => [], 'skipped_steps' => [],
    ]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.ssid', 'Mitarbeiter-WLAN')
        ->set('form.password', 'geheim123')
        ->set('form.network_id', $network->id)
        ->set('form.encryption', 'WPA2')
        ->call('save')
        ->assertHasNoErrors();

    expect(Wifi::where('customer_id', $customer->id)->count())->toBe(1);
});

test('Massenzuweisung: customer_id und hidden aus dem Formular werden ignoriert', function () {
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();
    $fremderKunde = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'Filiale')
        ->set('form.customer_id', $fremderKunde->id)
        ->set('form.id', 99999)
        ->call('save')
        ->assertHasNoErrors();

    $site = Site::where('name', 'Filiale')->firstOrFail();
    expect($site->customer_id)->toBe($customer->id);
    expect($site->id)->not->toBe(99999);
});

test('Router-Passwort wird verschlüsselt gespeichert', function () {
    $this->actingAs(userWithPermissions(['site_create', 'router_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'site_id' => $site->id, 'current_step' => 'router',
        'completed_steps' => [], 'skipped_steps' => [],
    ]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'RTR-Core')
        ->set('form.ip', '10.10.30.1')
        ->set('form.port', '443')
        ->set('form.username', 'admin')
        ->set('form.password', 'geheim123')
        ->call('save')
        ->assertHasNoErrors();

    $raw = DB::table('routers')->where('customer_id', $customer->id)->first();
    expect($raw->password)->not->toBe('geheim123');

    $router = Router::where('customer_id', $customer->id)->first();
    expect($router->password)->toBe('geheim123');
});

test('leeres optionales Feld bleibt NULL statt Chiffretext eines Leerstrings', function () {
    $this->actingAs(userWithPermissions(['site_create', 'nas_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'site_id' => $site->id, 'current_step' => 'nas',
        'completed_steps' => [], 'skipped_steps' => [],
    ]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', '') // NAS: 'name' ist optional
        ->set('form.ip1', '10.10.30.20')
        ->set('form.username', 'admin')
        ->set('form.password', 'geheim123')
        ->call('save')
        ->assertHasNoErrors();

    $raw = DB::table('nas')->where('customer_id', $customer->id)->first();
    expect($raw->name)->toBeNull();
});

test('Fortschritt wird in der Datenbank gespeichert und in neuer Instanz fortgesetzt', function () {
    $user = userWithPermissions(['site_create', 'contactperson_create']);
    $this->actingAs($user);
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->call('nextStep');

    $run = DocumentationRun::where('customer_id', $customer->id)->where('user_id', $user->id)->first();
    expect($run->current_step)->toBe('contactperson');
    expect($run->completed_steps)->toContain('site');

    // Frische Komponenten-Instanz (z. B. nach Logout/Login) nimmt denselben Lauf auf.
    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->assertSee('Ansprechpartner');

    expect(DocumentationRun::where('customer_id', $customer->id)->count())->toBe(1);
});

test('Überspringen landet in skipped_steps und legt nichts an', function () {
    $this->actingAs(userWithPermissions(['site_create', 'contactperson_create']));
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->call('skipStep');

    $run = DocumentationRun::where('customer_id', $customer->id)->first();
    expect($run->skipped_steps)->toContain('site');
    expect($run->completed_steps ?? [])->not->toContain('site');
    expect(Site::where('customer_id', $customer->id)->count())->toBe(0);
});

test('Mandantentrennung greift auch bei der Livewire-Komponente, nicht nur beim Seitenaufruf', function () {
    // isCustomer-Middleware läuft nicht unter /livewire/update - guard() prüft deshalb bei
    // JEDEM render() neu, nicht nur bei expliziten Aktionen. Ein Kundennutzer mit falscher
    // customer_id bekommt daher schon beim ersten Rendern ein 403, nicht erst bei ->call().
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();

    $user = userWithPermissions(['site_create']);
    $user->update(['customer_id' => $customerA->id]);
    $this->actingAs($user);

    Livewire::test(DocumentationWizard::class, ['customer' => $customerB])
        ->assertForbidden();
});

test('Einträge eines fremden Kunden erscheinen nicht in der Liste', function () {
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();
    $fremderKunde = Customer::factory()->create();
    Site::factory()->create(['customer_id' => $fremderKunde->id, 'name' => 'Fremde Filiale']);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->assertDontSee('Fremde Filiale');
});

test('Abschluss setzt completed_at, ein neuer Durchlauf ist danach wieder möglich', function () {
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->call('finish');

    $run = DocumentationRun::where('customer_id', $customer->id)->first();
    expect($run->completed_at)->not->toBeNull();

    // Neues mount() findet keinen offenen Lauf mehr und legt einen frischen an.
    Livewire::test(DocumentationWizard::class, ['customer' => $customer]);
    expect(DocumentationRun::where('customer_id', $customer->id)->count())->toBe(2);
});

test('Server ohne Betriebssystem schlägt fehl statt die NOT-NULL-Spalte zu verletzen', function () {
    // operating_system_id ist in servers NOT NULL, ServerRequest verlangt es zwar auch,
    // aber die Regression betrifft VM (siehe nächster Test) - hier nur Basisverhalten sichern.
    $this->actingAs(userWithPermissions(['site_create', 'server_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'site_id' => $site->id, 'current_step' => 'server',
        'completed_steps' => [], 'skipped_steps' => [],
    ]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'SRV-01')
        ->call('save')
        ->assertHasErrors('form.operating_system_id');

    expect(Server::where('customer_id', $customer->id)->count())->toBe(0);
});

test('VM ohne Betriebssystem schlägt fehl, obwohl VMRequest das Feld nicht verlangt', function () {
    // Regression: vms.operating_system_id ist NOT NULL, VMRequest::rules() lässt das Feld
    // aber leer durch ('operating_system_id' => ''). Reine FormRequest-Übernahme würde hier
    // eine SQL-Exception statt eines Validierungsfehlers produzieren.
    $this->actingAs(userWithPermissions(['site_create', 'vm_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'site_id' => $site->id, 'current_step' => 'vm',
        'completed_steps' => [], 'skipped_steps' => [],
    ]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'VM-01')
        ->call('save')
        ->assertHasErrors('form.operating_system_id');

    expect(VM::where('customer_id', $customer->id)->count())->toBe(0);

    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'VM-01')
        ->set('form.operating_system_id', $os->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(VM::where('customer_id', $customer->id)->count())->toBe(1);
});

test('AD-Domäne und Backup werden ohne site_id angelegt (Modelle haben keine Spalte dafür)', function () {
    $this->actingAs(userWithPermissions(['addomain_create', 'backup_create']));
    $customer = Customer::factory()->create();

    $run = DocumentationRun::create([
        'customer_id' => $customer->id, 'user_id' => auth()->id(),
        'current_step' => 'addomain', 'completed_steps' => [], 'skipped_steps' => [],
    ]);

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.domain', 'firma.local')
        ->set('form.netbios', 'FIRMA')
        ->set('form.dsrmpassword', 'geheim123')
        ->call('save')
        ->assertHasNoErrors();

    expect(ADDomain::where('customer_id', $customer->id)->count())->toBe(1);
});

test('schon erfasste Eintraege verlinken auf ihr Bearbeiten-Formular', function () {
    $this->actingAs(userWithPermissions(['site_create', 'router_create', 'router_update']));
    $customer = Customer::factory()->create();

    $inhalt = Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'Zentrale')
        ->call('save')
        // Der Nutzer hat nur site_ und router_create - die Schritte dazwischen
        // zeigt der Assistent gar nicht erst an.
        ->call('nextStep')
        ->set('form.name', 'RTR-Core')
        ->set('form.port', '443')
        ->set('form.username', 'admin')
        ->set('form.password', 'geheim123')
        ->call('save')
        ->html();

    $router = Router::where('customer_id', $customer->id)->firstOrFail();

    // Neuer Tab, damit der angefangene Durchlauf nicht verloren geht.
    expect($inhalt)->toContain(route('router.edit', [$customer, $router], false));
    expect($inhalt)->toContain('target="_blank"');
});

test('man kann zwischen den Schritten hin und her springen', function () {
    $this->actingAs(userWithPermissions(['site_create', 'router_create']));
    $customer = Customer::factory()->create();

    $component = Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->set('form.name', 'Zentrale')
        ->call('save')
        ->call('nextStep');

    $run = DocumentationRun::where('customer_id', $customer->id)->firstOrFail();
    expect($run->fresh()->current_step)->toBe('router');

    // Zurueck zum Standort und wieder vor.
    $component->call('gotoStep', 'site');
    expect($run->fresh()->current_step)->toBe('site');

    $component->call('gotoStep', 'router');
    expect($run->fresh()->current_step)->toBe('router');
});

test('ein Sprung auf einen gesperrten Schritt wird verworfen', function () {
    // Ohne server_create darf der Server-Schritt gar nicht erreichbar sein -
    // $key kommt vom Client.
    $this->actingAs(userWithPermissions(['site_create']));
    $customer = Customer::factory()->create();

    Livewire::test(DocumentationWizard::class, ['customer' => $customer])
        ->call('gotoStep', 'server');

    $run = DocumentationRun::where('customer_id', $customer->id)->firstOrFail();
    expect($run->current_step)->toBe('site');
});
