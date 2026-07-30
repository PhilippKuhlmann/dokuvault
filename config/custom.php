<?php

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
        'Backup'
    ],

    /*
     * Whitelist für den Papierkorb: URL-Slug => [Model-Klasse, Anzeigename].
     * Nur Models mit customer_id und SoftDeletes aufnehmen.
     */
    'trashables' => [
        'site' => [\App\Models\Site::class, 'Standort'],
        'contactperson' => [\App\Models\ContactPerson::class, 'Ansprechpartner'],
        'server' => [\App\Models\Server::class, 'Server'],
        'vm' => [\App\Models\VM::class, 'VM'],
        'nas' => [\App\Models\NAS::class, 'NAS'],
        'securepointutm' => [\App\Models\SecurepointUTM::class, 'Securepoint UTM'],
        'securepointuma' => [\App\Models\SecurepointUMA::class, 'E-Mail-Archivierung'],
        'router' => [\App\Models\Router::class, 'Router'],
        'network' => [\App\Models\Network::class, 'Netzwerk'],
        'networkswitch' => [\App\Models\NetworkSwitch::class, 'Switch'],
        'wifi' => [\App\Models\Wifi::class, 'WLAN'],
        'accesspoint' => [\App\Models\Accesspoint::class, 'Accesspoint'],
        'computer' => [\App\Models\Computer::class, 'Computer'],
        'iotdevice' => [\App\Models\IoTDevice::class, 'IoT-Gerät'],
        'machine' => [\App\Models\Machine::class, 'Maschine'],
        'otherclient' => [\App\Models\OtherClient::class, 'Sonstiger Client'],
        'printer' => [\App\Models\Printer::class, 'Drucker'],
        'addomain' => [\App\Models\ADDomain::class, 'AD-Domäne'],
        'aduser' => [\App\Models\ADUser::class, 'AD-Benutzer'],
        'adgroup' => [\App\Models\ADGroup::class, 'AD-Gruppe'],
        'phonesystem' => [\App\Models\PhoneSystem::class, 'TK-Anlage'],
        'phone' => [\App\Models\Phone::class, 'Telefon'],
        'dect' => [\App\Models\DECT::class, 'DECT'],
        'logingeneral' => [\App\Models\LoginGeneral::class, 'Login Allgemein'],
        'loginnas' => [\App\Models\LoginNAS::class, 'Login NAS'],
        'loginwebsite' => [\App\Models\LoginWebsite::class, 'Login Webseite'],
        'loginrecorder' => [\App\Models\LoginRecorder::class, 'Login Recorder'],
        'mailbox' => [\App\Models\Mailbox::class, 'Postfach'],
        'recorder' => [\App\Models\Recorder::class, 'Recorder'],
        'camera' => [\App\Models\Camera::class, 'Kamera'],
        'licensesoftware' => [\App\Models\LicenseSoftware::class, 'Software-Lizenz'],
        'licensewindows' => [\App\Models\LicenseWindows::class, 'Windows-Lizenz'],
        'licenseaccess' => [\App\Models\LicenseAccess::class, 'CAL-Lizenz'],
        'ftpserver' => [\App\Models\FTPServer::class, 'FTP-Server'],
        'dyndns' => [\App\Models\DynDNS::class, 'DynDNS'],
        'ups' => [\App\Models\Ups::class, 'USV'],
        'internetconnection' => [\App\Models\InternetConnection::class, 'Internet-Anschluss'],
        'domain' => [\App\Models\Domain::class, 'Domain'],
        'certificate' => [\App\Models\Certificate::class, 'Zertifikat'],
        'backup' => [\App\Models\Backup::class, 'Backup'],
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
            'model' => \App\Models\Site::class, 'relation' => 'sites',
            'request' => \App\Http\Requests\SiteRequest::class, 'permission' => 'site_create',
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
            'model' => \App\Models\ContactPerson::class, 'relation' => 'contactpersons',
            'request' => \App\Http\Requests\ContactPersonRequest::class, 'permission' => 'contactperson_create',
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
            'model' => \App\Models\InternetConnection::class, 'relation' => 'internetconnections',
            'request' => \App\Http\Requests\InternetConnectionRequest::class, 'permission' => 'internetconnection_create',
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
            'model' => \App\Models\Router::class, 'relation' => 'routers',
            'request' => \App\Http\Requests\RouterRequest::class, 'permission' => 'router_create',
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
            'model' => \App\Models\Network::class, 'relation' => 'networks',
            'request' => \App\Http\Requests\NetworkRequest::class, 'permission' => 'network_create',
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
            'model' => \App\Models\Wifi::class, 'relation' => 'wifis',
            'request' => \App\Http\Requests\WifiRequest::class, 'permission' => 'wifi_create',
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
            'model' => \App\Models\NetworkSwitch::class, 'relation' => 'networkswitches',
            'request' => \App\Http\Requests\NetworkSwitchRequest::class, 'permission' => 'networkswitch_create',
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
            'model' => \App\Models\Accesspoint::class, 'relation' => 'accesspoints',
            'request' => \App\Http\Requests\AccesspointRequest::class, 'permission' => 'accesspoint_create',
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
            'model' => \App\Models\Server::class, 'relation' => 'servers',
            'request' => \App\Http\Requests\ServerRequest::class, 'permission' => 'server_create',
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
            'model' => \App\Models\VM::class, 'relation' => 'vms',
            'request' => \App\Http\Requests\VMRequest::class, 'permission' => 'vm_create',
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
            'model' => \App\Models\NAS::class, 'relation' => 'nas',
            'request' => \App\Http\Requests\NASRequest::class, 'permission' => 'nas_create',
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
            'model' => \App\Models\Computer::class, 'relation' => 'computers',
            'request' => \App\Http\Requests\ComputerRequest::class, 'permission' => 'computer_create',
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
            'model' => \App\Models\Printer::class, 'relation' => 'printers',
            'request' => \App\Http\Requests\PrinterRequest::class, 'permission' => 'printer_create',
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
            'model' => \App\Models\ADDomain::class, 'relation' => 'addomains',
            'request' => \App\Http\Requests\ADDomainRequest::class, 'permission' => 'addomain_create',
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
            'model' => \App\Models\PhoneSystem::class, 'relation' => 'phonesystems',
            'request' => \App\Http\Requests\PhoneSystemRequest::class, 'permission' => 'phonesystem_create',
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
            'model' => \App\Models\Backup::class, 'relation' => 'backups',
            'request' => \App\Http\Requests\BackupRequest::class, 'permission' => 'backup_create',
            'scope' => 'customer', 'label_field' => 'name',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'software', 'label' => 'Software', 'type' => 'text'],
                ['name' => 'destination', 'label' => 'Ziel', 'type' => 'text'],
                ['name' => 'schedule', 'label' => 'Zeitplan', 'type' => 'text'],
            ],
        ],
    ],
];
