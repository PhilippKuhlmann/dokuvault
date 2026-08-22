<?php

use App\Livewire\AdminProtokoll;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Firewall;
use App\Models\Site;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

test('die Suche findet ueber den Objektnamen', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'gesucht.de']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'unauffaellig.de']);

    Livewire::test(AdminProtokoll::class)
        ->set('suche', 'gesucht')
        ->assertSee('gesucht.de')
        ->assertDontSee('unauffaellig.de');
});

test('die Suche greift auch auf Werte zu, nicht nur auf Namen', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'irgendwas.de', 'registrar' => 'Hetzner']);

    // In einem Protokoll sucht man nach dem, woran man sich erinnert - nicht
    // nach dem Feld, in dem es stand.
    Livewire::test(AdminProtokoll::class)
        ->set('suche', 'Hetzner')
        ->assertSee('irgendwas.de');
});

test('der Ereignisfilter trennt Anlegen von Loeschen', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    $bleibt = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'bleibt.de']);
    $geht = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'geht.de']);
    $geht->delete();

    Livewire::test(AdminProtokoll::class)
        ->set('ereignis', 'deleted')
        ->assertSee('geht.de')
        ->assertDontSee('bleibt.de');
});

test('der Artfilter trennt die Objektarten', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'nur-domain.de']);
    Firewall::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'nur-firewall']);

    Livewire::test(AdminProtokoll::class)
        ->set('art', Firewall::class)
        ->assertSee('nur-firewall')
        ->assertDontSee('nur-domain.de');
});

test('der Benutzerfilter zeigt nur Eintraege dieses Verursachers', function () {
    $customer = Customer::factory()->create();

    $einer = userWithPermissions([]);
    $this->actingAs($einer);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'von-einem.de']);

    $anderer = userWithPermissions([]);
    $this->actingAs($anderer);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'vom-anderen.de']);

    Livewire::test(AdminProtokoll::class)
        ->set('benutzer', (string) $einer->id)
        ->assertSee('von-einem.de')
        ->assertDontSee('vom-anderen.de');
});

test('der Zeitraum blendet Aelteres aus', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'von-heute.de']);
    $alt = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'von-damals.de']);

    Activity::where('subject_id', $alt->id)->where('subject_type', Domain::class)
        ->update(['created_at' => now()->subDays(40)]);

    Livewire::test(AdminProtokoll::class)
        ->set('tage', 7)
        ->assertSee('von-heute.de')
        ->assertDontSee('von-damals.de');
});

test('Zuruecksetzen raeumt alle Filter ab', function () {
    $this->actingAs(userWithPermissions([]));

    Livewire::test(AdminProtokoll::class)
        ->set('suche', 'irgendwas')
        ->set('ereignis', 'deleted')
        ->set('tage', 7)
        ->call('zuruecksetzen')
        ->assertSet('suche', '')
        ->assertSet('ereignis', '')
        ->assertSet('tage', 0);
});

test('die Kopfzeile nennt beide Zahlen, wenn gefiltert wird', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'eine.de']);

    // Ohne die Gesamtzahl haelt man das gefilterte Ergebnis fuer den Bestand.
    Livewire::test(AdminProtokoll::class)
        ->set('ereignis', 'deleted')
        ->assertSee('von');
});

test('die Auswahllisten enthalten nur, was vorkommt', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();
    Domain::factory()->create(['customer_id' => $customer->id]);

    $test = Livewire::test(AdminProtokoll::class);

    // Eine Auswahl mit 114 Verursachern, von denen 110 geloescht sind, hilft
    // niemandem - und eine Objektart ohne Eintraege auch nicht.
    expect($test->viewData('arten'))->toHaveKey(Domain::class);
    expect($test->viewData('arten'))->not->toHaveKey(Firewall::class);
});

test('heute heisst heute, nicht die letzten 24 Stunden', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    $heute = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'von-heute.de']);
    $gestern = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'gestern-abend.de']);

    // Gestern Abend, aber weniger als 24 Stunden her - der Knopf hiess "heute"
    // und zeigte es trotzdem.
    Activity::where('subject_id', $gestern->id)->where('subject_type', Domain::class)
        ->update(['created_at' => now()->subDay()->setTime(23, 30)]);
    Activity::where('subject_id', $heute->id)->where('subject_type', Domain::class)
        ->update(['created_at' => now()->startOfDay()->addHour()]);

    Livewire::test(AdminProtokoll::class)
        ->set('tage', 1)
        ->assertSee('von-heute.de')
        ->assertDontSee('gestern-abend.de');
});

test('Platzhalter im Suchbegriff werden nicht als solche gelesen', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'srv_01.example']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'srv101.example']);

    // Der Unterstrich steht in LIKE fuer ein beliebiges Zeichen. Ohne
    // Maskierung fand "srv_01" auch "srv101".
    Livewire::test(AdminProtokoll::class)
        ->set('suche', 'srv_01')
        ->assertSee('srv_01.example')
        ->assertDontSee('srv101.example');
});

test('das Prozentzeichen liefert nicht den ganzen Bestand', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'ohne-sonderzeichen.de']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'mit-100%-anteil.de']);

    // Gemessen im Browser: Die Suche nach "%" gab 863 von 863 Eintraegen aus.
    Livewire::test(AdminProtokoll::class)
        ->set('suche', '%')
        ->assertSee('mit-100%-anteil.de')
        ->assertDontSee('ohne-sonderzeichen.de');
});

test('die Benutzerliste trennt Mitarbeiter von Kundenzugaengen', function () {
    $kunde = Customer::factory()->create(['name' => 'Mustermann GmbH']);

    $techniker = userWithPermissions([]);
    $this->actingAs($techniker);
    Domain::factory()->create(['customer_id' => $kunde->id, 'name' => 'vom-techniker.de']);

    $kundenzugang = userWithPermissions([]);
    $kundenzugang->forceFill(['customer_id' => $kunde->id])->save();
    $this->actingAs($kundenzugang);
    Domain::factory()->create(['customer_id' => $kunde->id, 'name' => 'vom-kunden.de']);

    $liste = Livewire::test(AdminProtokoll::class)->viewData('benutzerListe');

    // Ein Kundenzugang mit Schreibrecht aendert Daten wie jeder Techniker -
    // in einer Liste aus lauter Namen liesse sich nicht erkennen, wer zu wem
    // gehoert.
    expect($liste)->toHaveKey('Mitarbeiter');
    expect($liste)->toHaveKey('Mustermann GmbH');
    expect($liste['Mitarbeiter']->keys()->all())->toContain($techniker->id);
    expect($liste['Mustermann GmbH']->keys()->all())->toContain($kundenzugang->id);
});

test('die Zeile nennt den Kunden eines Kundenzugangs', function () {
    $kunde = Customer::factory()->create(['name' => 'Mustermann GmbH']);

    $kundenzugang = userWithPermissions([]);
    $kundenzugang->forceFill(['customer_id' => $kunde->id])->save();

    $this->actingAs($kundenzugang);
    Domain::factory()->create(['customer_id' => $kunde->id, 'name' => 'geaendert.de']);

    Livewire::test(AdminProtokoll::class)
        ->assertSee($kundenzugang->name)
        ->assertSee('Mustermann GmbH');
});

test('der Benutzerfilter trifft auch einen Kundenzugang', function () {
    $kunde = Customer::factory()->create();

    $einer = userWithPermissions([]);
    $einer->forceFill(['customer_id' => $kunde->id])->save();
    $this->actingAs($einer);
    Domain::factory()->create(['customer_id' => $kunde->id, 'name' => 'vom-kunden.de']);

    $anderer = userWithPermissions([]);
    $this->actingAs($anderer);
    Domain::factory()->create(['customer_id' => $kunde->id, 'name' => 'vom-techniker.de']);

    // Genau der Fall, um den es geht: nachsehen, was ein bestimmter Zugang
    // getan hat.
    Livewire::test(AdminProtokoll::class)
        ->set('benutzer', (string) $einer->id)
        ->assertSee('vom-kunden.de')
        ->assertDontSee('vom-techniker.de');
});

test('jeder Eintrag nennt den Namen des Objekts', function () {
    $this->actingAs(userWithPermissions([]));
    $customer = Customer::factory()->create();

    $domain = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'beispiel.de']);
    // Nur ein Nebenfeld aendern: logOnlyDirty schreibt dann nur dieses Feld,
    // und im Protokoll stand "Domain #1" statt des Namens.
    $domain->update(['registrar' => 'Hetzner']);

    $eintrag = Activity::where('subject_id', $domain->id)
        ->where('subject_type', Domain::class)
        ->where('event', 'updated')->latest('id')->first();

    expect($eintrag->properties['objekt'])->toBe('beispiel.de');

    Livewire::test(AdminProtokoll::class)->assertSee('beispiel.de');
});
