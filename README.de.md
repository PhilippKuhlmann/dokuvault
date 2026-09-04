<div align="center">

**Deutsch** · [English](README.md)

# 📘 DokuVault

### Die Open-Source-IT-Dokumentation für Managed Service Provider

Zentrale, mandantenfähige Dokumentation der **kompletten Kunden-IT** – vom Standort über Server,
Netzwerk und Active Directory bis zu Lizenzen und Zugangsdaten. Mit geführter Erstaufnahme,
PDF-Export, globaler Suche über alle Kunden und Geräten, die sich per Agent
**selbst dokumentieren**.

[![Tests](https://github.com/PhilippKuhlmann/dokuvault/actions/workflows/tests.yml/badge.svg)](https://github.com/PhilippKuhlmann/dokuvault/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9)
![Tests](https://img.shields.io/badge/Tests-1033%20grün-3fb950)
![License](https://img.shields.io/badge/License-MIT-blue)

**[▶ Live-Demo ausprobieren](https://doku.dokuvault.de)**

Anmeldung mit `admin`, `techniker`, `kunde-rw` oder `kunde-r` – Passwort jeweils `password`
<br><sub>Jede Rolle sieht etwas anderes. Alles darf verändert werden, die Demo setzt sich stündlich zurück.</sub>

<br>

<img src="docs/screenshots/dashboard.png" alt="Kunden-Dashboard" width="900">

</div>

---

## ✨ Warum DokuVault?

MSPs verlieren Zeit mit verstreuten Excel-Listen, veralteten Wikis und „wo stand das nochmal?".
**DokuVault** bündelt alles an einem Ort – strukturiert, durchsuchbar, verschlüsselt und
immer aktuell.

|  |  |
| --- | --- |
| 🏢 **Mandantenfähig** | Jeder Kunde mit eigenen Standorten, Geräten und Zugängen – sauber getrennt |
| 🧭 **Erstaufnahme-Assistent** | 16 Schritte führen durch den Neukunden – Frage stellen, Antwort speichern, weiter |
| 🔌 **Patchfelder** | Je Port die Dosennummer, den Raum und den Ziel-Switch – „wo hängt Dose A.12?" |
| 🔎 **Globale Suche** | Server, IP, Seriennummer oder MAC über **alle** Kunden in Sekunden finden |
| 🤖 **Auto-Dokumentation** | Ein Script auf dem Gerät – der Rest dokumentiert sich selbst (Proxmox, Windows AD) |
| 🌐 **IPAM** | Belegte, freie & reservierte IP-Adressen je VLAN auf einen Blick, DHCP- und Gateway-Erkennung |
| 🔐 **Verschlüsselt** | Alle Passwörter verschlüsselt gespeichert, rollenbasierte Zugriffe, Audit-Log |
| 📄 **PDF-Export** | Komplette Kundendokumentation auf Knopfdruck als PDF |
| 🌙 **Hell & Dunkel** | Modernes, responsives UI – auch auf dem Smartphone |
| 🌍 **Deutsch & Englisch** | Umschaltbar je Benutzer oder der Browsersprache folgend |
| ⏰ **Ablauf-Warnungen** | Lizenzen, Zertifikate & Domains laufen nie unbemerkt ab |
| ♻️ **Papierkorb** | Versehentlich gelöscht? Wiederherstellen statt neu erfassen |

---

## 📸 Screenshots

<table>
  <tr>
    <td width="50%"><img src="docs/screenshots/dashboard.png" alt="Dashboard"><br><sub><b>Kunden-Dashboard</b> – Inventar, ablaufende Lizenzen & Zertifikate auf einen Blick</sub></td>
    <td width="50%"><img src="docs/screenshots/search.png" alt="Globale Suche"><br><sub><b>Globale Suche</b> – über alle Gerätetypen und Kunden hinweg</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/computers.png" alt="Geräteliste"><br><sub><b>Geräte</b> – übersichtliche Karten, IP/Seriennummer per Klick kopieren</sub></td>
    <td width="50%"><img src="docs/screenshots/ipam.png" alt="IPAM"><br><sub><b>IPAM</b> – belegte, freie und reservierte Adressen je VLAN, jeder Bereich in eigener Farbe</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/autodoc.png" alt="Auto-Dokumentation"><br><sub><b>Auto-Dokumentation</b> – Agent-Token erzeugen, Script ausführen, fertig</sub></td>
    <td width="50%"><img src="docs/screenshots/certificates.png" alt="Zertifikate"><br><sub><b>SSL/TLS-Zertifikate</b> – mit Ablauf-Warnung im Dashboard</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/wizard.png" alt="Erstaufnahme-Assistent"><br><sub><b>Erstaufnahme-Assistent</b> – eine Frage je Schritt, Bestand bleibt sichtbar</sub></td>
    <td width="50%"><img src="docs/screenshots/rack.png" alt="Serverschrank-Editor"><br><sub><b>Serverschränke</b> – Vorder- und Rückseite bestücken, daneben die gezeichnete Ansicht</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/patchpanel.png" alt="Patchfeld-Ports bearbeiten"><br><sub><b>Patchfeld-Ports</b> – je Port Dosennummer, Raum und Ziel-Switch eintragen</sub></td>
    <td width="50%"><img src="docs/screenshots/patchpanel-liste.png" alt="Patchfeld-Liste"><br><sub><b>Dosenübersicht</b> – welche Dose auf welchem Switch-Port liegt</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/rackcatalog.png" alt="Rack-Katalog im Adminbereich"><br><sub><b>Rack-Katalog</b> – Blindplatten, Fachböden & Co. im Adminbereich pflegen</sub></td>
    <td width="50%"><img src="docs/screenshots/login.png" alt="Anmeldung"><br><sub><b>Anmeldung</b> – Hell/Dunkel und Sprache umschaltbar, auch ohne Anmeldung</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/rackliste.png" alt="Serverschrank-Liste"><br><sub><b>Schrank-Übersicht</b> – Eckdaten in einer Zeile, Belegung und Zeichnung je Seite</sub></td>
    <td width="50%"><img src="docs/screenshots/server.png" alt="Serverliste"><br><sub><b>Server</b> – Zugangsdaten, BMC, Fernwartung und Hardware je Gerät, Kennwörter maskiert</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/vms.png" alt="VM-Liste"><br><sub><b>VMs</b> – mit Host oder Cluster, auf dem sie laufen</sub></td>
    <td width="50%"><img src="docs/screenshots/loginwebsites.png" alt="Webseiten-Logins"><br><sub><b>Webseiten-Logins</b> – Kennwort maskiert, auf Klick sichtbar oder kopiert</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/eol.png" alt="Support-Ende (EOL)"><br><sub><b>Support-Ende (EOL)</b> – welche Systeme ohne Sicherheitsupdates laufen, je Kunde gruppiert</sub></td>
    <td width="50%"><img src="docs/screenshots/protokoll.png" alt="Aktivitätsprotokoll"><br><sub><b>Aktivitätsprotokoll</b> – durchsuch- und filterbar, inklusive gescheiterter und gesperrter Anmeldungen</sub></td>
  </tr>
</table>

---

## 🧭 Erstaufnahme-Assistent – geführt statt geraten

Einen Neukunden aufzunehmen hieß bisher: Bereich in der Seitenleiste suchen, „Neu" klicken,
Formular ausfüllen, speichern, zurück, nächster Bereich – sechzehnmal. Man musste selbst wissen,
**was** zu dokumentieren ist und **in welcher Reihenfolge**.

Der Assistent dreht das um. Er stellt der Reihe nach eine Frage („Welche VLANs gibt es?") und legt
jede Antwort sofort an:

| Phase | Schritte |
| --- | --- |
| **Grunddaten** | Standorte → Ansprechpartner |
| **Netzwerk** | Internet-Anschlüsse → Router → VLANs → WLAN-Netze → Switches → Accesspoints |
| **Server & Speicher** | Server → VMs → NAS |
| **Clients** | Computer → Drucker |
| **Dienste** | AD-Domänen → TK-Anlagen → Backups |

- **Reihenfolge steckt in der App**, nicht im Kopf: WLAN kommt nach den VLANs, deren Auswahl die
  gerade angelegten Netze bereits enthält.
- **Sofort gespeichert** – jeder Eintrag landet direkt in der Doku, nicht erst am Ende.
- **Bestand bleibt sichtbar**: Schon Erfasstes steht über dem Formular, Schritte lassen sich
  überspringen.
- **Jederzeit fortsetzbar** – der Fortschritt liegt in der Datenbank; das Dashboard bietet einen
  offenen Durchlauf zum Fortsetzen an.
- **Gleiche Regeln wie die normalen Formulare**: Die Validierung stammt aus denselben
  FormRequests, Schritte ohne Anlege-Recht werden gar nicht erst gezeigt.

Einstieg über **Sonstiges → Erstaufnahme-Assistent** oder die Karte auf dem Kunden-Dashboard.

---

## 🤖 Auto-Dokumentation – Geräte dokumentieren sich selbst

Schluss mit manuellem Abtippen. Erzeuge in der Oberfläche einen **an Kunde und Standort gebundenen
Agent-Token**, lade das passende Script herunter und führe es auf dem Gerät aus – die Infrastruktur
landet automatisch in der Doku. Wiederholte Läufe aktualisieren, statt zu duplizieren.

```bash
# Auf dem Proxmox-Host als root:
bash proxmox-doku.sh
```

Der Proxmox-Agent erfasst Host-Hardware, Seriennummer, IP und **alle VMs & LXC-Container** (inkl.
IP über den QEMU-Gast-Agent) und legt sie als Server samt Gästen an.

```powershell
# Auf einem Domaincontroller (bzw. Rechner mit RSAT-AD-Modul):
.\windows-ad-doku.ps1
```

Der Windows-AD-Agent liest alle Benutzer sowie **nur selbst angelegte Gruppen** aus – Standard-/
Built-in-Gruppen und System-Konten (Gast, krbtgt, DefaultAccount …) werden bereits am DC
herausgefiltert, der eingebaute Administrator bleibt erhalten. Passwörter werden nie ausgelesen
oder übertragen.

Jeder Token darf **ausschließlich dokumentieren** – bei einem Leak kein weiterer Zugriff. Weitere
Agenten folgen.

---

## 🧩 Funktionsumfang

- **Kunden & Standorte** – mehrmandantenfähige Struktur je Kunde
- **Infrastruktur** – Server, VMs (mit Host-Zuordnung), NAS, Computer, USV, Maschinen, IoT
- **Serverschränke** – Racks mit Drag-&-Drop-Bestückung, **Vorder- und Rückseite**:
  dokumentierte Geräte und passive Elemente je Höheneinheit platzieren. Geräte in voller
  Tiefe belegen beide Seiten, halbtiefe lassen dahinter Platz. Neben dem beschrifteten
  Schema eine gezeichnete Ansicht, die sich der Höhe anpasst; der Katalog passiver
  Elemente wird im Adminbereich gepflegt
- **Patchfelder** – je Port Dosennummer, Raum und Ziel-Switch samt Portnummer; die Portzeilen
  entstehen automatisch aus der Portanzahl
- **Netzwerk** – Router, Switches, Access Points, WLAN, VLANs, **IPAM** (mit reservierten Adressbereichen je VLAN, farblich getrennt), Internet/WAN, UTM-Firewalls; Internet-Anschlüsse optional mit geroutetem Netz (CIDR) und Gateway
- **Active Directory** – Domains, Benutzer, Gruppen
- **Kommunikation** – Telefonanlagen, DECT, E-Mail-Postfächer, E-Mail-Archivierung
- **Sicherheit & Zertifikate** – SSL/TLS-Zertifikate mit Ablauf-Warnung
- **Geräte** – Kameras, Recorder, Drucker
- **Dienste** – FTP, DynDNS, Domains, Backups
- **Lizenzen** – Software-, Windows- und Zugriffslizenzen inkl. Ablaufdaten & Datei-Upload
- **Zugangsdaten** – verschlüsselte Logins, Passwort anzeigen & kopieren, vorheriges Passwort
  bleibt für eine einstellbare Frist nachschlagbar – für den Fall, dass jemand falsch geändert hat
- **Erfassung** – Erstaufnahme-Assistent (16 geführte Schritte), Auto-Dokumentation per Agent,
  Agent-Token auf eigener Seite verwaltet (anlegen, einmalig anzeigen, widerrufen)
- **Betrieb** – globale Suche, durchsuch- und filterbares Aktivitätsprotokoll (Ereignis, Objektart,
  Benutzer, Zeitraum), Papierkorb (Wiederherstellen, dazu eine Adminansicht über alle Kunden),
  PDF-Export, Dateiablage
- **Benutzer einladen** – per E-Mail statt mit einem Kennwort, das man ihnen durchsagt: Der
  Eingeladene vergibt es sich selbst über einen Link
- **Adminbereich** – rechtebasiert statt an eine Rolle verdrahtet: Kunden, Benutzer & Rollen,
  Auswahlmenüs, Einstellungen, Papierkorb, Aktivitätsprotokoll und API-Token sind eigene Rechte,
  frei kombinierbar je Rolle
- **Einstellungen** – ohne Zugang zum Server: Name und Logos, Sprache, Zeitzone, ein Hinweis auf
  der Anmeldeseite, Zeilen je Seite, Upload-Grenze und erlaubte Dateiendungen; SMTP-Zugang für den
  Mailversand; Vorwarnzeiten für Lizenzen, Zertifikate, Garantien und Support-Ende sowie die
  Aufbewahrung der PDF-Ausgaben; Kennwortregeln, Anmeldesperre und Sitzungsdauer
- **Standortfilter** – schränkt Gerätelisten, IPAM und Auto-Dokumentation auf einen Standort ein
- **Sprache** – Deutsch und Englisch, je Benutzer oder der Browsersprache folgend

---

## 🔒 Sicherheit

- **Zwei-Faktor-Anmeldung (TOTP)** – im Profil einzurichten, per QR-Code oder kopierbarem
  Geheimnis, mit Wiederherstellungscodes zum Ausdrucken. Ein Administrator kann sie für einzelne
  Benutzer verpflichtend machen; wer sie einrichten muss, kommt bis dahin nur ins eigene Profil
- **Bremse gegen Durchprobieren** – zwei Zähler: einer je Konto, einer je Herkunft. Wer ein
  einziges Kennwort gegen viele Benutzernamen probiert, löst den ersten nie aus. Versuche und
  Sperrdauer sind einstellbar
- **Kennwortregeln einstellbar** – Mindestlänge, Groß-/Kleinschreibung, Ziffer, Sonderzeichen und
  Abgleich gegen bekannte Datenlecks. Gilt für die Kennwörter, mit denen sich Benutzer **anmelden**
  – nicht für die dokumentierten Kennwörter der Kunden: Dort wird festgehalten, was ist, nicht was
  sein soll
- **Sitzung am Kennwort-Hash** – eine Kennwortänderung beendet jede andere Sitzung des Benutzers,
  auch die auf einem verlorenen Gerät
- Passwörter & sensible Felder **verschlüsselt at rest** (`Crypt`)
- **Rollenbasierte** Zugriffe (Admin / Techniker / Kunde) mit granularen Berechtigungen
- **Audit-Log** aller Änderungen; ein geändertes Passwort steht nie im Eintrag selbst – der alte
  Wert liegt verschlüsselt in einer eigenen Tabelle, sichtbar erst auf Klick und nur für den, der
  das Gerät ohnehin sehen darf
- Schutz gegen **IDOR** (fremde Kunden-/Standortzuweisung), XSS-Härtung, verschlüsselte Sessions
- **Dateiuploads** gegen Pfadmanipulation im Dateinamen gehärtet; erlaubt ist eine Positivliste von
  Endungen, die sich kürzen, aber nicht erweitern lässt
- **PDF-Ausgaben** enthalten alle Zugangsdaten eines Kunden im Klartext und werden nach einer
  einstellbaren Frist automatisch gelöscht
- Verantwortungsvolle Meldung von Lücken über [SECURITY.de.md](SECURITY.de.md)

---

## 🏗️ Aufbau

Alle Objekttypen folgen demselben Muster – wer einen kennt, kennt alle. Vier Listen in
`config/custom.php` halten das zusammen:

| Schlüssel | Wofür |
| --- | --- |
| `permissions` | erzeugt je Objekt die Gates `_viewAny`, `_create`, `_update`, `_delete` (im `AuthServiceProvider`) |
| `trashables` | welche Objekte im Papierkorb erscheinen und wiederherstellbar sind |
| `list_titles` | Überschrift der jeweiligen Listenseite |
| `wizard_steps` | Reihenfolge, Fragen und Felder des Erstaufnahme-Assistenten |

Ein neuer Objekttyp braucht damit Model, Migration, FormRequest, Controller und Views – plus je
einen Eintrag in den Listen, die ihn betreffen. Neue Rechte-Gates, Papierkorb-Anbindung und
Seitentitel entstehen daraus von selbst.

Jede Ressource liegt unter `/{customer}/…`; die Kundenbindung wird über Route-Model-Binding und
`getFilteredQuery()` im Basis-Controller durchgesetzt, der zusätzlich den Standortfilter aus der
Seitenleiste anwendet. Passwortfelder verschlüsselt das Model selbst per `Attribute`-Cast, sodass
Klartext weder in der Datenbank noch im Audit-Log landet.

---

## ⚙️ Tech-Stack

| Bereich | Eingesetzt |
| --- | --- |
| **Backend** | PHP 8.2 · Laravel 12 · Livewire 4 · Laravel Sanctum 4 *(Agent-/API-Token)* |
| **Pakete** | spatie/laravel-activitylog 4.12 *(Audit-Log)* · barryvdh/laravel-dompdf 3.0 *(PDF-Export)* · spatie/laravel-backup 9.3 |
| **Frontend** | Tailwind CSS 3.4 · Alpine.js 3 · Flowbite 1.8 · Vite 3 |
| **Datenbank** | MySQL / MariaDB |
| **Qualität** | Pest 3 *(1033 Tests)* · Laravel Pint · GitHub Actions CI |

---

## 📦 Installation

Voraussetzungen: entweder Docker – oder PHP 8.2+, Composer, Node.js und MySQL/MariaDB.

### Mit Docker (am schnellsten)

```bash
git clone https://github.com/PhilippKuhlmann/dokuvault.git && cd dokuvault && docker compose up
```

Danach [http://localhost:8000](http://localhost:8000) öffnen und mit `admin` / `password`
anmelden. Datenbank, Demo-Daten und Zugänge legt der erste Start selbst an; ein zweiter
Start seedet nicht erneut, die eingegebenen Daten bleiben also erhalten.

Der Container ist zum Ausprobieren und für kleine Installationen gedacht: ein Prozess mit
Laravels eingebautem Server, kein nginx. Für den Betrieb mit vielen Nutzern ist der Weg in
[DEPLOYMENT.de.md](DEPLOYMENT.de.md) der richtige.

### Zum Ausprobieren (mit Demo-Daten)

Installiert den Demo-Kunden „Mustermann" samt realistischer Beispieldaten. Die Demo-Daten
benötigen `fakerphp/faker` – daher hier **mit** Dev-Abhängigkeiten installieren.

```bash
git clone https://github.com/PhilippKuhlmann/dokuvault.git
cd dokuvault

composer install                     # inkl. Dev-Pakete (Faker für Demo-Daten)
npm install && npm run build

cp .env.example .env                 # APP_ENV=local, DB-Zugang etc. anpassen
php artisan key:generate

php artisan migrate:fresh --seed     # legt Demo-Kunde + Demo-Zugänge an
```

Danach mit einem der Demo-Zugänge anmelden.

### Produktiv-Betrieb (ohne Demo-Daten)

Für einen echten Server – **kein** Faker, keine Demo-Daten, nur die Startdaten
(Admin-Benutzer, Rollen/Rechte, Betriebssystem- & Mail-Anbieter-Listen):

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build

cp .env.example .env
# WICHTIG in der .env:  APP_ENV=production   (steuert HTTPS-Zwang & den Seeder)
php artisan key:generate

php artisan migrate --force
php artisan db:seed --force          # führt den ProductionDatabaseSeeder aus
```

> Der Seeder verzweigt anhand von `APP_ENV`: bei `local` die Demo-Daten, bei `production`
> nur die Startdaten. Steht `APP_ENV` auf `local`, aber ohne Dev-Pakete installiert, schlägt
> das Seeding fehl (`fake()` nicht gefunden) – dann entweder `APP_ENV=production` setzen oder
> mit Dev-Paketen installieren.

### Aktualisieren

Eine bestehende Installation auf einen neueren Stand bringen – Backup, `git pull`,
Abhängigkeiten, Migrationen – steht Schritt für Schritt in
**[DEPLOYMENT.de.md → Aktualisieren](DEPLOYMENT.de.md#aktualisieren)**. Wer sich auf eine
Version festlegen will statt `main` zu folgen, nimmt einen Tag im Format `vJJ.MM.TT`.

### Automatisch deployen

Ein Push auf `main` kann den Server selbst aktualisieren: Erst laufen die Tests, und nur
wenn sie grün sind, holt eine GitHub-Action per SSH den neuen Stand, migriert und baut die
Caches neu. Einrichtung, Secrets und der stündliche Reset einer öffentlichen Demo stehen in
**[DEPLOYMENT.de.md](DEPLOYMENT.de.md)**.

---

## 👥 Rollen & Demo-Zugänge

| Rolle         | Rechte                                              |
| ------------- | --------------------------------------------------- |
| **Admin**     | Alles, immer – eine eingebaute Ausnahme, die sich durch kein abgewähltes Häkchen aussperren lässt |
| **Techniker** | Zugriff auf alle Kunden; der Adminbereich ist je Rolle einzeln freigebbar – siehe unten |
| **Kunde**     | Sieht nur die eigenen Daten                         |

Der Seeder legt vier Zugänge an – dieselben gelten in der
**[Live-Demo](https://doku.dokuvault.de)**. Am meisten sieht man, wenn man sie nacheinander
ausprobiert: Die Seitenleiste, die „Neu"-Schaltflächen und der Adminbereich ändern sich je Rolle.

| Benutzername | Passwort   | Rolle     | Was man damit sieht |
| ------------ | ---------- | --------- | ------------------- |
| `admin`      | `password` | Admin     | Alles: alle Kunden, der vollständige Adminbereich, Aktivitätsprotokoll |
| `techniker`  | `password` | Techniker | Alle Kunden, in der Demo zusätzlich der vollständige Adminbereich – siehe unten |
| `kunde-rw`   | `password` | Kunde     | Nur den Kunden „Mustermann", lesen **und** schreiben |
| `kunde-r`    | `password` | Kunde     | Nur den Kunden „Mustermann", ausschließlich lesen – keine „Neu"- und „Bearbeiten"-Schaltflächen |

Der Adminbereich ist **rechtebasiert, nicht an eine Rolle fest verdrahtet**: Jeder seiner
Bereiche – Kunden, Benutzer & Rollen, Auswahlmenüs, Einstellungen, Papierkorb,
Aktivitätsprotokoll, API-Token – hat ein eigenes Recht, in der Rollenverwaltung frei
kombinierbar. Eine zweite Technikergruppe, die den Papierkorb und das Protokoll sehen darf,
aber keine Benutzer verwaltet, ist ein Häkchen entfernt, kein Codeeingriff. Nur die
eingebaute Rolle **Admin** ist eine Ausnahme: Sie besteht jede Rechteprüfung bedingungslos,
damit ein versehentlich abgewähltes „Rollen verwalten" nie den letzten Zugang zur
Rollenverwaltung kostet. In der Demo haben `admin` und `techniker` alle Rechte angehakt,
deshalb sehen sie dort gleich aus.

> ⚠️ **Diese Zugänge gehören nicht auf einen echten Server.** Sie stammen aus dem Demo-Seeder
> und haben überall dasselbe Passwort. Für den Produktivbetrieb eigene Benutzer anlegen und die
> Demo-Accounts löschen.

---

## 🧪 Tests

1033 Feature-Tests (Pest 3) laufen gegen eine In-Memory-SQLite – keine Einrichtung nötig, keine
Spuren in der Entwicklungsdatenbank. Bei jedem Push führt GitHub Actions dieselbe Suite aus –
gegen PHP 8.2 und 8.3, jeweils mit SQLite und MariaDB, dazu PHP 8.4 mitlaufend, aber nicht
blockierend.

```bash
php artisan test
```

Einzelne Bereiche:

```bash
php artisan test --filter=DocumentationWizard
```

Abgedeckt sind unter anderem die Mandantentrennung (kein Zugriff auf fremde Kunden und Standorte),
die Berechtigungs-Gates je Rolle, der Erstaufnahme-Assistent inklusive der Feldlisten seiner
Schritte, der Standortfilter über alle Listen sowie Papierkorb und Wiederherstellung. Dazu die
Anmeldung mit zweiter Stufe, die Bremse gegen Durchprobieren und die Verschlüsselung: Ein Test
gleicht alle Spalten, deren Name auf ein Geheimnis hindeutet, mit denen ab, die tatsächlich
verschlüsselt sind – wer eine neue anlegt, muss sie verschlüsseln oder begründen, warum nicht.

Code-Stil vor dem Commit prüfen:

```bash
./vendor/bin/pint
```

---

## 🤝 Mitwirken & Lizenz

Beiträge sind willkommen – siehe [CONTRIBUTING.de.md](CONTRIBUTING.de.md) und
[CODE_OF_CONDUCT.de.md](CODE_OF_CONDUCT.de.md). Sicherheitslücken bitte gemäß [SECURITY.de.md](SECURITY.de.md)
melden (nicht als öffentliches Issue).

Veröffentlicht unter der [MIT-Lizenz](LICENSE).
