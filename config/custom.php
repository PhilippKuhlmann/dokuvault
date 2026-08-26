<?php

use App\Http\Requests\AccesspointRequest;
use App\Http\Requests\ADDomainRequest;
use App\Http\Requests\BackupRequest;
use App\Http\Requests\ComputerRequest;
use App\Http\Requests\ContactPersonRequest;
use App\Http\Requests\FirewallRequest;
use App\Http\Requests\InternetConnectionRequest;
use App\Http\Requests\NASRequest;
use App\Http\Requests\NetworkRequest;
use App\Http\Requests\NetworkSwitchRequest;
use App\Http\Requests\PhoneSystemRequest;
use App\Http\Requests\PrinterRequest;
use App\Http\Requests\RouterRequest;
use App\Http\Requests\ServerRequest;
use App\Http\Requests\SiteRequest;
use App\Http\Requests\VMRequest;
use App\Http\Requests\WifiRequest;
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
use App\Models\DECT;
use App\Models\Domain;
use App\Models\DynDNS;
use App\Models\Firewall;
use App\Models\FTPServer;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
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
use App\Models\OtherClient;
use App\Models\PatchPanel;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Rack;
use App\Models\Recorder;
use App\Models\Router;
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Ups;
use App\Models\VM;
use App\Models\Wifi;

// Vor dem return, damit sowohl die eigenen Konfigurationsschluessel als auch
// die Felder des Erstaufnahme-Assistenten dieselbe Liste benutzen.
// Verfuegbare Sprachen der Oberflaeche. Der Schluessel ist der Locale-Code,
// der Wert steht in der Auswahl - bewusst in der jeweiligen Sprache, damit man
// die eigene auch findet, wenn die Oberflaeche gerade fremd ist.
$sprachen = [
    'de' => 'Deutsch',
    'en' => 'English',
];

$serverBauformen = [
    'rack' => '19-Zoll (Rackeinbau)',
    'tower' => 'Standserver (Tower)',
];

$serverTiefen = [
    1 => 'Volle Tiefe',
    0 => 'Halbe Tiefe',
];

return [
    'locales' => $sprachen,

    'permissions' => [
        'Site',
        'ContactPerson',
        'Server',
        'VM',
        'NAS',
        'Firewall',
        'Router',
        'Network',
        'NetworkSwitch',
        'Wifi',
        'Accesspoint',
        'Computer',
        'IoTDevice',
        'Machine',
        'OtherClient',
        'Printer',
        'ADDomain',
        'ADUser',
        'ADGroup',
        'PhoneSystem',
        'Phone',
        'DECT',
        'LoginGeneral',
        'LoginWebsite',
        'SshKey',
        'SecurepointUMA',
        'Mailbox',
        'Recorder',
        'Camera',
        'LicenseSoftware',
        'LicenseWindows',
        'LicenseAccess',
        'FTPServer',
        'DynDNS',
        'File',
        'Ups',
        'InternetConnection',
        'Domain',
        'Certificate',
        'Backup',
        'Rack',
        'PatchPanel',
        'Cluster',
    ],

    /*
     * Whitelist für den Papierkorb: URL-Slug => [Model-Klasse, Anzeigename].
     * Nur Models mit customer_id und SoftDeletes aufnehmen.
     */
    'trashables' => [
        'site' => [Site::class, 'Standort'],
        'contactperson' => [ContactPerson::class, 'Ansprechpartner'],
        'server' => [Server::class, 'Server'],
        'cluster' => [Cluster::class, 'Cluster'],
        'vm' => [VM::class, 'VM'],
        'nas' => [NAS::class, 'NAS'],
        'securepointuma' => [SecurepointUMA::class, 'E-Mail-Archivierung'],
        'firewall' => [Firewall::class, 'Firewall'],
        'router' => [Router::class, 'Router'],
        'network' => [Network::class, 'Netzwerk'],
        'networkswitch' => [NetworkSwitch::class, 'Switch'],
        'rack' => [Rack::class, 'Serverschrank'],
        'patchpanel' => [PatchPanel::class, 'Patchfeld'],
        'wifi' => [Wifi::class, 'WLAN'],
        'accesspoint' => [Accesspoint::class, 'Accesspoint'],
        'computer' => [Computer::class, 'Computer'],
        'iotdevice' => [IoTDevice::class, 'IoT-Gerät'],
        'machine' => [Machine::class, 'Maschine'],
        'otherclient' => [OtherClient::class, 'Sonstiger Client'],
        'printer' => [Printer::class, 'Drucker'],
        'addomain' => [ADDomain::class, 'AD-Domäne'],
        'aduser' => [ADUser::class, 'AD-Benutzer'],
        'adgroup' => [ADGroup::class, 'AD-Gruppe'],
        'phonesystem' => [PhoneSystem::class, 'TK-Anlage'],
        'phone' => [Phone::class, 'Telefon'],
        'dect' => [DECT::class, 'DECT'],
        'logingeneral' => [LoginGeneral::class, 'Login Allgemein'],
        'sshkey' => [SshKey::class, 'SSH-Schlüssel'],
        'loginwebsite' => [LoginWebsite::class, 'Login Webseite'],
        'mailbox' => [Mailbox::class, 'Postfach'],
        'recorder' => [Recorder::class, 'Recorder'],
        'camera' => [Camera::class, 'Kamera'],
        'licensesoftware' => [LicenseSoftware::class, 'Software-Lizenz'],
        'licensewindows' => [LicenseWindows::class, 'Windows-Lizenz'],
        'licenseaccess' => [LicenseAccess::class, 'CAL-Lizenz'],
        'ftpserver' => [FTPServer::class, 'FTP-Server'],
        'dyndns' => [DynDNS::class, 'DynDNS'],
        'ups' => [Ups::class, 'USV'],
        'internetconnection' => [InternetConnection::class, 'Internet-Anschluss'],
        'domain' => [Domain::class, 'Domain'],
        'certificate' => [Certificate::class, 'Zertifikat'],
        'backup' => [Backup::class, 'Backup'],
    ],

    /**
     * Überschrift der Listen-Seiten. Die trashables-Labels oben benennen ein
     * einzelnes Objekt ("Zertifikat wiederhergestellt") und passen deshalb nicht
     * als Titel einer Liste. Hier steht die Mehrzahl, und zwar in der Schreibweise
     * aus der Seitenleiste — der Nutzer klickt dort und erwartet oben dasselbe Wort.
     * Fehlt ein Schlüssel, greift automatisch das trashables-Label.
     */
    'list_titles' => [
        'site' => 'Standorte',
        'vm' => 'VMs',
        'network' => 'VLANs',
        'wifi' => 'WLAN-Netze',
        'machine' => 'Maschinen',
        'otherclient' => 'Sonstige Clients',
        'printer' => 'Drucker',
        'addomain' => 'AD-Domänen',
        'aduser' => 'AD-Benutzer',
        'adgroup' => 'AD-Gruppen',
        'phonesystem' => 'TK-Anlagen',
        'phone' => 'Telefone',
        'logingeneral' => 'Logins Allgemein',
        'sshkey' => 'SSH-Schlüssel',
        'loginwebsite' => 'Logins Webseiten',
        'mailbox' => 'E-Mail-Postfächer',
        'recorder' => 'Recorder',
        'camera' => 'Kameras',
        'licensesoftware' => 'Software-Lizenzen',
        'licensewindows' => 'Windows-Lizenzen',
        'licenseaccess' => 'CAL-Lizenzen',
        'ftpserver' => 'FTP-Server',
        'internetconnection' => 'Internet-Anschlüsse',
        'domain' => 'Domains',
        'certificate' => 'Zertifikate',
        'backup' => 'Backups',
        'iotdevice' => 'IoT-Geräte',
        'networkswitch' => 'Switches',
        'rack' => 'Serverschränke',
        'patchpanel' => 'Patchfelder',
        'cluster' => 'Cluster',
        'accesspoint' => 'Accesspoints',
        'contactperson' => 'Ansprechpartner',
    ],

    /**
     * Überschrift der Admin-Listenseiten, Gegenstück zu list_titles oben.
     *
     * Stand vorher als $adminTitles roh in resources/views/components/sitetopmenu.blade.php -
     * ohne __(), waehrend der Nicht-Admin-Zweig direkt daneben schon uebersetzt wurde. Im
     * Menue stand deshalb "Rack catalogue", in der Ueberschrift "Rack-Katalog". Hier statt
     * dort, damit es dieselbe Quelle wie list_titles ist und der Uebersetzungs-Test
     * (LocaleTest) es findet.
     */
    'admin_list_titles' => [
        'admin.customer' => 'Kunden',
        'admin.role' => 'Rollen',
        'admin.user' => 'Benutzer',
        'admin.mailboxprovider' => 'Postfach-Anbieter',
        'admin.operatingsystem' => 'Betriebssysteme',
        'admin.rackcatalogitem' => 'Rack-Katalog',
        'admin.service' => 'Dienste',
        'admin.eol' => 'Support-Ende (EOL)',
    ],

    /**
     * Schritte des Dokumentations-Assistenten (App\Livewire\DocumentationWizard).
     * Jeder Schritt fragt eine Teilmenge der Felder des zugehörigen FormRequest ab -
     * Pflichtfelder plus das fachlich Wichtigste, nicht das komplette Formular.
     *
     * 'request' liefert die Basis-Validierung (App\Livewire\DocumentationWizard::rulesForStep()
     * übernimmt die Regeln daraus für genau die hier gelisteten Felder und ersetzt, was in
     * Livewire nicht funktioniert: BelongsToCustomer braucht die {customer}-Route, die es unter
     * /livewire/update nicht gibt - wird durch Rule::exists(...)->where('customer_id', ...) ersetzt).
     *
     * 'scope': 'site' => Datensatz bekommt automatisch die run.site_id; 'customer' => Modell hat
     * gar kein site_id (contact_people, ad_domains, backups - geprüft in den Migrationen).
     *
     * 'label_field': welches Feld einen vorhandenen Eintrag in der Liste identifiziert.
     *
     * Reihenfolge ist bewusst: 'wifi' braucht ein bereits angelegtes VLAN (network_id),
     * steht deshalb zwingend nach 'network'.
     */
    'wizard_steps' => [
        [
            'key' => 'site', 'group' => 'Grunddaten', 'label' => 'Standorte',
            'question' => 'An welchen Standorten arbeitet der Kunde?',
            'model' => Site::class, 'relation' => 'sites',
            'request' => SiteRequest::class, 'permission' => 'site_create',
            'scope' => 'customer', 'label_field' => 'name', 'sets_site' => true,
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'street', 'label' => 'Straße', 'type' => 'text'],
                ['name' => 'house_number', 'label' => 'Nr.', 'type' => 'text'],
                ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text'],
                ['name' => 'city', 'label' => 'Ort', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'contactperson', 'group' => 'Grunddaten', 'label' => 'Ansprechpartner',
            'question' => 'Wer sind die Ansprechpartner beim Kunden?',
            'model' => ContactPerson::class, 'relation' => 'contactpersons',
            'request' => ContactPersonRequest::class, 'permission' => 'contactperson_create',
            'scope' => 'customer', 'label_field' => 'last_name',
            'fields' => [
                ['name' => 'first_name', 'label' => 'Vorname', 'type' => 'text'],
                ['name' => 'last_name', 'label' => 'Nachname', 'type' => 'text'],
                ['name' => 'phone', 'label' => 'Telefon', 'type' => 'text'],
                ['name' => 'mail', 'label' => 'E-Mail', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'internetconnection', 'group' => 'Netzwerk', 'label' => 'Internet-Anschlüsse',
            'question' => 'Welche Internet-Anschlüsse gibt es?',
            'model' => InternetConnection::class, 'relation' => 'internetconnections',
            'request' => InternetConnectionRequest::class, 'permission' => 'internetconnection_create',
            'scope' => 'site', 'label_field' => 'provider',
            'fields' => [
                ['name' => 'provider', 'label' => 'Anbieter', 'type' => 'text'],
                ['name' => 'product', 'label' => 'Produkt', 'type' => 'text'],
                ['name' => 'connection_type', 'label' => 'Anschlussart', 'type' => 'text'],
                ['name' => 'wan_ip', 'label' => 'WAN-IP', 'type' => 'text'],
                ['name' => 'bandwidth_down', 'label' => 'Download', 'type' => 'text'],
                ['name' => 'bandwidth_up', 'label' => 'Upload', 'type' => 'text'],
                ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
                ['name' => 'contract_number', 'label' => 'Vertragsnummer', 'type' => 'text'],
                ['name' => 'hotline', 'label' => 'Hotline', 'type' => 'text'],
                ['name' => 'subnet', 'label' => 'Netz (optional, z. B. 203.0.113.16/28)', 'type' => 'text'],
                ['name' => 'subnet_gateway', 'label' => 'Gateway des Netzes', 'type' => 'text'],
                ['name' => 'pppoe_user', 'label' => 'Einwahl-Benutzer (PPPoE)', 'type' => 'text'],
                ['name' => 'pppoe_password', 'label' => 'Einwahl-Passwort', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'firewall', 'group' => 'Netzwerk', 'label' => 'Firewall',
            'question' => 'Welche Firewall schützt das Netz?',
            'model' => Firewall::class, 'relation' => 'firewalls',
            'request' => FirewallRequest::class, 'permission' => 'firewall_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
                ['name' => 'firmware', 'label' => 'Firmware', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'management_url', 'label' => 'Verwaltungsoberfläche', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
                ['name' => 'subscription_until', 'label' => 'Subscription bis', 'type' => 'date'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'router', 'group' => 'Netzwerk', 'label' => 'Router',
            'question' => 'Welche Router sind im Einsatz?',
            'model' => Router::class, 'relation' => 'routers',
            'request' => RouterRequest::class, 'permission' => 'router_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'number', 'default' => '443'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'network', 'group' => 'Netzwerk', 'label' => 'VLANs',
            'question' => 'Welche VLANs gibt es?',
            'model' => Network::class, 'relation' => 'networks',
            'request' => NetworkRequest::class, 'permission' => 'network_create',
            'scope' => 'site', 'label_field' => 'description',
            'fields' => [
                ['name' => 'description', 'label' => 'Bezeichnung', 'type' => 'text'],
                ['name' => 'vlanId', 'label' => 'VLAN-ID', 'type' => 'number'],
                ['name' => 'network', 'label' => 'Netzadresse', 'type' => 'text', 'placeholder' => '10.10.20.0'],
                // 'sofort': Die beiden rechnen sich gegenseitig aus und muessen
                // dafuer beim Verlassen des Feldes zum Server.
                ['name' => 'subnetmask', 'label' => 'Subnetzmaske', 'type' => 'text', 'default' => '255.255.255.0', 'sofort' => true],
                ['name' => 'cidr', 'label' => 'CIDR', 'type' => 'number', 'default' => '24', 'sofort' => true],
                ['name' => 'gateway', 'label' => 'Gateway', 'type' => 'text'],
                ['name' => 'dns1', 'label' => 'DNS 1', 'type' => 'text'],
                ['name' => 'dns2', 'label' => 'DNS 2', 'type' => 'text'],
                ['name' => 'dhcpStart', 'label' => 'DHCP-Start', 'type' => 'text'],
                ['name' => 'dhcpEnd', 'label' => 'DHCP-Ende', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'wifi', 'group' => 'Netzwerk', 'label' => 'WLAN-Netze',
            'question' => 'Welche WLANs werden ausgestrahlt?',
            'model' => Wifi::class, 'relation' => 'wifis',
            'request' => WifiRequest::class, 'permission' => 'wifi_create',
            'scope' => 'site', 'label_field' => 'ssid',
            // password/network_id sind in wifis NOT NULL, obwohl WifiRequest sie nicht als
            // 'required' führt (geprüft in der Migration) - siehe DocumentationWizard::RULE_OVERRIDES.
            'fields' => [
                ['name' => 'ssid', 'label' => 'SSID', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
                ['name' => 'network_id', 'label' => 'VLAN', 'type' => 'select', 'options' => 'networks'],
                ['name' => 'encryption', 'label' => 'Verschlüsselung', 'type' => 'select', 'options' => [
                    'WPA2' => 'WPA2', 'WPA3' => 'WPA3', 'offen' => 'offen',
                ]],
            ],
        ],
        [
            'key' => 'networkswitch', 'group' => 'Netzwerk', 'label' => 'Switches',
            'question' => 'Welche Switches sind verbaut?',
            'model' => NetworkSwitch::class, 'relation' => 'networkswitches',
            'request' => NetworkSwitchRequest::class, 'permission' => 'networkswitch_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'accesspoint', 'group' => 'Netzwerk', 'label' => 'Accesspoints',
            'question' => 'Welche Accesspoints sind verbaut?',
            'model' => Accesspoint::class, 'relation' => 'accesspoints',
            'request' => AccesspointRequest::class, 'permission' => 'accesspoint_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'server', 'group' => 'Server & Speicher', 'label' => 'Server',
            'question' => 'Welche physischen Server stehen beim Kunden?',
            'model' => Server::class, 'relation' => 'servers',
            'request' => ServerRequest::class, 'permission' => 'server_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'select', 'options' => 'operatingSystems'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'form_factor', 'label' => 'Bauform', 'type' => 'select', 'options' => $serverBauformen, 'default' => 'rack'],
                ['name' => 'full_depth', 'label' => 'Einbautiefe', 'type' => 'select', 'options' => $serverTiefen, 'default' => 1],
                ['name' => 'height_units', 'label' => 'Höheneinheiten (HE)', 'type' => 'number', 'default' => 1],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'bmcIp', 'label' => 'BMC IP', 'type' => 'text'],
                ['name' => 'bmcUser', 'label' => 'BMC User', 'type' => 'text'],
                ['name' => 'bmcPassword', 'label' => 'BMC Passwort', 'type' => 'text'],
                ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text'],
                ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'vm', 'group' => 'Server & Speicher', 'label' => 'VMs',
            'question' => 'Welche virtuellen Maschinen laufen?',
            'model' => VM::class, 'relation' => 'vms',
            'request' => VMRequest::class, 'permission' => 'vm_create',
            'scope' => 'site', 'label_field' => 'name',
            // operating_system_id ist in vms NOT NULL, obwohl VMRequest es nicht verlangt
            // (geprüft in der Migration) - siehe DocumentationWizard::RULE_OVERRIDES.
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'server_id', 'label' => 'Host-Server', 'type' => 'select', 'options' => 'servers'],
                ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'select', 'options' => 'operatingSystems'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text'],
                ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'nas', 'group' => 'Server & Speicher', 'label' => 'NAS',
            'question' => 'Welche NAS-Systeme sind im Einsatz?',
            'model' => NAS::class, 'relation' => 'nas',
            'request' => NASRequest::class, 'permission' => 'nas_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'computer', 'group' => 'Clients', 'label' => 'Computer',
            'question' => 'Welche Arbeitsplatzrechner gibt es?',
            'model' => Computer::class, 'relation' => 'computers',
            'request' => ComputerRequest::class, 'permission' => 'computer_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'select', 'options' => 'operatingSystems'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text'],
                ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'printer', 'group' => 'Clients', 'label' => 'Drucker',
            'question' => 'Welche Drucker sind im Einsatz?',
            'model' => Printer::class, 'relation' => 'printers',
            'request' => PrinterRequest::class, 'permission' => 'printer_create',
            'scope' => 'site', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzer', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'addomain', 'group' => 'Dienste', 'label' => 'AD-Domänen',
            'question' => 'Welche Active-Directory-Domäne wird betrieben?',
            'model' => ADDomain::class, 'relation' => 'addomains',
            'request' => ADDomainRequest::class, 'permission' => 'addomain_create',
            'scope' => 'customer', 'label_field' => 'domain',
            'fields' => [
                ['name' => 'domain', 'label' => 'Domäne', 'type' => 'text', 'placeholder' => 'firma.local'],
                ['name' => 'netbios', 'label' => 'NetBIOS-Name', 'type' => 'text'],
                ['name' => 'dsrmpassword', 'label' => 'DSRM-Passwort', 'type' => 'password'],
            ],
        ],
        [
            'key' => 'phonesystem', 'group' => 'Dienste', 'label' => 'TK-Anlagen',
            'question' => 'Welche Telefonanlage ist im Einsatz?',
            'model' => PhoneSystem::class, 'relation' => 'phonesystems',
            'request' => PhoneSystemRequest::class, 'permission' => 'phonesystem_create',
            'scope' => 'site', 'label_field' => 'manufacturer',
            'fields' => [
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
                ['name' => 'ip_address', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ],
        ],
        [
            'key' => 'backup', 'group' => 'Dienste', 'label' => 'Backups',
            'question' => 'Wie wird gesichert?',
            'model' => Backup::class, 'relation' => 'backups',
            'request' => BackupRequest::class, 'permission' => 'backup_create',
            'scope' => 'customer', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'software', 'label' => 'Software', 'type' => 'text'],
                ['name' => 'destination', 'label' => 'Ziel', 'type' => 'text'],
                ['name' => 'schedule', 'label' => 'Zeitplan', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
                ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
                ['name' => 'source', 'label' => 'Quelle', 'type' => 'text'],
                ['name' => 'retention', 'label' => 'Aufbewahrung', 'type' => 'text'],
                ['name' => 'last_success', 'label' => 'Letzter Erfolg', 'type' => 'text'],
            ],
        ],
    ],

    /*
     * Geraetetypen, die sich in ein Rack einbauen lassen.
     * Schluessel = stabiler Bezeichner im Editor (nie Klassennamen vom Client annehmen),
     * Wert = [Model-Klasse, Anzeigename, Darstellung in der Frontansicht].
     * Reihenfolge = Reihenfolge der Palette.
     * Voraussetzung: Model hat customer_id und ein name-Feld.
     */
    /*
    |--------------------------------------------------------------------------
    | Bauform von Servern
    |--------------------------------------------------------------------------
    |
    | Nur Server der Bauform 'rack' lassen sich in einen Serverschrank
    | einbauen - ein Standserver steht daneben.
    |
    */
    'server_form_factors' => $serverBauformen,

    /*
    |--------------------------------------------------------------------------
    | Art eines Server-Clusters
    |--------------------------------------------------------------------------
    |
    | Womit die Knoten ihre Daten zusammenhalten. Bewusst als Konfiguration
    | statt als Datenbank-Enum: Eine neue Technik ist damit eine Zeile hier,
    | keine Migration. "Sonstiges" faengt ab, was die Liste nicht kennt - dann
    | steht die Erklaerung in der Notiz.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Stellen, an denen ein eigenes Logo stehen kann
    |--------------------------------------------------------------------------
    |
    | Drei statt einer: Das Logo auf der Anmeldeseite darf gross und breit
    | sein, das in der Kopfzeile muss neben den Namen passen, ein Favicon ist
    | quadratisch. Hier statt im Controller, damit der Uebersetzungs-Test die
    | Beschriftungen findet - sie laufen erst zur Laufzeit durch __().
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Dateiarten
    |--------------------------------------------------------------------------
    |
    | Beschriftung und zugehoerige Endungen. Bestimmt Symbolfarbe in der Liste
    | und den Art-Filter. Hier statt im Model, damit Beschriftung und Endungen
    | zusammenbleiben - und damit der Uebersetzungs-Test die Beschriftungen
    | findet, die erst zur Laufzeit durch __() laufen.
    |
    | Was in keiner Liste steht, ist "Sonstige" - das laesst sich nicht
    | aufzaehlen, nur als Gegenteil formulieren.
    |
    */
    'file_arten' => [
        'pdf' => ['PDF', ['pdf']],
        'bild' => ['Bild', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'heic']],
        'text' => ['Text', ['doc', 'docx', 'odt', 'rtf', 'txt', 'md']],
        'tabelle' => ['Tabelle', ['xls', 'xlsx', 'ods', 'csv']],
        'archiv' => ['Archiv', ['zip', 'rar', '7z', 'tar', 'gz']],
    ],

    'branding_logos' => [
        'login' => ['Anmeldeseite', 'Steht gross über dem Anmeldeformular.'],
        'header' => ['Kopfzeile', 'Steht oben links neben dem Namen — ein schmales, breites Logo passt hier am besten.'],
        'favicon' => ['Favicon', 'Das Symbol im Browser-Tab. Quadratisch, mindestens 32 × 32 Pixel.'],
    ],

    'cluster_types' => [
        'ceph' => 'Ceph (verteilter Speicher)',
        'replication' => 'Replikation (ZFS/Storage-Replikation)',
        'shared_storage' => 'Gemeinsamer Speicher (SAN/NFS/iSCSI)',
        'local' => 'Nur lokaler Speicher (ohne Replikation)',
        'other' => 'Sonstiges',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bauform einer Firewall
    |--------------------------------------------------------------------------
    |
    | Eine Appliance steht im Schrank, eine virtuelle Firewall (OPNsense,
    | pfSense, auch die Securepoint UTM) laeuft auf einem Host. Die Bauform
    | entscheidet, ob ein Einbau ueberhaupt in Frage kommt.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Fernwartungsloesungen
    |--------------------------------------------------------------------------
    |
    | Welche im Haus benutzt wird, stellt ein Admin unter Einstellungen ein;
    | hier stehen die Muster. {id} und {password} werden ersetzt.
    |
    | Nur RustDesk uebergibt das Kennwort im Link. TeamViewer und AnyDesk
    | koennen das nicht - dort oeffnet der Knopf die Verbindung, das Kennwort
    | steht weiterhin zum Kopieren daneben. Ein Muster ohne {password} ist
    | deshalb kein Fehler, sondern der Normalfall.
    |
    */
    'remote_tools' => [
        'rustdesk' => [
            'label' => 'RustDesk',
            'url' => 'rustdesk://connection/new/{id}?password={password}',
            'icon' => 'svg.software.rustdesk',
            'id_label' => 'RustDesk ID',
            'password_label' => 'RustDesk Passwort',
        ],
        'teamviewer' => [
            'label' => 'TeamViewer',
            'url' => 'teamviewer10://control?device={id}',
            'icon' => 'svg.remote',
            'id_label' => 'TeamViewer ID',
            'password_label' => 'TeamViewer Kennwort',
        ],
        'anydesk' => [
            'label' => 'AnyDesk',
            'url' => 'anydesk:{id}',
            'icon' => 'svg.remote',
            'id_label' => 'AnyDesk-Adresse',
            'password_label' => 'AnyDesk Kennwort',
        ],
        'custom' => [
            'label' => 'Andere Lösung',
            // Wird durch das im Admin-Bereich hinterlegte Muster ersetzt.
            'url' => '',
            'icon' => 'svg.remote',
            'id_label' => 'Fernwartungs-ID',
            'password_label' => 'Fernwartungs-Kennwort',
        ],
    ],

    'firewall_form_factors' => [
        'appliance' => 'Appliance',
        'vm' => 'Virtuelle Maschine',
    ],

    /*
    |--------------------------------------------------------------------------
    | Einbautiefe
    |--------------------------------------------------------------------------
    |
    | Belegt das Geraet die volle Schranktiefe, oder bleibt hinter ihm Platz
    | fuer einen weiteren Einbau? Wird fuer die kommende Rueckansicht der
    | Schraenke gebraucht.
    |
    */
    'server_depths' => $serverTiefen,

    'rack_device_types' => [
        'server' => [Server::class, 'Server', 'server'],
        'networkswitch' => [NetworkSwitch::class, 'Switch', 'switch'],
        'patchpanel' => [PatchPanel::class, 'Patchfeld', 'patchpanel'],
        'nas' => [NAS::class, 'NAS', 'nas'],
        'router' => [Router::class, 'Router', 'router'],
        'firewall' => [Firewall::class, 'Firewall', 'router'],
        'ups' => [Ups::class, 'USV', 'ups'],
        // PhoneSystem fehlt bewusst: die Tabelle hat keine name-Spalte,
        // ein Einbau hätte in der Rack-Ansicht keinen Anzeigenamen.
        'recorder' => [Recorder::class, 'Recorder', 'server'],
        'securepointuma' => [SecurepointUMA::class, 'E-Mail-Archiv', 'server'],
    ],

    /*
     * Benutzer, die auf einer Demo-Instanz (DEMO_MODE=true) nicht geloescht
     * und deren Passwort und Rolle nicht geaendert werden koennen.
     *
     * Ohne diesen Schutz sperrt der erste Besucher, der das Admin-Passwort
     * aendert oder das Konto loescht, alle uebrigen aus - bis zum naechsten
     * stuendlichen Reset. Ausserhalb des Demo-Modus wirkt die Liste nicht.
     */
    'demo_protected_users' => ['admin', 'techniker', 'kunde-rw', 'kunde-r'],

    /*
    |--------------------------------------------------------------------------
    | Spalten, deren Inhalt nie ins Protokoll darf
    |--------------------------------------------------------------------------
    |
    | spatie/activitylog liest die Werte ueber die Eloquent-Accessoren - ein
    | verschluesseltes Feld stuende sonst im Klartext im Protokoll, alter und
    | neuer Wert.
    |
    | Geschrieben wie die SPALTE heisst, nicht wie die Accessor-Methode: Die
    | Liste nannte einmal "uscpin" und "cloudBackupPassword", die Spalten heissen
    | "usc_pin" und "cloud_backup_password" - und damit standen USC-PIN und
    | Cloud-Backup-Kennwort im Protokoll. Ein Test in VerschluesselteSpaltenTest
    | gleicht diese Liste gegen die tatsaechlich verschluesselten Spalten ab.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Verfahren eines SSH-Schluessels
    |--------------------------------------------------------------------------
    |
    | Feste Liste statt Freitext: "ed25519", "ED25519" und "Ed25519" waeren
    | sonst drei Verfahren. Die Beschriftungen sind Eigennamen und werden
    | nicht uebersetzt.
    |
    */

    'ssh_key_types' => [
        'ed25519' => 'Ed25519',
        'ecdsa' => 'ECDSA',
        'rsa' => 'RSA',
    ],

    'secret_columns' => [
        'password',
        'private_key',
        'remotePassword',
        'bmcPassword',
        'dsrmpassword',
        'cloud_backup_password',
        'pppoe_password',
        'encryptionkey',
        'usc_pin',
        'remember_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Davon sind keine Kennwoerter
    |--------------------------------------------------------------------------
    |
    | Das remember_token ist ein Sitzungsmerkmal: Es aendert sich bei jeder
    | Anmeldung und waere als "Kennwort geaendert" schlicht falsch.
    |
    */
    'non_password_secrets' => ['remember_token'],

    /*
    |--------------------------------------------------------------------------
    | Wie ein Kennwortfeld heisst, wenn man es benennen muss
    |--------------------------------------------------------------------------
    |
    | Ein Geraet hat oft mehrere Kennwoerter. "Kennwort geaendert" allein waere
    | die halbe Auskunft - welches denn?
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Ereignisse im Aktivitaetsprotokoll
    |--------------------------------------------------------------------------
    |
    | Beschriftung und Farbe je Ereignis. "password_changed" ist keines von
    | spatie, sondern unseres: Der Wert eines Kennworts darf nie ins Protokoll,
    | die Tatsache der Aenderung sehr wohl.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Rechte fuer den Admin-Bereich
    |--------------------------------------------------------------------------
    |
    | Bis hierhin hing alles unter /admin an einer harten Pruefung auf die
    | Rolle 1: entweder ganz oder gar nicht. Eine zweite Technikergruppe, die
    | zum Beispiel den Papierkorb und das Protokoll sehen soll, aber keine
    | Benutzer anlegen darf, war damit nicht baubar.
    |
    | Ein Recht je Menuepunkt. Sie tauchen in der Rollenverwaltung von selbst
    | auf - dort landet alles, was nicht ins Sehen/Erstellen/Bearbeiten/Loeschen-
    | Schema passt, im Abschnitt "Weitere Rechte".
    |
    | Die Rolle 1 darf unabhaengig davon alles (Gate::before im
    | AppServiceProvider). Das schuetzt davor, sich selbst auszusperren: Wer
    | versehentlich "Rollen verwalten" abwaehlt, kaeme sonst nie wieder an die
    | Rollenverwaltung.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Woran man ein Objekt erkennt
    |--------------------------------------------------------------------------
    |
    | Nicht jedes Model hat eine name-Spalte: Ein WLAN heisst ssid, eine
    | IP-Adresse address, ein Postfach mailAdress. Protokoll und Papierkorb
    | stellen dieselbe Frage - welcher Eintrag ist das eigentlich - und
    | beantworten sie jetzt aus derselben Liste. Die Reihenfolge entscheidet.
    |
    | Ein Model, das etwas Besseres weiss, ueberschreibt protokollName().
    |
    */
    'name_fields' => [
        'name',
        'ssid',
        'address',
        'username',
        'mailAdress',
        'domain',
        'provider',
        'description',
        'host',
        'key',
    ],

    'admin_permissions' => [
        // Die Beschreibung steht so in der Rollenverwaltung - deshalb mit
        // Umlauten, anders als die Kommentare in dieser Datei.
        'admin_customer' => 'Kunden verwalten',
        'admin_user' => 'Benutzer verwalten',
        'admin_role' => 'Rollen und Rechte verwalten',
        'admin_catalog' => 'Auswahlmenüs verwalten',
        'admin_operatingsystem' => 'Support-Ende (EOL) sehen',
        'admin_setting' => 'Einstellungen der Installation',
        'admin_trash' => 'Papierkorb über alle Kunden',
        'admin_activity' => 'Protokoll sehen',
        'admin_apitoken' => 'API-Token verwalten',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rechte ausserhalb des Admin-Bereichs
    |--------------------------------------------------------------------------
    |
    | Die Fernwartungs-Suche hing an einer harten Pruefung auf die Rolle 10.
    | Eine zweite Technikergruppe haette sie damit nicht oeffnen koennen -
    | genau das, was hier moeglich werden soll.
    |
    */
    'extra_permissions' => [
        'remote_search' => 'Fernwartungs-Suche benutzen',
    ],

    'activity_events' => [
        'created' => ['Erstellt', 'text-green-700 bg-green-50 dark:text-green-400 dark:bg-green-900/30'],
        'updated' => ['Geändert', 'text-cerulean-700 bg-cerulean-50 dark:text-cerulean-400 dark:bg-gray-700'],
        'deleted' => ['Gelöscht', 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-900/30'],
        'restored' => ['Wiederhergestellt', 'text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/30'],
        'password_changed' => ['Kennwort geändert', 'text-purple-700 bg-purple-50 dark:text-purple-300 dark:bg-purple-900/30'],
    ],

    'secret_field_labels' => [
        'password' => 'Kennwort',
        'remotePassword' => 'Fernwartungs-Kennwort',
        'bmcPassword' => 'BMC-Kennwort',
        'dsrmpassword' => 'DSRM-Kennwort',
        'cloud_backup_password' => 'Cloud-Backup-Kennwort',
        'pppoe_password' => 'PPPoE-Kennwort',
        'encryptionkey' => 'Verschlüsselungsschlüssel',
        'usc_pin' => 'USC-PIN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Aufzeichnung der Herkunft auf der Demo
    |--------------------------------------------------------------------------
    |
    | Steuert, ob RecordDemoUsage die IP-Adresse mitschreibt:
    |
    |   'aus'    - gar nicht, die Aufzeichnung bleibt ohne Personenbezug
    |   'anonym' - gekuerzt auf /24 (IPv4) bzw. /48 (IPv6), Standard
    |   'voll'   - vollstaendig
    |
    | 'anonym' ist der Standard, weil es die Frage "woher kommen die Besucher"
    | genauso beantwortet: eine GeoIP-Abfrage liefert aus 91.65.42.0 dasselbe
    | Land wie aus der vollen Adresse. Die vollstaendige IP ist ein
    | personenbezogenes Datum und gehoert dann in die Datenschutzerklaerung.
    |
    */
    'demo_ip_logging' => env('DEMO_IP_LOGGING', 'anonym'),

    /*
    |--------------------------------------------------------------------------
    | Vertrauenswuerdige Proxys
    |--------------------------------------------------------------------------
    |
    | Steht ein Reverse-Proxy vor der App, sieht sie dessen Adresse statt der
    | des Besuchers - es sei denn, der Proxy steht hier. Dann wird stattdessen
    | X-Forwarded-For ausgewertet.
    |
    | Mehrere Eintraege mit Komma trennen, CIDR ist erlaubt:
    |
    |   TRUSTED_PROXIES=172.18.0.0/16,127.0.0.1
    |
    | '*' vertraut jedem. Bequem, aber dann kann jeder, der die App direkt
    | erreicht, per X-Forwarded-For eine beliebige Herkunft vortaeuschen -
    | vertretbar nur, wenn ausschliesslich der Proxy sie erreicht.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', '10.0.254.2'),

    /*
     * Darstellungen der gezeichneten Frontansicht (x-rack.face).
     * Geraete bekommen sie aus rack_device_types, Katalogelemente waehlt der
     * Admin je Eintrag aus dieser Liste.
     */
    'rack_appearances' => [
        'server' => 'Server (Laufwerksschächte)',
        'switch' => 'Switch (RJ45-Ports)',
        'nas' => 'NAS (Laufwerksschächte)',
        'router' => 'Router',
        'ups' => 'USV (Display)',
        'patchpanel' => 'Patchfeld',
        'cablering' => 'Rangierfeld (Kabelbügel)',
        'brush' => 'Kabeldurchführung (Bürste)',
        'shelf' => 'Fachboden',
        'pdu' => 'Steckdosenleiste',
        'blank' => 'Blindplatte',
    ],

    /*
     * Passive Rack-Elemente ohne eigene Dokumentation: Schluessel => [Label, Hoeheneinheiten].
     */
];
