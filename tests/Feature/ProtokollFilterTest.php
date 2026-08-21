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
