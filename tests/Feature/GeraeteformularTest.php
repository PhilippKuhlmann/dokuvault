<?php

use App\Models\Accesspoint;
use App\Models\Camera;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\DECT;
use App\Models\IoTDevice;
use App\Models\Machine;
use App\Models\NAS;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\OtherClient;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Recorder;
use App\Models\Router;
use App\Models\SecurepointUMA;
use App\Models\SecurepointUTM;
use App\Models\Server;
use App\Models\Site;
use App\Models\Ups;
use App\Models\VM;
use Illuminate\Support\Facades\Schema;

/**
 * Alle Geraeteformulare tragen dieselbe Gliederung wie das Server-Formular:
 * Abschnitte, breite Karte, IP-Felder nur noch im Block "Weitere IP-Adressen",
 * und die beiden Livewire-Bloecke stehen in derselben Karte.
 *
 * Slug => Model-Klasse. Die Berechtigung heisst wie der Slug.
 */
const GERAETE = [
    'accesspoint' => Accesspoint::class,
    'camera' => Camera::class,
    'computer' => Computer::class,
    'dect' => DECT::class,
    'iotdevice' => IoTDevice::class,
    'machine' => Machine::class,
    'nas' => NAS::class,
    'networkswitch' => NetworkSwitch::class,
    'otherclient' => OtherClient::class,
    'phone' => Phone::class,
    'phonesystem' => PhoneSystem::class,
    'printer' => Printer::class,
    'recorder' => Recorder::class,
    'router' => Router::class,
    'securepointuma' => SecurepointUMA::class,
    'securepointutm' => SecurepointUTM::class,
    'ups' => Ups::class,
    'vm' => VM::class,
    'server' => Server::class,
];

function geraetUmgebung(string $klasse): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    // Nicht jede Geraetetabelle kennt einen Standort (securepoint_umas etwa nicht).
    $tabelle = (new $klasse)->getTable();
    $attribute = ['customer_id' => $customer->id];

    if (Schema::hasColumn($tabelle, 'site_id')) {
        $attribute['site_id'] = $site->id;
    }

    // Die Factories setzen feste Betriebssystem-IDs, die es in der leeren
    // Testdatenbank nicht gibt - eines anlegen und mitgeben.
    if (Schema::hasColumn($tabelle, 'operating_system_id')) {
        $attribute['operating_system_id'] = OperatingSystem::factory()->create(['name' => 'Debian 13'])->id;
    }

    return [$customer, $klasse::factory()->create($attribute)];
}

test('jedes Geraeteformular ist in Abschnitte gegliedert und fuehrt keine IP-Felder mehr', function () {
    foreach (GERAETE as $slug => $klasse) {
        $this->actingAs(userWithPermissions(["{$slug}_create", "{$slug}_update"]));
        [$customer, $geraet] = geraetUmgebung($klasse);

        foreach (["/{$customer->slug}/{$slug}/create", "/{$customer->slug}/{$slug}/{$geraet->id}/edit"] as $url) {
            $inhalt = $this->get($url)->assertOk()->getContent();

            expect($inhalt)->toContain('uppercase tracking-wide text-cerulean');
            expect($inhalt)->toContain('max-w-5xl');

            foreach (['ip', 'ip1', 'ip2'] as $feld) {
                expect($inhalt)->not->toContain('name="'.$feld.'"');
            }
        }
    }
});

test('jedes Bearbeiten-Formular traegt IP-Adressen und Zugangsdaten in derselben Karte', function () {
    foreach (GERAETE as $slug => $klasse) {
        $this->actingAs(userWithPermissions(["{$slug}_update"]));
        [$customer, $geraet] = geraetUmgebung($klasse);

        $inhalt = $this->get("/{$customer->slug}/{$slug}/{$geraet->id}/edit")->assertOk()->getContent();

        expect(substr_count($inhalt, 'speichert sofort'))->toBe(2, "$slug: Bloecke fehlen oder doppelt");
        expect($inhalt)->toContain('Weitere IP-Adressen');
        expect($inhalt)->toContain('Stammdaten speichern');
    }
});

test('jedes Anlegen-Formular sagt, dass IP-Adressen und Zugangsdaten spaeter kommen', function () {
    // Beide Bloecke haengen am gespeicherten Objekt und koennen beim Anlegen
    // noch nicht dastehen. Ohne Hinweis sieht das aus wie ein Mangel.
    foreach (GERAETE as $slug => $klasse) {
        $this->actingAs(userWithPermissions(["{$slug}_create"]));
        [$customer] = geraetUmgebung($klasse);

        $inhalt = $this->get("/{$customer->slug}/{$slug}/create")->assertOk()->getContent();

        expect($inhalt)->toContain('IP-Adressen und Zugangsdaten');
        expect($inhalt)->toContain('Lassen sich eintragen, sobald das Gerät angelegt ist.');
    }
});

test('Speichern ohne IP-Feld laesst den Bestandswert stehen', function () {
    // Router steht fuer die neun Typen, deren Request die IP bisher verlangte.
    $this->actingAs(userWithPermissions(['router_update']));
    [$customer, $router] = geraetUmgebung(Router::class);
    $router->update(['ip' => '10.10.30.1']);

    $this->patch("/{$customer->slug}/router/{$router->id}", [
        'site_id' => $router->site_id, 'name' => 'RTR-NEU',
        'username' => 'admin', 'password' => 'x', 'port' => '443',
    ])->assertSessionHasNoErrors();

    $router->refresh();
    expect($router->name)->toBe('RTR-NEU');
    expect($router->ip)->toBe('10.10.30.1');
});
