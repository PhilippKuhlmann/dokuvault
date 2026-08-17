<?php

use App\Livewire\DeviceIpAddresses;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\IpAddress;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Befunde aus dem Praxistest an einer erfundenen Firma - jeder Fall ist dort
 * beim Dokumentieren aufgefallen, nicht am Code.
 */
test('die USC-PIN einer Securepoint-Firewall wird gespeichert', function () {
    $this->actingAs(userWithPermissions(['firewall_create', 'firewall_viewAny']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    // Sie fehlte in den Regeln, der Controller speichert validated() - die
    // Eingabe verschwand ohne Meldung.
    $this->post("/{$customer->slug}/firewall", [
        'site_id' => $site->id,
        'name' => 'FW-01',
        'manufacturer' => 'Securepoint',
        'username' => 'admin',
        'password' => 'geheim',
        'cloud_backup_password' => 'backup',
        'usc_pin' => '448213',
        'management_url' => 'https://10.0.0.1:11115',
    ])->assertRedirect();

    expect(Firewall::where('name', 'FW-01')->sole()->usc_pin)->toBe('448213');
});

test('dieselbe IP-Adresse laesst sich beim Kunden nur einmal vergeben', function () {
    $this->actingAs(userWithPermissions(['server_update', 'networkswitch_update']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    $server = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
    ]);

    Livewire::test(DeviceIpAddresses::class, ['model' => $server, 'customer' => $customer])
        ->set('address', '10.20.10.11')
        ->call('add')
        ->assertHasNoErrors();

    // Zweimal am selben Geraet: abgelehnt.
    Livewire::test(DeviceIpAddresses::class, ['model' => $server, 'customer' => $customer])
        ->set('address', '10.20.10.11')
        ->call('add')
        ->assertHasErrors('address');

    // Und an einem anderen Geraet ebenfalls - das ist der schlimmere Fall:
    // die Doku behauptete sonst, die Adresse gehoere zu beiden.
    $switch = NetworkSwitch::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);

    Livewire::test(DeviceIpAddresses::class, ['model' => $switch, 'customer' => $customer])
        ->set('address', '10.20.10.11')
        ->call('add')
        ->assertHasErrors('address');

    expect(IpAddress::where('customer_id', $customer->id)->count())->toBe(1);
});

test('eine Adresse aus dem Papierkorb blockiert nicht', function () {
    $this->actingAs(userWithPermissions(['server_update']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $server = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
    ]);

    $block = Livewire::test(DeviceIpAddresses::class, ['model' => $server, 'customer' => $customer])
        ->set('address', '10.20.10.11')
        ->call('add');

    $block->call('remove', IpAddress::where('customer_id', $customer->id)->sole()->id);

    // Sonst waere die Adresse nach dem Aufraeumen fuer immer gesperrt.
    Livewire::test(DeviceIpAddresses::class, ['model' => $server, 'customer' => $customer])
        ->set('address', '10.20.10.11')
        ->call('add')
        ->assertHasNoErrors();
});

test('die Loeschkarte nennt den Papierkorb statt endgueltigem Verlust', function () {
    $this->actingAs(userWithPermissions(['contactperson_viewAny', 'contactperson_update', 'contactperson_delete']));
    $customer = Customer::factory()->create();
    $person = ContactPerson::factory()->create(['customer_id' => $customer->id]);

    $inhalt = $this->get("/{$customer->slug}/contactperson/{$person->id}/edit")->assertOk()->getContent();

    expect($inhalt)->toContain('Papierkorb');
    expect($inhalt)->not->toContain('unwiederruflich');
});

test('der Ansprechpartner hat eine Funktion', function () {
    $this->actingAs(userWithPermissions(['contactperson_create', 'contactperson_viewAny']));
    $customer = Customer::factory()->create();

    $this->post("/{$customer->slug}/contactperson", [
        'first_name' => 'Timo',
        'last_name' => 'Brandt',
        'role' => 'IT-Verantwortlicher',
        'phone' => '040 123',
        'mail' => 't.brandt@example.de',
    ])->assertRedirect();

    expect(ContactPerson::where('last_name', 'Brandt')->sole()->role)->toBe('IT-Verantwortlicher');

    // Und sie steht in der Liste - sonst nuetzt sie beim Nachschlagen nichts.
    expect($this->get("/{$customer->slug}/contactperson")->getContent())
        ->toContain('IT-Verantwortlicher');
});

test('das Dashboard zaehlt auch die Netzwerk-Infrastruktur', function () {
    $this->actingAs(userWithPermissions([
        'firewall_viewAny', 'router_viewAny', 'networkswitch_viewAny',
        'accesspoint_viewAny', 'rack_viewAny', 'patchpanel_viewAny', 'internetconnection_viewAny',
    ]));
    $customer = Customer::factory()->create();

    // Firewall, Switches, Schraenke und der Anschluss fehlten in der
    // Uebersicht - man konnte sie erfassen und sah sie dort nie wieder.
    $inhalt = $this->get(route('customer.dashboard', $customer))->assertOk()->getContent();

    foreach (['Internet / WAN', 'Firewalls', 'Switches', 'Accesspoints', 'Serverschränke', 'Patchfelder'] as $kachel) {
        expect($inhalt)->toContain($kachel);
    }
});

test('das Betriebssystem ist nicht vorausgewaehlt', function () {
    $this->actingAs(userWithPermissions(['server_create', 'vm_create']));
    $customer = Customer::factory()->create();
    Site::factory()->create(['customer_id' => $customer->id]);
    OperatingSystem::factory()->create(['name' => 'Windows Server 2025 Standard']);

    // Vorher stand der erste Eintrag der Liste da; wer das uebersah,
    // dokumentierte still das falsche Betriebssystem.
    $inhalt = $this->get("/{$customer->slug}/server/create")->assertOk()->getContent();

    expect($inhalt)->toContain('<option value="">');

    // Leere Auswahl muss eine Meldung geben statt eines Datenbankfehlers:
    // die Spalte ist NOT NULL.
    $this->post("/{$customer->slug}/vm", ['name' => 'VM-01', 'operating_system_id' => ''])
        ->assertSessionHasErrors('operating_system_id');
});
