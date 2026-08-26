<?php

use App\Http\Requests\AccesspointRequest;
use App\Http\Requests\ADDomainRequest;
use App\Http\Requests\ADGroupRequest;
use App\Http\Requests\ADUserRequest;
use App\Http\Requests\BackupRequest;
use App\Http\Requests\CameraRequest;
use App\Http\Requests\CertificateRequest;
use App\Http\Requests\ClusterRequest;
use App\Http\Requests\ComputerRequest;
use App\Http\Requests\ContactPersonRequest;
use App\Http\Requests\DECTRequest;
use App\Http\Requests\DomainRequest;
use App\Http\Requests\DynDNSRequest;
use App\Http\Requests\FirewallRequest;
use App\Http\Requests\FTPServerRequest;
use App\Http\Requests\InternetConnectionRequest;
use App\Http\Requests\IoTDeviceRequest;
use App\Http\Requests\LicenseAccessRequest;
use App\Http\Requests\LicenseSoftwareRequest;
use App\Http\Requests\LicenseWindowsRequest;
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
use App\Http\Requests\SecurepointUMARequest;
use App\Http\Requests\ServerRequest;
use App\Http\Requests\SiteRequest;
use App\Http\Requests\SshKeyRequest;
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
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
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
        'relation' => 'ftpservers', 'einzahl' => 'FTP-Server', 'suchfelder' => ['host', 'description'],
        // Die Zugaenge stehen im Bearbeiten-Modal als eigener Block darunter:
        // derselbe Mechanismus wie bei Server, VM oder NAS, damit ein Konto,
        // das auf zwei Servern gilt, nur einmal dokumentiert wird. IP-Adressen
        // fuehrt ein FTP-Server nicht - der Block bleibt deshalb weg.
        'bloecke' => true,
        // Zwei Felder, darunter die Zugangsdaten - hier ist der Block der
        // Inhalt des Fensters und nicht ein Anhang darunter.
        'breit' => true,
        'felder' => [
            ['name' => 'host', 'label' => 'Host', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
        ],
    ],
    'licenseaccess' => [
        'model' => LicenseAccess::class, 'request' => LicenseAccessRequest::class,
        'relation' => 'licenseaccesses', 'einzahl' => 'CAL-Lizenz',
        'suchfelder' => ['name', 'key'],
        // Kein Filter: Eine CAL-Lizenz hat weder Laufzeit noch Auswahlfeld -
        // da bliebe nur eine leere Leiste.
        'sortierungen' => [
            'neueste' => ['Neueste zuerst', 'created_at', 'desc'],
            'name' => ['Name', 'name', 'asc'],
        ],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'key', 'label' => 'Key', 'type' => 'text'],
            ['name' => 'file_name', 'label' => 'Bezeichnung der Datei', 'type' => 'text'],
            ['name' => 'file_path', 'label' => 'Dateipfad', 'type' => 'versteckt'],
            ['name' => 'datei', 'label' => 'Datei', 'type' => 'datei',
                'pfad_feld' => 'file_path', 'name_feld' => 'file_name', 'ordner' => 'licenseaccess'],
        ],
    ],
    'licensesoftware' => [
        'model' => LicenseSoftware::class, 'request' => LicenseSoftwareRequest::class,
        'relation' => 'licensesoftware', 'einzahl' => 'Software-Lizenz',
        'suchfelder' => ['name', 'key', 'username'],
        'spalten' => 2,
        'filter' => [
            // "offen" schliesst Lizenzen ohne Enddatum ein: Eine Dauerlizenz
            // laeuft nicht ab und gehoert zu den unproblematischen.
            ['name' => 'ablauf', 'label' => 'Laufzeit', 'typ' => 'ablauf', 'feld' => 'end_date',
                'alle' => 'Alle', 'optionen' => [
                    'abgelaufen' => 'Abgelaufen',
                    '30' => 'Läuft in 30 Tagen ab',
                    '90' => 'Läuft in 90 Tagen ab',
                    'offen' => 'Läuft noch',
                ]],
            ['name' => 'abo', 'label' => 'Abonnement', 'typ' => 'werte', 'feld' => 'abo',
                'alle' => 'Alle', 'optionen' => ['Jährlich' => 'Jährlich', 'Monatlich' => 'Monatlich']],
        ],
        'sortierungen' => [
            'neueste' => ['Neueste zuerst', 'created_at', 'desc'],
            'ablauf' => ['Ablauf zuerst', 'end_date', 'asc'],
            'name' => ['Name', 'name', 'asc'],
        ],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'breit' => true],
            ['name' => 'key', 'label' => 'Key', 'type' => 'text', 'breit' => true],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'start_date', 'label' => 'Start Datum', 'type' => 'date'],
            ['name' => 'end_date', 'label' => 'End Datum', 'type' => 'date'],
            // "Kein Abo" ist der leere Wert - er wird beim Speichern zu null,
            // wie im bisherigen Formular.
            ['name' => 'abo', 'label' => 'Abonnement', 'type' => 'optionen',
                'werte' => ['' => 'Kein Abo', 'Jährlich' => 'Jährlich', 'Monatlich' => 'Monatlich']],
            ['name' => 'file_name', 'label' => 'Bezeichnung der Datei', 'type' => 'text'],
            ['name' => 'file_path', 'label' => 'Dateipfad', 'type' => 'versteckt'],
            ['name' => 'datei', 'label' => 'Datei', 'type' => 'datei', 'breit' => true,
                'pfad_feld' => 'file_path', 'name_feld' => 'file_name', 'ordner' => 'licensesoftware'],
        ],
    ],
    'licensewindows' => [
        'model' => LicenseWindows::class, 'request' => LicenseWindowsRequest::class,
        'relation' => 'licensewindows', 'einzahl' => 'Windows-Lizenz',
        'suchfelder' => ['key'],
        'filter' => [
            // Die Auswahl kommt aus dem Bestand, nicht aus dem ganzen Katalog:
            // Ein Betriebssystem, zu dem es keine Lizenz gibt, waere eine
            // Zeile, die immer nichts findet.
            ['name' => 'os', 'label' => 'Betriebssystem', 'typ' => 'beziehung',
                'feld' => 'operating_system_id', 'quelle' => OperatingSystem::class,
                'anzeige' => 'name', 'alle' => 'Alle', 'optionen' => []],
        ],
        'sortierungen' => [
            'neueste' => ['Neueste zuerst', 'created_at', 'desc'],
        ],
        'felder' => [
            // Nur Windows-Systeme zur Auswahl: Eine Windows-Lizenz fuer
            // Debian oder Proxmox gibt es nicht, und der Katalog fuehrt
            // beides. Der Praefix reicht - alle Windows-Eintraege beginnen
            // damit (siehe OperatingSystemsSeeder).
            ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name',
                'einschraenkung' => [['name', 'like', 'Windows%']]],
            ['name' => 'key', 'label' => 'Key', 'type' => 'text'],
            ['name' => 'file_name', 'label' => 'Bezeichnung der Datei', 'type' => 'text'],
            // Der Pfad entsteht beim Hochladen und wird nicht eingetippt.
            ['name' => 'file_path', 'label' => 'Dateipfad', 'type' => 'versteckt'],
            ['name' => 'datei', 'label' => 'Datei', 'type' => 'datei',
                'pfad_feld' => 'file_path', 'name_feld' => 'file_name', 'ordner' => 'licensewindows'],
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
    'sshkey' => [
        'model' => SshKey::class, 'request' => SshKeyRequest::class,
        'relation' => 'sshkeys', 'einzahl' => 'SSH-Schlüssel',
        'suchfelder' => ['name', 'username', 'description', 'public_key'],
        'mitladen' => ['links.credentialable'],
        // Zwei Spalten waeren hier falsch: Die beiden Schluesselfelder sind
        // mehrzeilig und brauchen die ganze Breite.
        'breit' => true,
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'key_type', 'label' => 'Verfahren', 'type' => 'optionen',
                'quelle' => 'custom.ssh_key_types'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
            ['name' => 'public_key', 'label' => 'Öffentlicher Schlüssel', 'type' => 'mehrzeilig',
                'zeilen' => 3, 'platzhalter' => 'ssh-ed25519 AAAA… benutzer@rechner'],
            ['name' => 'private_key', 'label' => 'Privater Schlüssel', 'type' => 'mehrzeilig',
                'zeilen' => 6, 'platzhalter' => '-----BEGIN OPENSSH PRIVATE KEY-----'],
            ['name' => 'password', 'label' => 'Passphrase', 'type' => 'text'],
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
        'relation' => 'contactpersons', 'einzahl' => 'Ansprechpartner', 'suchfelder' => ['first_name', 'last_name', 'mail'],
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
    'securepointuma' => [
        'model' => SecurepointUMA::class, 'request' => SecurepointUMARequest::class,
        'relation' => 'securepointumas', 'einzahl' => 'E-Mail-Archivierung',
        'suchfelder' => ['name', 'manufacturer'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller / Produkt', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Typ', 'type' => 'text'],
            ['name' => 'urlAdmin', 'label' => 'Admin URL', 'type' => 'text'],
            ['name' => 'urlUser', 'label' => 'User URL', 'type' => 'text'],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            // Ein Verschluesselungscode ist lang und wird am Stueck kopiert -
            // er braucht die volle Breite.
            ['name' => 'encryptionkey', 'label' => 'Verschlüsselungscode', 'type' => 'text', 'breit' => true],
        ],
    ],
    'site' => [
        'model' => Site::class, 'request' => SiteRequest::class,
        'relation' => 'sites', 'einzahl' => 'Standort',
        'suchfelder' => ['name', 'city', 'street'],
        'spalten' => 2,
        // Der Standort steht auch im Umschalter der Seitenleiste und in der
        // Auswahl jedes Geraeteformulars - nach dem Speichern neu laden.
        'seite_neu_laden' => true,
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'breit' => true],
            ['name' => 'street', 'label' => 'Straße', 'type' => 'text'],
            ['name' => 'house_number', 'label' => 'Hausnummer', 'type' => 'text'],
            ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text'],
            ['name' => 'city', 'label' => 'Stadt', 'type' => 'text'],
        ],
    ],
    'cluster' => [
        'model' => Cluster::class, 'request' => ClusterRequest::class,
        'relation' => 'clusters', 'einzahl' => 'Cluster', 'suchfelder' => ['name', 'note'],
        // Die Knoten fuer die Karte vorladen, sonst eine Abfrage je Zeile.
        'mitladen' => ['servers.operatingSystem'],
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Art', 'type' => 'optionen',
                'quelle' => 'custom.cluster_types'],
            ['name' => 'note', 'label' => 'Notiz', 'type' => 'text'],
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
            // Vorbelegt wie im Seitenformular: Die meisten Server sind 1 HE
            // hoch, und required_if verlangt den Wert beim Rackeinbau.
            ['name' => 'height_units', 'label' => 'Höheneinheiten (HE)', 'type' => 'number',
                'default' => 1, 'sichtbar_wenn' => ['form_factor' => 'rack']],
            ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name'],
            // 'auswahl' filtert selbst nach customer_id - ein fremder Cluster
            // steht damit gar nicht erst zur Wahl.
            ['name' => 'cluster_id', 'label' => 'Cluster', 'type' => 'auswahl',
                'quelle' => Cluster::class, 'anzeige' => 'name'],
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
        // Host und Cluster stehen auf der Karte - ohne Vorladen fragt die
        // Liste sie je Zeile einzeln nach.
        'mitladen' => ['host', 'cluster'],
        'felder' => [
            // Host und Cluster zuerst: Beide beantworten den Standort gleich
            // mit, und was sie beantworten, soll man nicht davor schon tippen.
            //
            // Entweder oder: In einem HA-Cluster wandert die VM zwischen den
            // Knoten - ein fester Host waere dort falsch. Jedes Feld
            // verschwindet deshalb, sobald das andere steht.
            ['name' => 'server_id', 'label' => 'Host', 'type' => 'auswahl',
                'quelle' => Server::class, 'anzeige' => 'name',
                'sichtbar_wenn' => ['cluster_id' => ['leer' => true]]],
            ['name' => 'cluster_id', 'label' => 'Cluster', 'type' => 'auswahl',
                'quelle' => Cluster::class, 'anzeige' => 'name',
                'sichtbar_wenn' => ['server_id' => ['leer' => true]]],
            // Nur ohne beides: Sonst kommt der Standort von dort (VM::booted) -
            // zweimal gepflegt koennten sie auseinanderlaufen. Gebraucht wird
            // er trotzdem, etwa fuer einen vServer beim Anbieter, dessen Host
            // nie dokumentiert wird.
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort',
                'sichtbar_wenn' => ['server_id' => ['leer' => true], 'cluster_id' => ['leer' => true]]],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
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
