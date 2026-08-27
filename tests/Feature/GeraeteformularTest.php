<?php

use App\Livewire\ObjektFormular;
use App\Models\Accesspoint;
use App\Models\Camera;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\DECT;
use App\Models\Firewall;
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
use App\Models\Server;
use App\Models\Site;
use App\Models\Ups;
use App\Models\VM;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

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
    'firewall' => Firewall::class,
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

test('kein Geraeteformular fuehrt noch eigene IP-Felder', function () {
    // Die Adressen haengen am eigenen Block, nicht mehr an Feldern im Formular.
    // Frueher stand das hier fuer die /create- und /edit-Seiten; die gibt es
    // nicht mehr, die Zusicherung gilt aber unveraendert fuers Modal.
    foreach (GERAETE as $slug => $klasse) {
        $this->actingAs(userWithPermissions(["{$slug}_create", "{$slug}_update"]));
        [$customer, $geraet] = geraetUmgebung($klasse);

        foreach ([null, $geraet->id] as $id) {
            $formular = Livewire::test(ObjektFormular::class, ['typ' => $slug, 'customer' => $customer]);
            $id === null ? $formular->call('neu') : $formular->call('bearbeiten', $slug, $id);
            $inhalt = $formular->html();

            foreach (['ip', 'ip1', 'ip2'] as $feld) {
                expect(str_contains($inhalt, 'wire:model="form.'.$feld.'"'))
                    ->toBeFalse("$slug: Feld $feld gehört in den IP-Block, nicht ins Formular.");
            }
        }
    }
});

test('beim Anlegen sagt das Modal, dass Adressen und Zugangsdaten spaeter kommen', function () {
    // Beide Bloecke haengen am gespeicherten Objekt und koennen beim Anlegen
    // noch nicht dastehen. Ohne Hinweis sieht das aus wie ein Mangel.
    foreach (GERAETE as $slug => $klasse) {
        $this->actingAs(userWithPermissions(["{$slug}_create"]));
        [$customer] = geraetUmgebung($klasse);

        $inhalt = Livewire::test(ObjektFormular::class, ['typ' => $slug, 'customer' => $customer])
            ->call('neu')->html();

        expect($inhalt)->toContain('sobald der Eintrag angelegt ist');
    }
});

test('Speichern der Stammdaten laesst die Adressen im Block stehen', function () {
    // Router steht fuer die neun Typen, deren Request die IP bisher verlangte.
    $this->actingAs(userWithPermissions(['router_update']));
    [$customer, $router] = geraetUmgebung(Router::class);
    $router->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.10.30.1']);

    imModalBearbeiten('router', $customer, $router, [
        'site_id' => $router->site_id, 'name' => 'RTR-NEU',
        'username' => 'admin', 'password' => 'x', 'port' => '443',
    ])->assertSessionHasNoErrors();

    $router->refresh();
    expect($router->name)->toBe('RTR-NEU');

    // Die Adresse haengt am Block und ueberlebt das Speichern der Stammdaten.
    expect($router->ipAddresses()->pluck('address')->all())->toBe(['10.10.30.1']);
});
