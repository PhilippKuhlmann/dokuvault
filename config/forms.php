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
use App\Http\Requests\NetworkShareRequest;
use App\Http\Requests\NetworkSwitchRequest;
use App\Http\Requests\OtherClientRequest;
use App\Http\Requests\PhoneRequest;
use App\Http\Requests\PhoneSystemRequest;
use App\Http\Requests\PrinterRequest;
use App\Http\Requests\RecorderRequest;
use App\Http\Requests\RouterRequest;
use App\Http\Requests\ScannerRequest;
use App\Http\Requests\ScanTargetRequest;
use App\Http\Requests\SecurepointUMARequest;
use App\Http\Requests\ServerRequest;
use App\Http\Requests\SipAccountRequest;
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
use App\Models\NetworkShare;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\OtherClient;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Recorder;
use App\Models\Router;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\SipAccount;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Ups;
use App\Models\VM;
use App\Models\Wifi;
use App\Support\SshKeyGenerator;

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
            // Nightly check (domains:check): expiry from RDAP, nameservers,
            // MX, SPF and DMARC from the DNS.
            ['name' => 'auto_check', 'label' => 'Automatisch prüfen', 'type' => 'schalter', 'default' => '1'],
            // DKIM selectors can't be listed over DNS - the check tries the
            // common ones, these are added on top.
            ['name' => 'dkim_selectors', 'label' => 'Eigene DKIM-Selektoren', 'type' => 'text', 'platzhalter' => 'z. B. mail2024, sendgrid'],
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
            // Nightly check (domains:check) reads the certificate the host
            // serves. Empty host means the common name - a wildcard needs one.
            ['name' => 'auto_check', 'label' => 'Automatisch prüfen', 'type' => 'schalter', 'default' => '1'],
            ['name' => 'check_host', 'label' => 'Prüfen an Host', 'type' => 'text', 'platzhalter' => 'www.example.com'],
            ['name' => 'check_port', 'label' => 'Port', 'type' => 'number', 'platzhalter' => '443'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'text'],
        ],
    ],
    'aduser' => [
        'model' => ADUser::class, 'request' => ADUserRequest::class,
        'relation' => 'adusers', 'einzahl' => 'AD-Benutzer',
        'mitladen' => ['groups'],
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
            // Filled by the AD agent, editable here and on the group.
            ['name' => 'groups', 'label' => 'Gruppen', 'type' => 'verknuepfung',
                'relation' => 'groups', 'quelle' => ADGroup::class, 'anzeige' => 'name'],
            // Technical, not for display: the value is passed through
            // unchanged, as in the previous form.
            ['name' => 'hidden', 'label' => 'Verborgen', 'type' => 'versteckt'],
        ],
    ],
    'adgroup' => [
        'model' => ADGroup::class, 'request' => ADGroupRequest::class,
        'relation' => 'adgroups', 'einzahl' => 'AD-Gruppe', 'suchfelder' => ['name', 'description'],
        'mitladen' => ['users'],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
            ['name' => 'users', 'label' => 'Mitglieder', 'type' => 'verknuepfung',
                'relation' => 'users', 'quelle' => ADUser::class, 'anzeige' => 'username'],
        ],
    ],
    'backup' => [
        'model' => Backup::class, 'request' => BackupRequest::class,
        'relation' => 'backups', 'einzahl' => 'Backup', 'suchfelder' => ['name', 'software'],
        // The last runs on the card (x-backup-verlauf), limited per backup.
        'mitladen' => ['recentRuns'],
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
            ['name' => 'port', 'label' => 'Port', 'type' => 'number'],
            ['name' => 'subscription_until', 'label' => 'Subscription bis', 'type' => 'date'],
            // Only on Securepoint. The vendor is free text, so it is compared
            // rather than tested for equality.
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
        // The accounts stand in the edit modal as a block of their own below:
        // the same mechanism as with server, VM or NAS, so an account valid on
        // two servers is documented only once. An FTP server carries no IP
        // addresses - that block is therefore left out.
        'bloecke' => true,
        // Two fields, the credentials below them - here the block is the
        // content of the window and not an appendix underneath.
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
        // No filter: a CAL licence has neither a term nor a select field -
        // only an empty bar would be left.
        'sortierungen' => [
            'neueste' => ['Neueste zuerst', 'created_at', 'desc'],
            'name' => ['Name', 'name', 'asc'],
        ],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'key', 'label' => 'Lizenzschlüssel', 'type' => 'text'],
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
            // "offen" includes licences without an end date: a perpetual
            // licence does not expire and counts among the unproblematic ones.
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
            ['name' => 'key', 'label' => 'Lizenzschlüssel', 'type' => 'text', 'breit' => true],
            ['name' => 'username', 'label' => 'Benutzername', 'type' => 'text'],
            ['name' => 'password', 'label' => 'Passwort', 'type' => 'text'],
            ['name' => 'start_date', 'label' => 'Start Datum', 'type' => 'date'],
            ['name' => 'end_date', 'label' => 'End Datum', 'type' => 'date'],
            // "Kein Abo" is the empty value - it becomes null on save, as in
            // the previous form.
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
            // The choices come from what is in use, not from the whole
            // catalog: an operating system without a licence would be a row
            // that never finds anything.
            ['name' => 'os', 'label' => 'Betriebssystem', 'typ' => 'beziehung',
                'feld' => 'operating_system_id', 'quelle' => OperatingSystem::class,
                'anzeige' => 'name', 'alle' => 'Alle', 'optionen' => []],
        ],
        'sortierungen' => [
            'neueste' => ['Neueste zuerst', 'created_at', 'desc'],
        ],
        'felder' => [
            // Windows systems only: there is no Windows licence for Debian or
            // Proxmox, and the catalog carries both. The prefix is enough -
            // every Windows entry begins with it (see
            // OperatingSystemsSeeder).
            ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name',
                'einschraenkung' => [['name', 'like', 'Windows%']]],
            ['name' => 'key', 'label' => 'Lizenzschlüssel', 'type' => 'text'],
            ['name' => 'file_name', 'label' => 'Bezeichnung der Datei', 'type' => 'text'],
            // The path comes into being on upload and is not typed in.
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
            // A number with a fixed unit: one types 250, the field reads "250 | Mbit/s".
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
        'suchfelder' => ['name', 'username', 'description', 'public_key', 'fingerprint'],
        'mitladen' => ['links.credentialable'],
        // The width comes from the multi-line fields themselves here, so no
        // 'breit' is needed.
        // Fills the two key fields through ssh-keygen.
        'erzeuger' => SshKeyGenerator::class,
        'erzeuger_label' => 'Schlüsselpaar erzeugen',
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
        'relation' => 'addomains', 'einzahl' => 'AD-Domäne',
        'suchfelder' => ['domain', 'netbios'],
        // Domain admin account via the credentials block, not a column of its own.
        'bloecke' => true,
        'spalten' => 2,
        // Two columns, so the order is in pairs per row: what belongs together
        // stands side by side. Entra Connect comes last in its group - its
        // server is hidden on "Nein" and would otherwise leave a gap mid-form.
        'felder' => [
            ['name' => 'domain', 'label' => 'Domäne', 'type' => 'text', 'gruppe' => 'Domäne'],
            ['name' => 'netbios', 'label' => 'NetBIOS', 'type' => 'text', 'gruppe' => 'Domäne'],
            ['name' => 'upn_suffixes', 'label' => 'UPN-Suffixe', 'type' => 'text', 'gruppe' => 'Domäne'],
            ['name' => 'functional_level', 'label' => 'Funktionsebene', 'type' => 'optionen',
                'quelle' => 'custom.ad_functional_levels', 'gruppe' => 'Domäne'],
            // Server or VM of the customer, stored in ad_domain_hosts under 'rolle'.
            ['name' => 'domain_controllers', 'label' => 'Domänencontroller', 'type' => 'host',
                'rolle' => 'dc', 'mehrfach' => true, 'gruppe' => 'Domänencontroller'],
            ['name' => 'fsmo_holder', 'label' => 'FSMO-Rollen / PDC', 'type' => 'text', 'gruppe' => 'Domänencontroller'],
            ['name' => 'dsrmpassword', 'label' => 'DSRM Passwort', 'type' => 'text', 'gruppe' => 'Domänencontroller'],
            ['name' => 'dns_forwarders', 'label' => 'DNS-Weiterleitungen', 'type' => 'text', 'gruppe' => 'Domänencontroller'],
            ['name' => 'dhcp_server', 'label' => 'DHCP-Server', 'type' => 'text', 'gruppe' => 'Dienste'],
            ['name' => 'certificate_authority', 'label' => 'Zertifizierungsstelle (AD CS)', 'type' => 'host',
                'rolle' => 'ca', 'gruppe' => 'Dienste'],
            ['name' => 'entra_connect', 'label' => 'Entra Connect (Azure AD Sync)', 'type' => 'optionen',
                'werte' => ['' => 'Unbekannt', 1 => 'Ja', 0 => 'Nein'], 'gruppe' => 'Dienste'],
            ['name' => 'entra_connect_server', 'label' => 'Entra-Connect-Server', 'type' => 'host',
                'rolle' => 'entra_connect', 'sichtbar_wenn' => ['entra_connect' => '1'], 'gruppe' => 'Dienste'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'mehrzeilig', 'zeilen' => 3, 'breit' => true,
                'gruppe' => 'Sonstiges'],
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
            // The network belongs to the customer - the select is limited to it.
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
            // An encryption key is long and gets copied in one piece - it
            // needs the full width.
            ['name' => 'encryptionkey', 'label' => 'Verschlüsselungscode', 'type' => 'text', 'breit' => true],
        ],
    ],
    'site' => [
        'model' => Site::class, 'request' => SiteRequest::class,
        'relation' => 'sites', 'einzahl' => 'Standort',
        'suchfelder' => ['name', 'city', 'street'],
        'spalten' => 2,
        // The site also stands in the sidebar switcher and in the select of
        // every device form - reload after saving.
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
        // Preload the nodes for the map, or one query per row.
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
        // The card sums the guests' memory - without preloading the list
        // asks for them row by row.
        'mitladen' => ['gaeste'],
        // Twenty fields one below another would be a scrolling distance.
        'spalten' => 2,
        // 'gruppe' divides the twenty fields into sections with headings of
        // their own - without it they stood as one unbroken area in the modal,
        // in which the remote-access account and the purchase date looked
        // alike.
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort', 'gruppe' => 'Grunddaten'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'gruppe' => 'Grunddaten'],
            ['name' => 'type', 'label' => 'Typ', 'type' => 'text', 'gruppe' => 'Grunddaten'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text', 'gruppe' => 'Grunddaten'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text', 'gruppe' => 'Grunddaten'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text', 'gruppe' => 'Grunddaten'],
            ['name' => 'form_factor', 'label' => 'Bauform', 'type' => 'optionen',
                'quelle' => 'custom.server_form_factors', 'gruppe' => 'Grunddaten'],
            // Only when rack-mounted: a tower server has no mounting depth.
            ['name' => 'full_depth', 'label' => 'Einbautiefe', 'type' => 'optionen',
                'quelle' => 'custom.server_depths', 'sichtbar_wenn' => ['form_factor' => 'rack'], 'gruppe' => 'Grunddaten'],
            // Prefilled as in the page form: most servers are 1 U tall, and
            // required_if demands the value when rack-mounted.
            ['name' => 'height_units', 'label' => 'Höheneinheiten (HE)', 'type' => 'number',
                'default' => 1, 'sichtbar_wenn' => ['form_factor' => 'rack'], 'gruppe' => 'Grunddaten'],
            ['name' => 'operating_system_id', 'label' => 'Betriebssystem', 'type' => 'auswahl',
                'quelle' => OperatingSystem::class, 'anzeige' => 'name', 'gruppe' => 'System'],
            // 'auswahl' filters by customer_id itself - a foreign cluster is
            // therefore not even on offer.
            ['name' => 'cluster_id', 'label' => 'Cluster', 'type' => 'auswahl',
                'quelle' => Cluster::class, 'anzeige' => 'name', 'gruppe' => 'System'],
            ['name' => 'bmcIp', 'label' => 'BMC IP', 'type' => 'text', 'gruppe' => 'Fernzugriff'],
            ['name' => 'bmcUser', 'label' => 'BMC Benutzer', 'type' => 'text', 'gruppe' => 'Fernzugriff'],
            ['name' => 'bmcPassword', 'label' => 'BMC Passwort', 'type' => 'text', 'gruppe' => 'Fernzugriff'],
            ['name' => 'remoteID', 'label' => 'Fernwartungs-ID', 'type' => 'text', 'gruppe' => 'Fernzugriff'],
            ['name' => 'remotePassword', 'label' => 'Fernwartungs-Kennwort', 'type' => 'text', 'gruppe' => 'Fernzugriff'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date', 'gruppe' => 'Beschaffung'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date', 'gruppe' => 'Beschaffung'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date', 'gruppe' => 'Beschaffung'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text', 'gruppe' => 'Beschaffung'],
            // The services need the full width - catalog and chips do not fit
            // into half a column.
            ['name' => 'services', 'label' => 'Dienste', 'type' => 'dienste', 'breit' => true, 'gruppe' => 'Dienste'],
        ],
    ],
    'vm' => [
        'model' => VM::class, 'request' => VMRequest::class,
        'relation' => 'vms', 'einzahl' => 'VM', 'suchfelder' => ['name'],
        'bloecke' => true,
        // Host and cluster stand on the card - without preloading the list
        // asks for them one row at a time.
        'mitladen' => ['host', 'cluster'],
        'felder' => [
            // Host and cluster first: both answer the site along with it, and
            // what they answer should not be typed in before them.
            //
            // One or the other: in an HA cluster the VM moves between nodes -
            // a fixed host would be wrong there. Each field therefore
            // disappears as soon as the other is set.
            ['name' => 'server_id', 'label' => 'Host', 'type' => 'auswahl',
                'quelle' => Server::class, 'anzeige' => 'name',
                'sichtbar_wenn' => ['cluster_id' => ['leer' => true]]],
            ['name' => 'cluster_id', 'label' => 'Cluster', 'type' => 'auswahl',
                'quelle' => Cluster::class, 'anzeige' => 'name',
                'sichtbar_wenn' => ['server_id' => ['leer' => true]]],
            // Only without both: otherwise the site comes from there
            // (VM::booted) - kept twice they could drift apart. It is needed
            // all the same, for a vServer at a provider whose host is never
            // documented.
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
            // A global catalog maintained by an admin - no customer_id.
            ['name' => 'mailbox_provider_id', 'label' => 'Anbieter', 'type' => 'auswahl',
                'quelle' => MailboxProvider::class, 'anzeige' => 'name'],
        ],
    ],
    'machine' => [
        'model' => Machine::class, 'request' => MachineRequest::class,
        'relation' => 'machines', 'einzahl' => 'Maschine', 'suchfelder' => ['name'],
        // Carries IP addresses and credentials - both appear when editing.
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
        ],
    ],
    'accesspoint' => [
        'model' => Accesspoint::class, 'request' => AccesspointRequest::class,
        'relation' => 'accesspoints', 'einzahl' => 'Accesspoint', 'suchfelder' => ['name', 'model', 'serialNumber', 'location'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'location', 'label' => 'Montageort', 'type' => 'text', 'platzhalter' => 'z. B. Halle 1, über der Tür'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'camera' => [
        'model' => Camera::class, 'request' => CameraRequest::class,
        'relation' => 'cameras', 'einzahl' => 'Kamera', 'suchfelder' => ['name', 'model', 'serialNumber', 'location'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'location', 'label' => 'Montageort', 'type' => 'text', 'platzhalter' => 'z. B. Halle 1, über der Tür'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
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
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
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
        'relation' => 'dects', 'einzahl' => 'DECT', 'suchfelder' => ['model', 'serialNumber', 'location'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'location', 'label' => 'Montageort', 'type' => 'text', 'platzhalter' => 'z. B. Halle 1, über der Tür'],
            ['name' => 'role', 'label' => 'Rolle', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'mac', 'label' => 'MAC-Adresse', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'iotdevice' => [
        'model' => IoTDevice::class, 'request' => IoTDeviceRequest::class,
        'relation' => 'iotdevices', 'einzahl' => 'IoT-Gerät', 'suchfelder' => ['name', 'model', 'serialNumber', 'location'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'location', 'label' => 'Montageort', 'type' => 'text', 'platzhalter' => 'z. B. Halle 1, über der Tür'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'url', 'label' => 'URL', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'networkshare' => [
        'model' => NetworkShare::class, 'request' => NetworkShareRequest::class,
        'relation' => 'networkshares', 'einzahl' => 'Netzlaufwerk', 'suchfelder' => ['name', 'path', 'drive_letter'],
        'mitladen' => ['host', 'readGroup', 'writeGroup'],
        'sortierungen' => [
            'buchstabe' => ['Laufwerksbuchstabe', 'drive_letter', 'asc', true],
            'name' => ['Name', 'name', 'asc'],
        ],
        'verweise' => [['route' => 'adgroup.index', 'label' => 'AD-Gruppen', 'recht' => 'adgroup_viewAny']],
        'felder' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'platzhalter' => 'z. B. Buchhaltung'],
            ['name' => 'drive_letter', 'label' => 'Laufwerksbuchstabe', 'type' => 'text', 'platzhalter' => 'S'],
            // The machine that holds the share - file servers are often VMs.
            // Choosing the machine prefills the path with \\\\name\\.
            ['name' => 'host', 'label' => 'Server / NAS', 'type' => 'host', 'rolle' => 'share', 'typen' => ['server', 'vm', 'nas'],
                'fuellt' => ['path' => '\\\\{name}\\']],
            ['name' => 'path', 'label' => 'Pfad', 'type' => 'text', 'platzhalter' => '\\\\srv-fs01\\buchhaltung'],
            // The groups the onboarding checklist assigns for read or write access.
            ['name' => 'ad_group_read_id', 'label' => 'AD-Gruppe Lesen', 'type' => 'auswahl',
                'quelle' => ADGroup::class, 'anzeige' => '{name}'],
            ['name' => 'ad_group_write_id', 'label' => 'AD-Gruppe Schreiben', 'type' => 'auswahl',
                'quelle' => ADGroup::class, 'anzeige' => '{name}'],
            // Sensitive drives need the approval of the customer's management in the checklist.
            ['name' => 'sensitive', 'label' => 'Sensibel (Freigabe nötig)', 'type' => 'schalter'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
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
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'networkswitch' => [
        'model' => NetworkSwitch::class, 'request' => NetworkSwitchRequest::class,
        'relation' => 'networkswitches', 'einzahl' => 'Switch', 'suchfelder' => ['name', 'model', 'serialNumber', 'location'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'location', 'label' => 'Montageort', 'type' => 'text', 'platzhalter' => 'z. B. Halle 1, über der Tür'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
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
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
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
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'mac', 'label' => 'MAC-Adresse', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'phonesystem' => [
        'model' => PhoneSystem::class, 'request' => PhoneSystemRequest::class,
        'relation' => 'phonesystems', 'einzahl' => 'TK-Anlage', 'suchfelder' => ['name', 'manufacturer', 'model', 'serialNumber'],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Typ', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'sipaccount' => [
        'model' => SipAccount::class, 'request' => SipAccountRequest::class,
        'relation' => 'sipaccounts', 'einzahl' => 'SIP-Anschluss',
        'suchfelder' => ['provider', 'main_number', 'numbers', 'contract_number'],
        // SIP credentials via the credentials block: single numbers often
        // have one login each, a trunk one for all.
        'bloecke' => true,
        'spalten' => 2,
        'felder' => [
            ['name' => 'provider', 'label' => 'Anbieter', 'type' => 'text', 'gruppe' => 'Anschluss'],
            ['name' => 'account_type', 'label' => 'Anschlussart', 'type' => 'optionen',
                'quelle' => 'custom.sip_account_types', 'gruppe' => 'Anschluss'],
            ['name' => 'product', 'label' => 'Produkt', 'type' => 'text', 'gruppe' => 'Anschluss'],
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort', 'gruppe' => 'Anschluss'],
            ['name' => 'contract_number', 'label' => 'Vertragsnummer', 'type' => 'text', 'gruppe' => 'Anschluss'],
            ['name' => 'provider_customer_number', 'label' => 'Kundennummer', 'type' => 'text', 'gruppe' => 'Anschluss'],
            ['name' => 'hotline', 'label' => 'Hotline', 'type' => 'text', 'gruppe' => 'Anschluss'],
            ['name' => 'channels', 'label' => 'Sprachkanäle', 'type' => 'number', 'gruppe' => 'Anschluss'],
            // A trunk has a main number with a range, single numbers are a list.
            ['name' => 'main_number', 'label' => 'Stammnummer', 'type' => 'text', 'gruppe' => 'Rufnummern',
                'sichtbar_wenn' => ['account_type' => 'trunk']],
            ['name' => 'number_range', 'label' => 'Durchwahlbereich', 'type' => 'text', 'gruppe' => 'Rufnummern',
                'sichtbar_wenn' => ['account_type' => 'trunk']],
            ['name' => 'numbers', 'label' => 'Rufnummern (eine je Zeile)', 'type' => 'mehrzeilig', 'zeilen' => 4,
                'platzhalter' => '040 1234567', 'breit' => true, 'gruppe' => 'Rufnummern',
                'sichtbar_wenn' => ['account_type' => 'single']],
            ['name' => 'registrar', 'label' => 'SIP-Registrar / Proxy', 'type' => 'text', 'gruppe' => 'Technik'],
            ['name' => 'phone_system_id', 'label' => 'TK-Anlage', 'type' => 'auswahl', 'gruppe' => 'Technik',
                'quelle' => PhoneSystem::class, 'anzeige' => '{name} · {manufacturer} {model}'],
            ['name' => 'internet_connection_id', 'label' => 'Über Internetanschluss', 'type' => 'auswahl', 'gruppe' => 'Technik',
                'quelle' => InternetConnection::class, 'anzeige' => '{provider} · {product}'],
            ['name' => 'notes', 'label' => 'Notizen', 'type' => 'mehrzeilig', 'zeilen' => 3, 'breit' => true,
                'gruppe' => 'Sonstiges'],
        ],
    ],
    'scanner' => [
        'model' => Scanner::class, 'request' => ScannerRequest::class,
        'relation' => 'scanners', 'einzahl' => 'Scanner', 'suchfelder' => ['name', 'model', 'serialNumber'],
        'mitladen' => ['scanTargets.login'],
        // Scan targets have no menu item of their own - they are not a device.
        'verweise' => [['route' => 'scantarget.index', 'label' => 'Scan-Ziele', 'recht' => 'scantarget_viewAny']],
        'bloecke' => true,
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'manufacturer', 'label' => 'Hersteller', 'type' => 'text'],
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'purchase_date', 'label' => 'Kaufdatum', 'type' => 'date'],
            ['name' => 'warranty_until', 'label' => 'Garantie bis', 'type' => 'date'],
            ['name' => 'eol_date', 'label' => 'Support-Ende (EOL)', 'type' => 'date'],
            ['name' => 'supplier', 'label' => 'Lieferant', 'type' => 'text'],
        ],
    ],
    'scantarget' => [
        'model' => ScanTarget::class, 'request' => ScanTargetRequest::class,
        'relation' => 'scantargets', 'einzahl' => 'Scan-Ziel', 'suchfelder' => ['name', 'target', 'description'],
        // The card shows which scanners the target is set up on.
        'mitladen' => ['scanners', 'login'],
        'verweise' => [['route' => 'scanner.index', 'label' => 'Scanner', 'recht' => 'scanner_viewAny']],
        'felder' => [
            ['name' => 'name', 'label' => 'Bezeichnung', 'type' => 'text', 'platzhalter' => 'z. B. Buchhaltung'],
            ['name' => 'kind', 'label' => 'Art', 'type' => 'optionen', 'werte' => ScanTarget::ARTEN],
            ['name' => 'target', 'label' => 'Ziel', 'type' => 'text', 'platzhalter' => '\\\\srv-file01\\scans'],
            // Only the passwords from "Zugangsdaten" (global scope of
            // LoginGeneral), limited to the customer.
            ['name' => 'login_general_id', 'label' => 'Zugangsdaten', 'type' => 'auswahl',
                'quelle' => LoginGeneral::class, 'anzeige' => '{name} ({username})'],
            ['name' => 'description', 'label' => 'Beschreibung', 'type' => 'text'],
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
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'firmware', 'label' => 'Firmware', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
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
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'port', 'label' => 'Port', 'type' => 'text'],
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
            ['name' => 'model', 'label' => 'Modell', 'type' => 'text'],
            ['name' => 'serialNumber', 'label' => 'Seriennummer', 'type' => 'text'],
            ['name' => 'firmware', 'label' => 'Firmware', 'type' => 'text'],
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
