<?php

namespace Database\Seeders;

use App\Models\Accesspoint;
use App\Models\ADDomain;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Backup;
use App\Models\Camera;
use App\Models\Certificate;
use App\Models\Cluster;
use App\Models\Computer;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\DECT;
use App\Models\Domain;
use App\Models\DynDNS;
use App\Models\Firewall;
use App\Models\FTPServer;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\IpRange;
use App\Models\LicenseAccess;
use App\Models\LicenseSoftware;
use App\Models\LicenseWindows;
use App\Models\LoginGeneral;
use App\Models\LoginWebsite;
use App\Models\Machine;
use App\Models\Mailbox;
use App\Models\NAS;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\OtherClient;
use App\Models\PatchPanel;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\Recorder;
use App\Models\Role;
use App\Models\Router;
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Ups;
use App\Models\User;
use App\Models\VM;
use App\Models\Wifi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocalDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);

        $this->call([
            PermissionRoleSeeder::class,
            UserSeeder::class,
            OperatingSystemsSeeder::class,
            MailboxProvidorsSeeder::class,
        ]);

        // Dienste-Katalog: gilt fuer die ganze Installation, nicht je Kunde.
        // Farbe nach Rolle - was ausfaellt, faellt unterschiedlich schwer auf.
        // Die Beschreibung steht bei der Auswahl im Geraeteformular und als
        // Titel an der Kachel; Kuerzel wie "DFS" oder "RDS" erklaeren sich
        // sonst nur dem, der sie schon kennt.
        foreach ([
            'AD' => ['#b91c1c', 'Verzeichnisdienst: Anmeldung, Benutzer und Gruppenrichtlinien'],
            'DNS' => ['#dc2626', 'Namensauflösung im Netz'],
            'Hyper-V' => ['#7c3aed', 'Virtualisierung – auf diesem Host laufen VMs'],
            'SQL' => ['#1f73d6', 'Datenbankserver'],
            'Fileserver' => ['#3391f0', 'Dateifreigaben der Abteilungen'],
            'DFS' => ['#8ecdff', 'Verteiltes Dateisystem über mehrere Standorte'],
            'Backup' => ['#15803d', 'Sicherung – hier laufen die Aufträge'],
            'RDS' => ['#b45309', 'Terminalserver für Remote-Arbeitsplätze'],
            'Print' => ['#f59e0b', 'Druckerwarteschlangen und Treiber'],
            'docker' => ['#0f766e', 'Container-Laufzeitumgebung'],
            'apache2' => ['#14b8a6', 'Webserver'],
            'mariadb' => ['#1b4176', 'Datenbankserver, MySQL-kompatibel'],
        ] as $name => [$farbe, $beschreibung]) {
            Service::create(['name' => $name, 'description' => $beschreibung, 'color' => $farbe]);
        }

        $customer = Customer::factory()->create([
            'name' => 'Mustermann',
        ]);

        $site1 = Site::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Zentrale Hamburg',
        ]);

        $site2 = Site::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Filiale München',
        ]);

        User::factory()->create([
            'name' => 'Kunde Lesen/Schreiben',
            'username' => 'kunde-rw',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'general_full')->firstOrFail()->id,
            'customer_id' => $customer->id,
        ]);

        User::factory()->create([
            'name' => 'Kunde nur Lesen',
            'username' => 'kunde-r',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'general_read')->firstOrFail()->id,
            'customer_id' => $customer->id,
        ]);

        ADDomain::factory()->create([
            'domain' => 'ad.mustermann.de',
            'netbios' => 'MUSTERMANN',
            'dsrmpassword' => 'password',
            'customer_id' => $customer->id,
        ]);

        Router::factory(2)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        // Eine Securepoint mit ihren Sonderfeldern, damit in der Demo zu sehen
        // ist, dass sie nur bei diesem Hersteller erscheinen.
        Firewall::factory(1)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'manufacturer' => 'Securepoint',
            'model' => 'RC300 G5',
            'form_factor' => 'appliance',
            'firmware' => '12.6.2',
            'management_url' => 'https://192.168.175.1:11115',
            'url_user' => 'https://192.168.175.1',
            'url_external' => 'https://utm.mustermann-gmbh.de:11115',
            'usc_pin' => '448213',
            'cloud_backup_password' => 'Wolke!2026sicher',
        ]);

        SecurepointUMA::factory(1)->create([
            'customer_id' => $customer->id,
            'name' => 'Reddoxx Mailserver',
            'manufacturer' => 'Reddoxx',
            'type' => 'Appliance',
        ]);

        Network::factory(5)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Network::factory(3)->create([
            'customer_id' => $customer->id,
            'site_id' => $site2->id,
        ]);

        // Kohärentes Management-VLAN mit passend adressierten Geräten (für einen
        // aussagekräftigen IP-Plan: Gateway, Server, DHCP-Bereich, freie Bereiche).
        Network::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'description' => 'Server & Management',
            'vlanId' => 30,
            'network' => '10.10.30.0',
            'cidr' => '24',
            'subnetmask' => '255.255.255.0',
            'gateway' => '10.10.30.1',
            'dns1' => '10.10.30.10',
            'dns2' => '8.8.8.8',
            // Volle Adressen wie im Formular gefordert, nicht nur das Oktett.
            'dhcpStart' => '10.10.30.100',
            'dhcpEnd' => '10.10.30.200',
        ]);

        // Clients-VLAN (der Router ist auch hier Gateway -> zweite IP)
        $clientsVlan = Network::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'description' => 'Clients',
            'vlanId' => 20,
            'network' => '10.10.20.0',
            'cidr' => '24',
            'subnetmask' => '255.255.255.0',
            'gateway' => '10.10.20.1',
            'dhcpStart' => '10.10.20.100',
            'dhcpEnd' => '10.10.20.200',
        ]);

        // Reservierte Bereiche: Sie belegen nichts, sie halten fest, wofuer ein
        // Stueck des Netzes gedacht ist. Drei aneinandergrenzende, damit im
        // Demo-Datensatz sichtbar ist, dass jeder seine eigene Farbe bekommt -
        // in einer Farbe waeren sie eine einzige Flaeche.
        //
        // Der erste beginnt bewusst bei .10, wo SRV-DC01 haengt: So sieht man,
        // dass eine belegte Adresse im Bereich belegt bleibt und der Block
        // trotzdem durchlaeuft.
        foreach ([
            ['10.10.20.10', '10.10.20.20', 'Proxmox-Server'],
            ['10.10.20.21', '10.10.20.30', 'Drucker'],
            ['10.10.20.31', '10.10.20.40', 'Kameras'],
        ] as [$von, $bis, $wofuer]) {
            IpRange::create([
                'customer_id' => $customer->id,
                'network_id' => $clientsVlan->id,
                'from_ip' => $von,
                'to_ip' => $bis,
                'label' => $wofuer,
            ]);
        }

        $rtrCore = Router::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'RTR-Core']);
        // Router hängt in mehreren VLANs -> zusätzliche Gateway-IP im Clients-VLAN
        $rtrCore->ipAddresses()->create(['customer_id' => $customer->id, 'network_id' => $clientsVlan->id, 'address' => '10.10.20.1', 'label' => 'Gateway Clients']);

        // Vier Geraetemodelle mit eigener Zeichnung. Die Geraete darunter tragen
        // dieselben Werte in Hersteller und Modell - darueber findet die
        // Rack-Ansicht die Blende, ganz ohne Verknuepfung.
        $this->call(DeviceModelSeeder::class);

        NetworkSwitch::factory()->create([
            'customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'SW-Core',
            'manufacturer' => 'Ubiquiti', 'model' => 'USW-Pro-24-PoE',
        ]);
        NetworkSwitch::factory()->create([
            'customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'SW-Edge-01',
            'manufacturer' => 'Ubiquiti', 'model' => 'USW-Pro-48-PoE',
        ]);
        // ein Client im Clients-VLAN, damit dort auch etwas belegt ist
        Computer::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'PC-Empfang']);
        $srvDc01 = Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'SRV-DC01', 'bmcIp' => '10.10.30.210', 'manufacturer' => 'Dell', 'model' => 'PowerEdge R650']);
        // Ein Server mit mehreren Beinen - damit im Demo-Datensatz sichtbar ist,
        // dass die Liste alle Adressen zeigt und nicht nur die ersten beiden.
        $srvDc01->ipAddresses()->create(['customer_id' => $customer->id, 'network_id' => $clientsVlan->id, 'address' => '10.10.20.10', 'label' => 'Clients']);
        Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'SRV-FS01', 'bmcIp' => '10.10.30.211']);
        Server::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'SRV-HV01', 'bmcIp' => '10.10.30.212']);

        // Proxmox-Cluster mit drei Knoten: der Fall, fuer den es die
        // Cluster-Doku ueberhaupt gibt. Ceph als Technik, weil das den
        // Unterschied zum Einzelserver ausmacht - der Speicher liegt verteilt
        // auf den Knoten, nicht auf einem SAN daneben.
        $pveOs = OperatingSystem::firstOrCreate(['name' => 'Proxmox VE 9']);
        $pveCluster = Cluster::create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'name' => 'PVE-Cluster HH',
            'type' => 'ceph',
            'note' => 'Drei Knoten, Ceph auf NVMe, Quorum über alle drei',
        ]);

        // Die Adressen ohne network_id wie bei den uebrigen Geraeten: Der
        // IP-Plan ordnet sie ueber den Adressbereich zu.
        foreach ([
            ['PVE-01', '10.10.30.13', '10.10.30.213'],
            ['PVE-02', '10.10.30.14', '10.10.30.214'],
            ['PVE-03', '10.10.30.15', '10.10.30.215'],
        ] as [$knoten, $ip, $bmc]) {
            Server::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
                'cluster_id' => $pveCluster->id,
                'operating_system_id' => $pveOs->id,
                'name' => $knoten,
                'bmcIp' => $bmc,
                'form_factor' => 'rack',
                'height_units' => 1,
                'full_depth' => true,
                'services' => 'Virtualisierung,Ceph',
            ])->ipAddresses()->create([
                'customer_id' => $customer->id,
                'address' => $ip,
                'label' => 'Primaer',
            ]);
        }

        // Zwei VMs am Cluster, ohne festen Knoten - genau das unterscheidet sie
        // von einer VM auf einem einzelnen Host: Im HA-Cluster wandern sie.
        $debian = OperatingSystem::firstOrCreate(['name' => 'Debian 13']);
        foreach (['VM-Ticketsystem', 'VM-Monitoring'] as $vmName) {
            VM::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
                'cluster_id' => $pveCluster->id,
                'operating_system_id' => $debian->id,
                'name' => $vmName,
            ]);
        }
        NAS::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'NAS-Backup']);
        Accesspoint::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'AP-Serverraum']);

        // Adressen gehoeren nicht mehr an das Geraet, sondern in den Block
        // "Weitere IP-Adressen". Hier gebuendelt statt bei jedem create, damit
        // die Zeilen oben lesbar bleiben.
        foreach ([
            [Router::class, 'RTR-Core', '10.10.30.1'],
            [NetworkSwitch::class, 'SW-Core', '10.10.30.2'],
            [Computer::class, 'PC-Empfang', '10.10.20.50'],
            [Server::class, 'SRV-DC01', '10.10.30.10'],
            [Server::class, 'SRV-FS01', '10.10.30.11'],
            [Server::class, 'SRV-HV01', '10.10.30.12'],
            [NAS::class, 'NAS-Backup', '10.10.30.20'],
            [Accesspoint::class, 'AP-Serverraum', '10.10.30.30'],
        ] as [$klasse, $name, $adresse]) {
            $klasse::where('customer_id', $customer->id)->where('name', $name)->first()
                ?->ipAddresses()->create([
                    'customer_id' => $customer->id,
                    'address' => $adresse,
                    'label' => 'Primär',
                ]);
        }

        // Patchfeld mit ein paar beschrifteten Dosen - die uebrigen Ports bleiben frei,
        // so wie in einer echten Doku.
        $swCore = NetworkSwitch::where('customer_id', $customer->id)->where('name', 'SW-Core')->first();
        $patchpanel = PatchPanel::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'name' => 'PF-EG-01',
            'port_count' => 24,
            'height_units' => 1,
        ]);
        $patchpanel->syncPorts();
        foreach ([
            [1, 'EG 1.01', 'Empfang', '1'],
            [2, 'EG 1.02', 'Empfang', '2'],
            [5, 'EG 1.05', 'Besprechung', '5'],
            [6, 'EG 1.06', 'Besprechung', '6'],
            [12, 'A.12', 'Büro Nord', '12'],
        ] as [$nr, $dose, $raum, $swPort]) {
            $patchpanel->ports()->where('number', $nr)->update([
                'outlet' => $dose,
                'label' => $raum,
                'network_switch_id' => $swCore?->id,
                'switch_port' => $swPort,
            ]);
        }

        // Serverschrank mit den kohärenten Geräten von oben - von unten nach oben:
        // USV, Server, NAS, dann Netzwerktechnik und Patchfeld unter der Decke.
        $rack = Rack::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'name' => 'Rack HH-01',
            'height_units' => 42,
            'location' => 'Serverraum EG',
        ]);
        $usv = Ups::factory()->create(['customer_id' => $customer->id, 'site_id' => $site1->id, 'name' => 'USV-01', 'manufacturer' => 'APC', 'model' => 'Smart-UPS 1500 RM']);
        $byName = fn (string $class, string $name) => $class::where('customer_id', $customer->id)->where('name', $name)->first();

        // Passive Einbauten aus dem Katalog holen, damit Hoehe *und* Darstellung
        // von dort kommen - genau wie beim Einbauen im Editor.
        $ausKatalog = function (int $position, string $name, string $seite = 'front') {
            $eintrag = RackCatalogItem::where('name', $name)->first();

            return $eintrag ? [
                'side' => $seite,
                'position' => $position,
                'height_units' => $eintrag->height_units,
                'full_depth' => $eintrag->full_depth,
                'name' => $eintrag->name,
                'appearance' => $eintrag->appearance,
            ] : null;
        };

        $rack->items()->createMany(array_filter([
            ['position' => 1, 'height_units' => 2, 'device_type' => Ups::class, 'device_id' => $usv->id],
            // Eine HE: Ein PowerEdge R650 ist ein 1-HE-Geraet, und das Modell sagt es auch.
            ['position' => 4, 'height_units' => 1, 'device_type' => Server::class, 'device_id' => $byName(Server::class, 'SRV-DC01')?->id],
            ['position' => 6, 'height_units' => 2, 'device_type' => Server::class, 'device_id' => $byName(Server::class, 'SRV-FS01')?->id],
            ['position' => 8, 'height_units' => 2, 'device_type' => Server::class, 'device_id' => $byName(Server::class, 'SRV-HV01')?->id],
            ['position' => 11, 'height_units' => 2, 'device_type' => NAS::class, 'device_id' => $byName(NAS::class, 'NAS-Backup')?->id],
            $ausKatalog(14, 'Fachboden 1 HE'),
            // Die drei Clusterknoten uebereinander, wie sie auch im Schrank
            // stehen wuerden.
            ['position' => 22, 'height_units' => 1, 'device_type' => Server::class, 'device_id' => $byName(Server::class, 'PVE-01')?->id],
            ['position' => 24, 'height_units' => 1, 'device_type' => Server::class, 'device_id' => $byName(Server::class, 'PVE-02')?->id],
            ['position' => 26, 'height_units' => 1, 'device_type' => Server::class, 'device_id' => $byName(Server::class, 'PVE-03')?->id],
            $ausKatalog(20, 'Blindplatte 2 HE'),
            ['position' => 36, 'height_units' => 1, 'device_type' => Router::class, 'device_id' => $byName(Router::class, 'RTR-Core')?->id],
            ['position' => 38, 'height_units' => 1, 'device_type' => NetworkSwitch::class, 'device_id' => $byName(NetworkSwitch::class, 'SW-Core')?->id],
            // Unter seinem Patchfeld (PF-EG-02 auf 35), wie er auch haengen wuerde.
            ['position' => 34, 'height_units' => 1, 'device_type' => NetworkSwitch::class, 'device_id' => $byName(NetworkSwitch::class, 'SW-Edge-01')?->id],
            ['position' => 41, 'height_units' => $patchpanel->height_units,
                'device_type' => PatchPanel::class, 'device_id' => $patchpanel->id],
            $ausKatalog(39, 'Rangierfeld'),
            $ausKatalog(40, 'Patchfeld 24 Port'),
            $ausKatalog(42, 'Kabeldurchführung'),

            // Rueckseite: Was dort typischerweise sitzt - Strom unten,
            // Kabelfuehrung oben. Beides in halber Tiefe, vorne bleibt Platz.
            $ausKatalog(3, 'Steckdosenleiste (PDU)', 'rear'),
            $ausKatalog(37, 'Kabeldurchführung', 'rear'),
        ], fn ($item) => $item !== null
            && (! array_key_exists('device_id', $item) || $item['device_id'] !== null)));

        // Weitere Patchfelder und zwei kleine Schraenke. Ein Kunde hat selten nur
        // einen Serverraum: Etagenverteiler sind meist 12 HE und tragen vor allem
        // Patchfelder und einen Switch.
        $patchfeld = function (string $name, Site $standort, int $ports, int $he, array $dosen) use ($customer, $swCore) {
            $feld = PatchPanel::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $standort->id,
                'name' => $name,
                'port_count' => $ports,
                'height_units' => $he,
            ]);
            $feld->syncPorts();

            foreach ($dosen as [$nr, $dose, $raum, $swPort]) {
                $feld->ports()->where('number', $nr)->update([
                    'outlet' => $dose,
                    'label' => $raum,
                    'network_switch_id' => $swCore?->id,
                    'switch_port' => $swPort,
                ]);
            }

            return $feld;
        };

        // Grosser Schrank: ein zweites Feld fuer das Obergeschoss.
        $pfEg02 = $patchfeld('PF-EG-02', $site1, 24, 1, [
            [3, 'EG 2.03', 'Buero Sued', '13'],
            [4, 'EG 2.04', 'Buero Sued', '14'],
            [9, 'EG 2.09', 'Kueche', '19'],
        ]);
        $rack->items()->create([
            'position' => 35, 'height_units' => $pfEg02->height_units,
            'device_type' => PatchPanel::class, 'device_id' => $pfEg02->id,
        ]);

        // Etagenverteiler 1. OG - zwei Felder, eines davon 48 Ports auf 2 HE.
        $rackOg = Rack::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'name' => 'Rack HH-02',
            'height_units' => 12,
            'location' => 'Etagenverteiler 1. OG',
        ]);

        $pfOg01 = $patchfeld('PF-OG-01', $site1, 48, 2, [
            [1, 'OG 1.01', 'Buero 101', '1'],
            [2, 'OG 1.02', 'Buero 101', '2'],
            [7, 'OG 1.07', 'Buero 104', '7'],
            [26, 'OG 1.26', 'Konferenz', '26'],
            [31, 'OG 1.31', 'Konferenz', '31'],
        ]);
        $pfOg02 = $patchfeld('PF-OG-02', $site1, 24, 1, [
            [4, 'OG 2.04', 'Technik', '4'],
            [5, 'OG 2.05', 'Technik', '5'],
        ]);

        $rackOg->items()->createMany(array_filter([
            ['position' => 10, 'height_units' => $pfOg01->height_units,
                'device_type' => PatchPanel::class, 'device_id' => $pfOg01->id],
            ['position' => 12, 'height_units' => $pfOg02->height_units,
                'device_type' => PatchPanel::class, 'device_id' => $pfOg02->id],
            $ausKatalog(9, 'Rangierfeld'),
            $ausKatalog(1, 'Steckdosenleiste (PDU)', 'rear'),
        ], fn ($item) => $item !== null));

        // Filiale Muenchen - ein kleiner Schrank mit einem Feld.
        $rackMuc = Rack::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site2->id,
            'name' => 'Rack MUC-01',
            'height_units' => 12,
            'location' => 'Technikraum Filiale',
        ]);

        $pfMuc = $patchfeld('PF-MUC-01', $site2, 24, 1, [
            [1, 'MUC 1.01', 'Empfang', '1'],
            [8, 'MUC 1.08', 'Lager', '8'],
        ]);

        $rackMuc->items()->createMany(array_filter([
            ['position' => 11, 'height_units' => $pfMuc->height_units,
                'device_type' => PatchPanel::class, 'device_id' => $pfMuc->id],
            $ausKatalog(10, 'Rangierfeld'),
            $ausKatalog(1, 'Steckdosenleiste (PDU)', 'rear'),
        ], fn ($item) => $item !== null));

        // Ausdruecklich gemischt statt dem Zufall ueberlassen: Bei zehn
        // Ziehungen mit 85 Prozent waere jeder fuenfte Datensatz durchgehend
        // aktiv - und die Demo zeigte den gesperrten Fall dann gar nicht.
        // Eine Domain fuer alle - so sieht ein Firmenverzeichnis aus.
        $adDomain = Str::slug($customer->name).'.de';

        ADUser::factory(7)->beiFirma($adDomain)->create(['customer_id' => $customer->id]);

        // Zwei ausgeschiedene Mitarbeiter: gesperrt, Adresse bleibt stehen.
        ADUser::factory(2)->gesperrt()->beiFirma($adDomain)->create(['customer_id' => $customer->id]);

        // Zwei Dienstkonten - kein Mensch, keine Adresse.
        foreach (['svc-backup', 'svc-scan'] as $dienst) {
            ADUser::factory()->dienstkonto($dienst)->create(['customer_id' => $customer->id]);
        }

        // Eines, dessen Status nie dokumentiert wurde: So sieht der dritte
        // Zustand in der Liste aus, statt nur in der Theorie zu existieren.
        ADUser::factory()->ohneStatus()->beiFirma($adDomain)->create(['customer_id' => $customer->id]);

        ADGroup::factory(5)->create([
            'customer_id' => $customer->id,
        ]);

        // Server – Referenzen für die VM-Hosts merken. Feste Namen, sonst kollidieren
        // sie mit den oben namentlich angelegten SRV-DC01/FS01/HV01.
        $servers = collect(['SRV-APP01', 'SRV-SQL01', 'SRV-BAK01'])
            ->map(fn ($name) => Server::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
                'name' => $name,
            ]));

        // VMs, jeweils einem physischen Host-Server zugeordnet.
        // Namen fest vergeben: die Factory zieht sie zufällig aus einer kurzen
        // Liste, sodass im Demo-Datensatz dieselbe VM mehrfach auftauchte.
        $vms = collect(['VM-DC02', 'VM-Exchange', 'VM-RDS01', 'VM-App-ERP', 'VM-SQL02', 'VM-Webserver'])
            ->map(fn ($name) => VM::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
                'name' => $name,
                'server_id' => $servers->random()->id,
            ]));

        // NAS und Recorder mit festen Namen: die Factory zieht sie zufaellig aus
        // einer kurzen Liste, sonst stehen zwei gleichnamige Geraete in der Doku.
        $nasList = collect(['NAS-Archiv', 'NAS-Fileserver'])->map(fn ($name) => NAS::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'name' => $name,
        ]));

        NetworkSwitch::factory(4)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Accesspoint::factory(5)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Computer::factory(12)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Printer::factory(4)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        IoTDevice::factory(4)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Machine::factory(3)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        OtherClient::factory(3)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        PhoneSystem::factory(1)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Phone::factory(12)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        DECT::factory(5)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        Mailbox::factory(6)->create([
            'customer_id' => $customer->id,
        ]);

        Wifi::factory(4)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        $recorders = collect(['NVR-Zentrale', 'NVR-Werkstatt'])->map(fn ($name) => Recorder::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'name' => $name,
        ]));

        Camera::factory(12)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        ContactPerson::factory(3)->create([
            'customer_id' => $customer->id,
        ]);

        // Logins - feste Namen, weil die Factory sie zufällig aus einer kurzen
        // Liste zieht und sonst dreimal "DATEV" in der Auswahl steht.
        foreach (['DATEV', 'Lexware', 'TeamViewer', 'Microsoft 365 Admin', 'Warenwirtschaft', 'Zeiterfassung'] as $name) {
            LoginGeneral::factory()->create([
                'customer_id' => $customer->id,
                'name' => $name,
            ]);
        }

        // Zwei SSH-Schluessel: einer fuer die Verwaltung aller Linux-Systeme,
        // einer nur fuer die naechtliche Auslagerung. Der erste haengt an
        // mehreren Servern - dafuer stehen die Schluessel bei den Logins.
        $adminKey = SshKey::create([
            'customer_id' => $customer->id,
            'name' => 'Admin ed25519',
            'description' => 'Wartungszugang der Linux-Systeme',
            'username' => 'root',
            'key_type' => 'ed25519',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIH'.Str::random(32).' admin@'.Str::slug($customer->name),
            'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\n".Str::random(64)."\n-----END OPENSSH PRIVATE KEY-----",
            'password' => fake()->password(12, 16),
        ]);

        SshKey::create([
            'customer_id' => $customer->id,
            'name' => 'Backup rsa',
            'description' => 'Nur fuer die naechtliche Auslagerung, ohne Passphrase',
            'username' => 'backup',
            'key_type' => 'rsa',
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQ'.Str::random(48).' backup@'.Str::slug($customer->name),
            'private_key' => "-----BEGIN RSA PRIVATE KEY-----\n".Str::random(96)."\n-----END RSA PRIVATE KEY-----",
        ]);

        // Ein Passwort, viele Systeme - der Fall, für den es die Verknüpfung gibt.
        $rootLogin = LoginGeneral::create([
            'customer_id' => $customer->id,
            'name' => 'Linux root',
            'description' => 'Einheitliches root-Passwort der Linux-VMs',
            'username' => 'root',
            'password' => 'R00t!Demo2026',
        ]);

        $konsolenLogin = LoginGeneral::create([
            'customer_id' => $customer->id,
            'name' => 'Hypervisor-Konsole',
            'description' => 'Lokale Anmeldung an den Host-Servern',
            'username' => 'administrator',
            'password' => 'Hyp3r!Demo2026',
        ]);

        foreach ($vms as $vm) {
            $vm->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $rootLogin->id,
            ]);
        }

        // Alle Server des Kunden, nicht nur die drei aus der Factory: Sonst stehen
        // die namentlich angelegten SRV-DC01/FS01/HV01 oben in der Liste ohne
        // Zugangsdaten, und der Abschnitt sieht aus, als fehle er.
        foreach (Server::where('customer_id', $customer->id)->get() as $server) {
            $server->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $konsolenLogin->id,
            ]);
        }

        // Derselbe Schluessel an mehreren Servern - genau der Grund, warum er
        // einmal dokumentiert und verknuepft wird statt je Server kopiert.
        foreach (Server::where('customer_id', $customer->id)->take(3)->get() as $server) {
            $server->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $adminKey->id,
                'note' => 'SSH',
            ]);
        }

        // Der einzige Fall im Datensatz, in dem die Notiz etwas beiträgt: dasselbe
        // root-Passwort, an der Firewall aber über die serielle Konsole statt SSH.
        // Überall sonst bleibt sie leer - sie soll den Namen nicht wiederholen.
        $firewall = Firewall::where('customer_id', $customer->id)->first();
        $firewall?->credentialLinks()->create([
            'customer_id' => $customer->id,
            'login_general_id' => $rootLogin->id,
            'note' => 'Serielle Konsole',
        ]);

        LoginWebsite::factory(6)->create([
            'customer_id' => $customer->id,
        ]);

        // Geräte-Logins gibt es nicht mehr als eigene Typen - sie sind ein
        // Login-Eintrag plus Verknüpfung, so wie die Migration den Bestand umzieht.
        foreach ($nasList as $nas) {
            $login = LoginGeneral::create([
                'customer_id' => $customer->id,
                'name' => $nas->name.' (admin)',
                'description' => 'Weboberfläche',
                'username' => 'admin',
                'password' => fake()->password(10, 14),
            ]);
            $nas->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $login->id,
            ]);
        }

        foreach ($recorders as $recorder) {
            $login = LoginGeneral::create([
                'customer_id' => $customer->id,
                'name' => $recorder->name.' (admin)',
                'username' => 'admin',
                'password' => fake()->password(10, 14),
            ]);
            $recorder->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $login->id,
            ]);
        }

        // Lizenzen
        LicenseWindows::factory(8)->create([
            'customer_id' => $customer->id,
        ]);

        LicenseSoftware::factory(6)->create([
            'customer_id' => $customer->id,
        ]);

        LicenseAccess::factory(3)->create([
            'customer_id' => $customer->id,
        ]);

        // Dienste
        // Zwei Server: der erste mit drei Zugaengen, der zweite mit einem. Ein
        // Konto ("Backup extern") haengt an beiden - genau der Fall, fuer den
        // die Zugangsdaten am Login stehen und nicht am Geraet.
        $ftpServers = FTPServer::factory(2)->create([
            'customer_id' => $customer->id,
        ]);

        $ftpLogins = collect(['ftp-steuerberater', 'ftp-webdeploy'])
            ->map(fn ($benutzer) => LoginGeneral::create([
                'customer_id' => $customer->id,
                'name' => 'FTP '.$benutzer,
                'username' => $benutzer,
                'password' => fake()->password(12, 16),
            ]));

        $ftpBackup = LoginGeneral::create([
            'customer_id' => $customer->id,
            'name' => 'FTP Backup extern',
            'description' => 'Sammelkonto der naechtlichen Auslagerung',
            'username' => 'backup',
            'password' => fake()->password(12, 16),
        ]);

        foreach ($ftpLogins as $login) {
            $ftpServers->first()->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $login->id,
            ]);
        }

        foreach ($ftpServers as $server) {
            $server->credentialLinks()->create([
                'customer_id' => $customer->id,
                'login_general_id' => $ftpBackup->id,
            ]);
        }

        DynDNS::factory(1)->create([
            'customer_id' => $customer->id,
        ]);

        // Internet-Anschlüsse je Standort
        InternetConnection::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
            'provider' => 'Deutsche Telekom',
            'product' => 'DeutschlandLAN Glasfaser 1000',
            'connection_type' => 'Glasfaser',
            'bandwidth_down' => '1000',
            'bandwidth_up' => '500',
            // Der Hauptanschluss bringt ein geroutetes /28 mit - so zeigt die
            // Demo beide Faelle, mit und ohne eigenes Netz.
            'wan_ip' => '203.0.113.2',
            'subnet' => '203.0.113.16/28',
            'subnet_gateway' => '203.0.113.17',
        ]);

        InternetConnection::factory()->create([
            'customer_id' => $customer->id,
            'site_id' => $site2->id,
            'provider' => 'Vodafone',
            'product' => 'Business Kabel 500',
            'connection_type' => 'Kabel',
            'bandwidth_down' => '500',
            'bandwidth_up' => '50',
        ]);

        // USV
        Ups::factory(2)->create([
            'customer_id' => $customer->id,
            'site_id' => $site1->id,
        ]);

        // Domains (eine läuft demnächst ab -> Dashboard-Warnung)
        Domain::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'mustermann.de',
            'registrar' => 'IONOS',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'nameserver1' => 'ns1.ionos.de',
            'nameserver2' => 'ns2.ionos.de',
        ]);

        Domain::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'mustermann-gmbh.de',
            'registrar' => 'united-domains',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        // SSL/TLS-Zertifikate (eines läuft demnächst ab -> Dashboard-Warnung)
        Certificate::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Wildcard *.mustermann.de',
            'common_name' => '*.mustermann.de',
            'issuer' => "Let's Encrypt",
            'type' => 'Wildcard',
            'issued_date' => now()->subMonths(2)->toDateString(),
            'expiry_date' => now()->addWeeks(3)->toDateString(),
        ]);

        Certificate::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'mail.mustermann.de',
            'common_name' => 'mail.mustermann.de',
            'issuer' => 'Sectigo',
            'type' => 'SSL/TLS',
            'issued_date' => now()->subMonths(1)->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        // Backup-Konzepte
        Backup::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Veeam – VMs täglich',
            'software' => 'Veeam Backup & Replication',
            'source' => 'Hyper-V (alle VMs)',
            'destination' => 'NAS-Backup',
            'schedule' => 'täglich 22:00',
            'retention' => '30 Tage',
            'last_success' => now()->subDay()->toDateString(),
        ]);

        Backup::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Cloud-Backup Fileserver',
            'software' => 'Synology Hyper Backup',
            'source' => 'NAS-Fileserver',
            'destination' => 'Wasabi Cloud',
            'schedule' => 'täglich 01:00',
            'retention' => '90 Tage',
            'last_success' => now()->toDateString(),
        ]);

        $this->call([
            CustomerSeeder::class,
            // Zum Schluss, damit die Historie auf fertigen Daten aufsetzt und
            // im Protokoll ganz oben steht.
            DemoProtokollSeeder::class,
        ]);
    }
}
