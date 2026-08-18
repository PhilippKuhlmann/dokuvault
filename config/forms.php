<?php

use App\Http\Requests\ADDomainRequest;
use App\Http\Requests\ADGroupRequest;
use App\Http\Requests\BackupRequest;
use App\Http\Requests\CertificateRequest;
use App\Http\Requests\ContactPersonRequest;
use App\Http\Requests\DomainRequest;
use App\Http\Requests\DynDNSRequest;
use App\Http\Requests\FTPServerRequest;
use App\Http\Requests\LoginGeneralRequest;
use App\Http\Requests\LoginWebsiteRequest;
use App\Http\Requests\MachineRequest;
use App\Http\Requests\RackRequest;
use App\Models\ADDomain;
use App\Models\ADGroup;
use App\Models\Backup;
use App\Models\Certificate;
use App\Models\ContactPerson;
use App\Models\Domain;
use App\Models\DynDNS;
use App\Models\FTPServer;
use App\Models\LoginGeneral;
use App\Models\LoginWebsite;
use App\Models\Machine;
use App\Models\Rack;

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
    'rack' => [
        'model' => Rack::class, 'request' => RackRequest::class,
        'relation' => 'racks', 'einzahl' => 'Serverschrank', 'suchfelder' => ['name', 'location'],
        'felder' => [
            ['name' => 'site_id', 'label' => 'Standort', 'type' => 'standort'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['name' => 'location', 'label' => 'Aufstellort', 'type' => 'text'],
            ['name' => 'height_units', 'label' => 'Höheneinheiten', 'type' => 'number'],
            ['name' => 'note', 'label' => 'Notiz', 'type' => 'text'],
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
];
