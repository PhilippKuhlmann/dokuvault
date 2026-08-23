<?php

use App\Models\Customer;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Die Listen laden ihre Nebendaten vor.
 *
 * Aufgefallen an einem Kunden mit 40 Servern und 22 Switches: Eine Seite mit
 * 25 Zeilen kostete rund 100 Abfragen, weil einbauort() je Geraet Einbau und
 * Schrank einzeln nachlud. Lokal sind das Millisekunden, mit Netzwerk zwischen
 * Anwendung und Datenbank Sekunden.
 */
test('eine Geraeteliste braucht nur eine Handvoll Abfragen', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $rack = Rack::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);

    $server = Server::factory()->count(25)->create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'operating_system_id' => $os->id,
    ]);

    foreach ($server as $nr => $s) {
        $s->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.60.0.'.(10 + $nr)]);
        $rack->items()->create(['device_type' => Server::class, 'device_id' => $s->id,
            'position' => $nr + 1, 'height_units' => 1, 'side' => 'front']);
    }

    $abfragen = 0;
    DB::listen(function () use (&$abfragen) {
        $abfragen++;
    });

    $this->get(route('server.index', $customer))->assertOk();

    // Ohne Vorladen waren es ueber hundert. Die Grenze ist bewusst weit
    // gesetzt: Sie soll eine Rueckkehr zum Nachladen je Zeile bemerken, nicht
    // jede einzelne zusaetzliche Abfrage verbieten.
    //
    // Von 30 auf 40 angehoben, als das Cluster-Auswahlfeld ins Modal kam: Jedes
    // Feld vom Typ 'auswahl' kostet den Formularteil der Seite ein paar feste
    // Abfragen (Auswahlliste und Spaltenpruefung). Gemessen: mit dem Feld 30
    // statt 27 Abfragen, und die Zahl steigt nicht mit der Zeilenzahl (5 Zeilen
    // 30, 25 Zeilen 25) - also keine Rueckkehr zum Nachladen je Zeile, die
    // diese Grenze abfangen soll.
    expect($abfragen)->toBeLessThan(40, "Liste braucht {$abfragen} Abfragen - lädt sie ihre Nebendaten noch vor?");
});

test('auch die Switch-Liste laedt vor', function () {
    $this->actingAs(userWithPermissions(['networkswitch_viewAny']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    NetworkSwitch::factory()->count(25)->create(['customer_id' => $customer->id, 'site_id' => $site->id]);

    $abfragen = 0;
    DB::listen(function () use (&$abfragen) {
        $abfragen++;
    });

    $this->get(route('networkswitch.index', $customer))->assertOk();

    expect($abfragen)->toBeLessThan(30, "Liste braucht {$abfragen} Abfragen");
});
