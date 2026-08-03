<?php

use App\Http\Requests\AccesspointRequest;
use App\Http\Requests\ADDomainRequest;
use App\Http\Requests\BackupRequest;
use App\Http\Requests\ComputerRequest;
use App\Http\Requests\ContactPersonRequest;
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
use App\Models\Computer;
use App\Models\ContactPerson;
use App\Models\DECT;
use App\Models\Domain;
use App\Models\DynDNS;
use App\Models\FTPServer;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\LicenseAccess;
use App\Models\LicenseSoftware;
use App\Models\LicenseWindows;
use App\Models\LoginGeneral;
use App\Models\LoginNAS;
use App\Models\LoginRecorder;
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
use App\Models\SecurepointUTM;
use App\Models\Server;
use App\Models\Site;
use App\Models\Ups;
use App\Models\VM;
use App\Models\Wifi;

return [
    'permissions' => [
        'Site',
        'ContactPerson',
        'Server',
        'VM',
        'NAS',
        'SecurepointUTM',
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
        'LoginNAS',
        'LoginWebsite',
        'LoginRecorder',
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
    ],

    /*
     * Whitelist für den Papierkorb: URL-Slug => [Model-Klasse, Anzeigename].
     * Nur Models mit customer_id und SoftDeletes aufnehmen.
     */
    'trashables' => [
        'site' => [Site::class, 'Standort'],
        'contactperson' => [ContactPerson::class, 'Ansprechpartner'],
        'server' => [Server::class, 'Server'],
        'vm' => [VM::class, 'VM'],
        'nas' => [NAS::class, 'NAS'],
        'securepointutm' => [SecurepointUTM::class, 'Securepoint UTM'],
        'securepointuma' => [SecurepointUMA::class, 'E-Mail-Archivierung'],
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
        'loginnas' => [LoginNAS::class, 'Login NAS'],
        'loginwebsite' => [LoginWebsite::class, 'Login Webseite'],
        'loginrecorder' => [LoginRecorder::class, 'Login Recorder'],
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
        'loginnas' => 'Logins NAS',
        'loginwebsite' => 'Logins Webseiten',
        'loginrecorder' => 'Logins Recorder',
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
        'accesspoint' => 'Accesspoints',
        'contactperson' => 'Ansprechpartner',
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
                ['name' => 'ip', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'number', 'default' => '443'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
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
                ['name' => 'subnetmask', 'label' => 'Subnetzmaske', 'type' => 'text', 'default' => '255.255.255.0'],
                ['name' => 'cidr', 'label' => 'CIDR', 'type' => 'number', 'default' => '24'],
                ['name' => 'gateway', 'label' => 'Gateway', 'type' => 'text'],
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
                ['name' => 'ip', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
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
                ['name' => 'ip', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
                ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
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
                ['name' => 'ip1', 'label' => 'IP-Adresse', 'type' => 'text'],
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
                ['name' => 'ip1', 'label' => 'IP-Adresse', 'type' => 'text'],
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
                ['name' => 'ip1', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
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
                ['name' => 'ip', 'label' => 'IP-Adresse', 'type' => 'text'],
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
                ['name' => 'ip', 'label' => 'IP-Adresse', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Passwort', 'type' => 'password'],
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
                ['name' => 'ip1', 'label' => 'IP-Adresse', 'type' => 'text'],
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
    'rack_device_types' => [
        'server' => [Server::class, 'Server', 'server'],
        'networkswitch' => [NetworkSwitch::class, 'Switch', 'switch'],
        'patchpanel' => [PatchPanel::class, 'Patchfeld', 'patchpanel'],
        'nas' => [NAS::class, 'NAS', 'nas'],
        'router' => [Router::class, 'Router', 'router'],
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
