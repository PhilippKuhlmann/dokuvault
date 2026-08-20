<?php

use App\Http\Requests\AccesspointRequest;
use App\Http\Requests\ADDomainRequest;
use App\Http\Requests\ADGroupRequest;
use App\Http\Requests\ADUserRequest;
use App\Http\Requests\BackupRequest;
use App\Http\Requests\CameraRequest;
use App\Http\Requests\CertificateRequest;
use App\Http\Requests\ComputerRequest;
use App\Http\Requests\ContactPersonRequest;
use App\Http\Requests\DECTRequest;
use App\Http\Requests\DomainRequest;
use App\Http\Requests\DynDNSRequest;
use App\Http\Requests\FirewallRequest;
use App\Http\Requests\FTPServerRequest;
use App\Http\Requests\InternetConnectionRequest;
use App\Http\Requests\IoTDeviceRequest;
use App\Http\Requests\LoginGeneralRequest;
use App\Http\Requests\LoginWebsiteRequest;
use App\Http\Requests\MachineRequest;
use App\Http\Requests\MailboxRequest;
use App\Http\Requests\NASRequest;
use App\Http\Requests\NetworkSwitchRequest;
use App\Http\Requests\OtherClientRequest;
use App\Http\Requests\PhoneRequest;
use App\Http\Requests\PhoneSystemRequest;
use App\Http\Requests\PrinterRequest;
use App\Http\Requests\RecorderRequest;
use App\Http\Requests\RouterRequest;
use App\Http\Requests\ServerRequest;
use App\Http\Requests\UpsRequest;
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
use App\Models\Firewall;
use App\Models\FTPServer;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\LoginGeneral;
use App\Models\LoginWebsite;
use App\Models\Machine;
use App\Models\Mailbox;
use App\Models\MailboxProvider;
use App\Models\NAS;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\OtherClient;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Recorder;
use App\Models\Router;
use App\Models\Server;
use App\Models\Ups;
use App\Models\VM;
use App\Models\Wifi;

/*
|--------------------------------------------------------------------------
| Formulare im Modal
|--------------------------------------------------------------------------
|
| Anlegen und Bearbeiten laufen fuer diese Typen ueber ein Modal statt ueber
| eigene Seiten - dasselbe Muster wie beim VLAN. Statt vierzig Livewire-
| Komponenten gibt es eine (App\Livewire\ObjektFormular), die sich von hier
| beschreiben laesst.
|
| Die Feldlisten wurden aus den bestehenden Formularen ausgelesen und gegen
| die rules() des jeweiligen Requests geprueft: Nur Typen, bei denen beide
| deckungsgleich sind, stehen hier. Wo Datei-Uploads, Auswahllisten oder
| Schalter im Spiel sind (Lizenzen, WLAN, Postfach, AD-Benutzer, Schrank),
| bleiben die Seiten vorerst - ein stiller Feldverlust waere teurer als der
| gesparte Klick. Ein Test haelt diese Deckungsgleichheit fest.
|
| 'relation' ist die Methode am Customer, ueber die angelegt wird.
|
*/
return [
    'domain' => [
        'model' => Domain::class, 'request' => DomainRequest::class,
        'relation' => 'domains', 'einzahl' => 'Domain', 'suchfelder' => ['name', 'registrar'],
        'felder' => [
            ['name' => 'name', 'label' => 'Domain', 'type' => 'text'],
            ['name' => 'registrar', 'label' => 'Registrar', 'type' => 'text'],
            ['name' => 'expiry_date', 'label' => 'Ablaufdatum', 'type' => 'date'],
            ['name' => 'nameserver1', 'label' => 'Nameserver 1', 'type' => 'text'],
            ['name' => 'nameserver2', 'label' => 'Nameserver 2', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
        ],
    ],
    'certificate' => [
        'model' => Certificate::class, 'request' => CertificateRequest::class,
        'relation' => 'certificates', 'einzahl' => 'Zertifikat', 'suchfelder' => ['name', 'common_name', 'issuer'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'common_name', 'label' => 'Common Name', 'type' => 'text'],
            ['name' => 'issuer', 'label' => 'Aussteller', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Art', 'type' => 'text'],
            ['name' => 'issued_date', 'label' => 'Ausgestellt am', 'type' => 'date'],
            ['name' => 'expiry_date', 'label' => 'Gültig bis', 'type' => 'date'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
        ],
    ],
    'aduser' => [
        'model' => ADUser::class, 'request' => ADUserRequest::class,
        'relation' => 'adusers', 'einzahl' => 'AD-Benutzer',
        'suchfelder' => ['username', 'firstName', 'lastName', 'email'],
        'felder' => [
            ['name' => 'firstName', 'label' => 'Vorname', 'type' => 'text'],
            ['name' => 'lastName', 'label' => 'Nachname', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            // Zwei Werte brauchen keinen eigenen Konfigurationseintrag.
            ['name' => 'enabled', 'label' => 'Status', 'type' => 'optionen',
                'werte' => [1 => 'Aktiv', 0 => 'Deaktiviert']],
            // Technisch, nicht fuer die Anzeige: Der Wert wird unveraendert
            // durchgereicht, wie im bisherigen Formular.
            ['name' => 'hidden', 'label' => 'Verborgen', 'type' => 'versteckt'],
        ],
    ],
    'adgroup' => [
        'model' => ADGroup::class, 'request' => ADGroupRequest::class,
        'relation' => 'adgroups', 'einzahl' => 'AD-Gruppe', 'suchfelder' => ['name', 'description'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
        ],
    ],
    'backup' => [
        'model' => Backup::class, 'request' => BackupRequest::class,
        'relation' => 'backups', 'einzahl' => 'Backup', 'suchfelder' => ['name', 'software'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'software', 'label' => 'Software', 'type' => 'text'],
            ['name' => 'schedule', 'label' => 'Zeitplan', 'type' => 'text'],
            ['name' => 'source', 'label' => 'Quelle', 'type' => 'text'],
            ['name' => 'destination', 'label' => 'Ziel', 'type' => 'text'],
            ['name' => 'retention', 'label' => 'Aufbewahrung', 'type' => 'text'],
            ['name' => 'last_success', 'label' => 'Letzter Erfolg', 'type' => 'date'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
        ],
    ],
    'firewall' => [
        'model' => Firewall::class, 'request' => FirewallRequest::class,
        'relation' => 'firewalls', 'einzahl' => 'Firewall', 'suchfelder' => ['name', 'serialNumber'],
        'bloecke' => true,
        'spalten' => 2,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'firmware', 'label' => 'Firmware', 'type' => 'text'],
            ['name' => 'form_factor', 'label' => 'Bauform', 'type' => 'optionen',
                'quelle' => 'custom.firewall_form_factors'],
            ['name' => 'management_url', 'label' => 'Verwaltungsoberfläche', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'number'],
            ['name' => 'subscription_until', 'label' => 'Subscription bis', 'type' => 'date'],
            // Nur bei Securepoint. Der Hersteller ist Freitext, deshalb wird
            // verglichen und nicht auf Gleichheit geprueft.
            ['name' => 'url_user', 'label' => 'Benutzerportal', 'type' => 'text',
                'sichtbar_wenn' => ['manufacturer' => ['enthaelt' => 'securepoint']]],
            ['name' => 'url_external', 'label' => 'Externer Zugang', 'type' => 'text',
                'sichtbar_wenn' => ['manufacturer' => ['enthaelt' => 'securepoint']]],
            ['name' => 'usc_pin', 'label' => 'USC-PIN', 'type' => 'text',
                'sichtbar_wenn' => ['manufacturer' => ['enthaelt' => 'securepoint']]],
            ['name' => 'cloud_backup_password', 'label' => 'Cloud-Backup-Kennwort', 'type' => 'text',
                'sichtbar_wenn' => ['manufacturer' => ['enthaelt' => 'securepoint']]],
            ['name' => 'height_units', 'label' => 'Höheneinheiten (HE)', 'type' => 'number',
                'sichtbar_wenn' => ['form_factor' => 'appliance']],
            ['name' => 'full_depth', 'label' => 'Volle Tiefe', 'type' => 'schalter',
                'sichtbar_wenn' => ['form_factor' => 'appliance']],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text', 'breit' => true],
        ],
    ],
    'ftpserver' => [
        'model' => FTPServer::class, 'request' => FTPServerRequest::class,
        'relation' => 'ftpservers', 'einzahl' => 'FTP-Server', 'suchfelder' => ['host', 'username'],
        'felder' => [
            ['name' => 'host', 'label' => 'Host', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
        ],
    ],
    'loginwebsite' => [
        'model' => LoginWebsite::class, 'request' => LoginWebsiteRequest::class,
        'relation' => 'loginwebsites', 'einzahl' => 'Webseiten-Login', 'suchfelder' => ['name', 'url', 'username'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'url', 'label' => 'URL', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
        ],
    ],
    'internetconnection' => [
        'model' => InternetConnection::class, 'request' => InternetConnectionRequest::class,
        'relation' => 'internetconnections', 'einzahl' => 'Internetanschluss',
        'suchfelder' => ['provider', 'product', 'contract_number'],
        'spalten' => 2,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'provider', 'label' => 'Anbieter', 'type' => 'text'],
            ['name' => 'product', 'label' => 'Produkt', 'type' => 'text'],
            ['name' => 'contract_number', 'label' => 'Vertragsnummer', 'type' => 'text'],
            ['name' => 'connection_type', 'label' => 'Anschlussart', 'type' => 'text'],
            ['name' => 'hotline', 'label' => 'Hotline', 'type' => 'text'],
            // Zahl mit fester Einheit: Man tippt 250, im Feld steht "250 | Mbit/s".
            ['name' => 'bandwidth_down', 'label' => 'Download', 'type' => 'einheit', 'einheit' => 'Mbit/s'],
            ['name' => 'bandwidth_up', 'label' => 'Upload', 'type' => 'einheit', 'einheit' => 'Mbit/s'],
            ['name' => 'wan_ip', 'label' => 'WAN-IP', 'type' => 'text'],
            ['name' => 'pppoe_user', 'label' => 'Einwahl-Benutzer', 'type' => 'text'],
            ['name' => 'pppoe_password', 'label' => 'Einwahl-Passwort', 'type' => 'text'],
            ['name' => 'subnet', 'label' => 'Geroutetes Netz', 'type' => 'text'],
            ['name' => 'subnet_gateway', 'label' => 'Gateway des Netzes', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text', 'breit' => true],
        ],
    ],
    'logingeneral' => [
        'model' => LoginGeneral::class, 'request' => LoginGeneralRequest::class,
        'relation' => 'logingenerals', 'einzahl' => 'Login', 'suchfelder' => ['name', 'username', 'description'],
        'mitladen' => ['links.credentialable'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
        ],
    ],
    'dyndns' => [
        'model' => DynDNS::class, 'request' => DynDNSRequest::class,
        'relation' => 'dyndns', 'einzahl' => 'DynDNS', 'suchfelder' => ['domain', 'host', 'providor'],
        'felder' => [
            ['name' => 'providor', 'label' => 'Anbieter', 'type' => 'text'],
            ['name' => 'domain', 'label' => 'Domain', 'type' => 'text'],
            ['name' => 'host', 'label' => 'Host', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
        ],
    ],
    'contactperson' => [
        'model' => ContactPerson::class, 'request' => ContactPersonRequest::class,
        'relation' => 'contactpeople', 'einzahl' => 'Ansprechpartner', 'suchfelder' => ['first_name', 'last_name', 'mail'],
        'felder' => [
            ['name' => 'first_name', 'label' => 'Vorname', 'type' => 'text'],
            ['name' => 'last_name', 'label' => 'Nachname', 'type' => 'text'],
            ['name' => 'role', 'label' => 'Funktion', 'type' => 'text'],
            ['name' => 'phone', 'label' => 'Telefon', 'type' => 'text'],
            ['name' => 'mail', 'label' => 'E-Mail', 'type' => 'text'],
        ],
    ],
    'addomain' => [
        'model' => ADDomain::class, 'request' => ADDomainRequest::class,
        'relation' => 'addomains', 'einzahl' => 'AD-Domäne', 'suchfelder' => ['domain', 'netbios'],
        'felder' => [
            ['name' => 'domain', 'label' => 'Domäne', 'type' => 'text'],
            ['name' => 'netbios', 'label' => 'NetBIOS', 'type' => 'text'],
            ['name' => 'dsrmpassword', 'label' => 'DSRM Passwort', 'type' => 'text'],
        ],
    ],
    'wifi' => [
        'model' => Wifi::class, 'request' => WifiRequest::class,
        'relation' => 'wifis', 'einzahl' => 'WLAN', 'suchfelder' => ['ssid', 'encryption'],
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'ssid', 'label' => 'SSID', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'encryption', 'label' => 'Verschlüsselung', 'type' => 'text'],
            // Das Netz gehoert dem Kunden - die Auswahl wird darauf eingeschraenkt.
            ['name' => 'network_id', 'label' => 'Netzwerk', 'type' => 'auswahl',
                'quelle' => Network::class, 'anzeige' => 'VLAN {vlanId} · {description}'],
        ],
    ],
    'server' => [
        'model' => Server::class, 'request' => ServerRequest::class,
        'relation' => 'servers', 'einzahl' => 'Server', 'suchfelder' => ['name', 'serialNumber'],
        'bloecke' => true,
        // Zwanzig Felder untereinander waeren eine Scrollstrecke.
        'spalten' => 2,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Typ', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'form_factor', 'label' => 'Bauform', 'type' => 'optionen',
                'quelle' => 'custom.server_form_factors'],
            // Nur beim Rackeinbau: Ein Standserver hat keine Einbautiefe.
            ['name' => 'full_depth', 'label' => 'Einbautiefe', 'type' => 'optionen',
                'quelle' => 'custom.server_depths', 'sichtbar_wenn' => ['form_factor' => 'rack']],
            ['name' => 'height_units', 'label' => 'Höheneinheiten (HE)', 'type' => 'number',
                'sichtbar_wenn' => ['form_factor' => 'rack']],
            ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name'],
            ['name' => 'bmcIp', 'label' => 'BMC IP', 'type' => 'text'],
            ['name' => 'bmcUser', 'label' => 'BMC Benutzer', 'type' => 'text'],
            ['name' => 'bmcPassword', 'label' => 'BMC Passwort', 'type' => 'text'],
            ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text'],
            ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
            // Die Dienste brauchen die volle Breite - Katalog und Kacheln
            // passen nicht in eine halbe Spalte.
            ['name' => 'services', 'label' => 'Dienste', 'type' => 'dienste', 'breit' => true],
        ],
    ],
    'vm' => [
        'model' => VM::class, 'request' => VMRequest::class,
        'relation' => 'vms', 'einzahl' => 'VM', 'suchfelder' => ['name'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            // Der Host, auf dem die VM laeuft.
            ['name' => 'server_id', 'label' => 'Host', 'type' => 'auswahl',
                'quelle' => Server::class, 'anzeige' => 'name'],
            ['name' => 'services', 'label' => 'Dienste', 'type' => 'dienste'],
            ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name'],
            ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text'],
            ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text'],
        ],
    ],
    'mailbox' => [
        'model' => Mailbox::class, 'request' => MailboxRequest::class,
        'relation' => 'mailboxes', 'einzahl' => 'Postfach', 'suchfelder' => ['name', 'mailAdress', 'username'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'mailAdress', 'label' => 'E-Mail Adresse', 'type' => 'email'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            // Ein globaler Katalog, den ein Admin pflegt - keine customer_id.
            ['name' => 'mailbox_provider_id', 'label' => 'Anbieter', 'type' => 'auswahl',
                'quelle' => MailboxProvider::class, 'anzeige' => 'name'],
        ],
    ],
    'machine' => [
        'model' => Machine::class, 'request' => MachineRequest::class,
        'relation' => 'machines', 'einzahl' => 'Maschine', 'suchfelder' => ['name'],
        // Fuehrt IP-Adressen und Zugangsdaten - beide erscheinen beim Bearbeiten.
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
        ],
    ],
    'accesspoint' => [
        'model' => Accesspoint::class, 'request' => AccesspointRequest::class,
        'relation' => 'accesspoints', 'einzahl' => 'Accesspoint', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'camera' => [
        'model' => Camera::class, 'request' => CameraRequest::class,
        'relation' => 'cameras', 'einzahl' => 'Kamera', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'computer' => [
        'model' => Computer::class, 'request' => ComputerRequest::class,
        'relation' => 'computers', 'einzahl' => 'Computer', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'operating_system_id', 'label' => 'Operating System Id', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name'],
            ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text'],
            ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'dect' => [
        'model' => DECT::class, 'request' => DECTRequest::class,
        'relation' => 'dects', 'einzahl' => 'DECT', 'suchfelder' => ['model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'role', 'label' => 'Rolle', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennmmer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'mac', 'label' => 'MAC-Adresse', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'iotdevice' => [
        'model' => IoTDevice::class, 'request' => IoTDeviceRequest::class,
        'relation' => 'iotdevices', 'einzahl' => 'IoT-Gerät', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'url', 'label' => 'URL', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzer', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'nas' => [
        'model' => NAS::class, 'request' => NASRequest::class,
        'relation' => 'nas', 'einzahl' => 'NAS', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'networkswitch' => [
        'model' => NetworkSwitch::class, 'request' => NetworkSwitchRequest::class,
        'relation' => 'networkswitches', 'einzahl' => 'Switch', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'otherclient' => [
        'model' => OtherClient::class, 'request' => OtherClientRequest::class,
        'relation' => 'otherclients', 'einzahl' => 'Sonstiger Client', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzer', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'phone' => [
        'model' => Phone::class, 'request' => PhoneRequest::class,
        'relation' => 'phones', 'einzahl' => 'Telefon', 'suchfelder' => ['model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'extension', 'label' => 'Nebenstelle', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennmmer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'mac', 'label' => 'MAC-Adresse', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'phonesystem' => [
        'model' => PhoneSystem::class, 'request' => PhoneSystemRequest::class,
        'relation' => 'phonesystems', 'einzahl' => 'TK-Anlage', 'suchfelder' => ['model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Typ', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'printer' => [
        'model' => Printer::class, 'request' => PrinterRequest::class,
        'relation' => 'printers', 'einzahl' => 'Drucker', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'recorder' => [
        'model' => Recorder::class, 'request' => RecorderRequest::class,
        'relation' => 'recorders', 'einzahl' => 'Recorder', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'router' => [
        'model' => Router::class, 'request' => RouterRequest::class,
        'relation' => 'routers', 'einzahl' => 'Router', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'ups' => [
        'model' => Ups::class, 'request' => UpsRequest::class,
        'relation' => 'ups', 'einzahl' => 'USV', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'capacity', 'label' => 'Kapazität', 'type' => 'text'],
            ['name' => 'runtime', 'label' => 'Laufzeit', 'type' => 'text'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
];
