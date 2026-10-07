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
![Tests](https://img.shields.io/badge/Tests-1394%20grün-3fb950)
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
| 🤖 **Auto-Dokumentation** | **Installierte Agenten** für Windows, Proxmox und Linux melden von selbst – Rollen, Intervall und „Jetzt melden“ stellst du in DokuVault ein. Dazu Scripte für Hyper-V, VMware, UniFi und Microsoft 365 |
| 💾 **Backup-Überwachung** | Veeam, Windows Server-Sicherung und Proxmox melden jeden Lauf – die letzten Läufe auf einen Blick, Fehler auf dem Dashboard |
| 📊 **Statistik** | API-Auslastung, Datenwachstum, Zustand der Agenten, Nutzung und Systemressourcen – um zu sehen, wann der Server mehr Leistung braucht |
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
    <td width="50%"><img src="docs/screenshots/autodoc.png" alt="Auto-Dokumentation"><br><sub><b>Auto-Dokumentation</b> – ein Token für Agenten und Scripte: erzeugen, installieren, fertig</sub></td>
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
    <td width="50%"><img src="docs/screenshots/protokoll.png" alt="Aktivitätsprotokoll"><br><sub><b>Aktivitätsprotokoll</b> – wer wann was angelegt, geändert, gelöscht oder wiederhergestellt hat; durchsuch- und filterbar</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/admin-dashboard.png" alt="Admin-Dashboard"><br><sub><b>Adminbereich</b> – Zustand der eigenen Sicherung, Inventar über alle Kunden, ablaufende Verträge</sub></td>
    <td width="50%"><img src="docs/screenshots/agenten-installiert.png" alt="Installierte Agenten"><br><sub><b>Installierte Agenten</b> – Rollen je Rechner, Intervall, „Jetzt melden“ und das Ergebnis jedes Laufs</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/backups.png" alt="Backup-Überwachung"><br><sub><b>Backup-Überwachung</b> – die letzten Läufe jedes gemeldeten Jobs über alle Kunden</sub></td>
    <td width="50%"><img src="docs/screenshots/statistik-auslastung.png" alt="API-Auslastung"><br><sub><b>API-Auslastung</b> – Anfragen von Agenten und API-Token je Stunde, Tagesprofil, Antwortzeiten</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/statistik-agenten.png" alt="Agenten-Statistik"><br><sub><b>Agenten</b> – wer meldet, wer schweigt, wessen Lauf scheiterte, wer noch eine alte Version hat</sub></td>
    <td width="50%"><img src="docs/screenshots/statistik-wachstum.png" alt="Datenwachstum"><br><sub><b>Datenwachstum</b> – wie die Doku wächst, insgesamt und je Kunde</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="docs/screenshots/backup-einstellungen.png" alt="Backup-Einstellungen"><br><sub><b>Eigene Sicherung</b> – Zeitplan, SFTP/FTP-Ziel, Aufbewahrung, Archivpasswort, Download</sub></td>
    <td width="50%"><img src="docs/screenshots/adgruppen.png" alt="AD-Gruppen"><br><sub><b>AD-Gruppen</b> – mit ihren Mitgliedern, eingetragen vom Agenten auf dem Domänencontroller</sub></td>
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

## 🤖 Auto-Dokumentation – die Umgebung dokumentiert sich selbst

Schluss mit Abtippen. Erzeuge in der Oberfläche einen **an Kunde und Standort gebundenen
Agent-Token**, installiere einen Agenten oder führe ein Script aus – der Bestand landet von selbst
in der Doku. **Ein Token gilt für alle Agenten**, und wiederholte Läufe aktualisieren, statt zu
verdoppeln.

<img src="docs/screenshots/agenten.png" alt="Übersicht der Agenten" width="900">

### Installierte Agenten – einmal einrichten, aus DokuVault steuern

Der empfohlene Weg: ein Agent, der auf dem Rechner bleibt und von selbst meldet.

| Agent | Einrichten | Meldet |
| --- | --- | --- |
| **Windows** | `.exe` herunterladen (Adresse und Token stecken schon drin) und doppelklicken – ein Windows-Dienst | Server, Active Directory (Benutzer, Gruppen, Mitgliedschaften), Hyper-V, Arbeitsplatz, Veeam- und Windows-Server-Sicherungen |
| **Proxmox** | `bash dokuvault-agent-proxmox.sh` als root – ein systemd-Timer | Host, VMs, Container, vzdump-Backup-Jobs; Proxmox-**Tags werden zu Diensten**, wenn der Katalog sie kennt |
| **Linux** | `bash dokuvault-agent-linux.sh` als root auf Debian/Ubuntu – ein systemd-Timer | Hardware, Betriebssystem, IP-Adresse und laufende Dienste |

<img src="docs/screenshots/agenten-installiert.png" alt="Installierte Agenten" width="900">

- **Rollen je Rechner** – jeder Agent erkennt, was sein Rechner kann; du hakst an, was er melden
  soll. Bei zwei Domänencontrollern stehen beide als Server in der Doku, aber nur einer macht das AD.
- **Intervall und „Jetzt melden“** je Rechner, eine Vorgabe für alle unter *Einstellungen → Agenten*.
  Ein Zufallsabstand verhindert, dass alle nach einem gemeinsamen Neustart im Gleichtakt melden.
- **Ergebnis jedes Laufs** je Rolle – ein fehlgeschlagener Veeam-Job oder ein stiller Agent steht
  auf dem Dashboard und unter *Statistik → Agenten*.
- **Selbst-Update** – installierte Agenten holen sich eine neue Version selbst.
- **Backups** – Veeam, Windows Server-Sicherung und Proxmox melden jeden Lauf; die letzten Läufe je
  Job stehen auf der Backup-Karte des Kunden und über alle Kunden unter *Backups*.
- Die Windows-`.exe` lässt sich **signieren** (`scripts/build-windows-agent.sh` mit Zertifikat in
  `scripts/signing.env`); der beim Download angehängte Token lässt die Signatur gültig.

### Scripte auf dem Gerät selbst

Diese Agenten brauchen nichts außer ihrem Token – sie lesen die Maschine, auf der sie laufen.

```bash
# Auf dem Proxmox-Host, als root:
bash proxmox-doku.sh
```

Der **Proxmox**-Agent erfasst Host-Hardware, Seriennummer, IP, CPU, Arbeitsspeicher und
Storage-Pools sowie **alle VMs und LXC-Container** (IP über den QEMU-Gastagenten bzw. die
Container-Konfiguration) und legt sie als Server samt Gästen an.

```powershell
# Auf dem Hyper-V-Host bzw. auf einem Windows-Server (als Administrator):
.\hyperv-doku.ps1
.\windows-server-doku.ps1
```

**Hyper-V** meldet den Host und jede virtuelle Maschine. Der **Windows-Server**-Agent legt den
Rechner als **Server** an, nicht als Client – wer früher den Client-Agenten auf einem Server
laufen ließ, fand ihn danach unter „Clients", wo ihn niemand sucht. Zusätzlich liest er die
installierten Rollen (AD, DNS, DHCP, Fileserver …) und trägt sie als Dienste ein, **solange das
Feld leer ist**: Wer die Dienste einmal von Hand gepflegt hat, weiß mehr als `Get-WindowsFeature`.

```powershell
# Auf einem Domaincontroller bzw. einem Rechner mit RSAT-AD-Modul:
.\windows-ad-doku.ps1

# Auf einem Arbeitsplatzrechner:
.\windows-client-doku.ps1
```

Der **Windows-AD**-Agent liest alle Benutzer sowie **nur selbst angelegte Gruppen** – Standard- und
Built-in-Gruppen und System-Konten (Gast, krbtgt, DefaultAccount …) werden bereits am DC
herausgefiltert, der eingebaute Administrator bleibt erhalten. Kennwörter werden nie ausgelesen;
im AD stehen sie als Hash und sind gar nicht lesbar.

Der **Windows-Client**-Agent meldet Hostname, Hersteller, Modell, Seriennummer, Betriebssystem und
IP eines Arbeitsplatzrechners. Erkannt wird er an einer eigenen Kennung, nicht am Namen – ein
umbenannter Rechner bleibt derselbe Eintrag, statt ein zweiter zu werden.

### Über das Netz

Diese drei laufen nicht auf dem Gerät, sondern fragen ein System über dessen Schnittstelle ab.

```powershell
.\unifi-doku.ps1        -Controller "https://unifi.local" -User "doku" -Password "…" -Site "Kunde A"
.\vmware-doku.ps1       -Server "vcenter.local" -User "doku@vsphere.local" -Password "…"
.\microsoft365-doku.ps1 -TenantId "…" -ClientId "…" -ClientSecret "…"
```

**UniFi** holt Switches, Accesspoints und WLANs – erkannt an der MAC-Adresse bzw. der
Controller-Id, ein umbenanntes Gerät bleibt derselbe Eintrag. Das Script spricht sowohl UniFi OS
(UDM, Cloud Key Gen2+) als auch den klassischen Controller an, ohne dass man die Bauart kennen
muss. **VMware** macht dasselbe wie Hyper-V, aber je ESXi-Host unter einem vCenter, über die
vSphere-REST-Schnittstelle und ohne PowerCLI. **Microsoft 365** liest Postfächer, verifizierte
Domains und gebuchte Lizenzen über Microsoft Graph.

Weil diese drei nur mit einer API sprechen, gibt es **UniFi und Microsoft 365 auch als
Shell-Script** – vom Mac oder von einem Linux-Rechner aus, ganz ohne PowerShell (`curl` und `jq`
genügen). Die Token-Seite bietet beide Fassungen nebeneinander an:

```bash
bash unifi-doku.sh        --controller https://unifi.local --user doku --site "Kunde A"
bash microsoft365-doku.sh --tenant-id "…" --client-id "…"
```

Die Shell-Fassungen **fragen das Kennwort ab**, statt es als Argument zu nehmen – so steht es
weder in der Prozessliste noch in der Shell-History; alternativ lesen sie es aus
`UNIFI_PASSWORD` bzw. `M365_CLIENT_SECRET`.

Ein UniFi-Controller führt oft **mehrere Sites**, ein Agent-Token gehört aber zu genau einem
Kunden – deshalb wird die Site nie geraten. Gibt es nur eine, wird sie genommen; gibt es mehrere,
listet das Script sie auf und hält an, bis eine gewählt ist. `--site` nimmt den internen oder den
angezeigten Namen, `--sites` listet nur auf.

### Was ein Agent nicht tut

- **Fremde Zugangsdaten landen nicht in DokuVault.** vCenter, UniFi und Graph bekommen ihre
  Anmeldung beim Aufruf mitgegeben; ein nur lesendes Konto genügt überall.
- **Nichts wird gelöscht, nichts überschrieben, was von Hand gepflegt ist.** Dienste,
  Zugangsdaten, korrigierte VLAN-Zuordnungen und nachgetragene Felder überstehen jeden Lauf.
- **Der Proxmox-Agent legt keine Betriebssysteme an.** Er meldet, was im Gast in
  `/etc/os-release` steht („debian 12"), und trägt nur ein, was der Katalog schon führt – findet
  sich nichts, bleibt das Feld leer. „Debian 12" und „Debian 13" haben verschiedene Support-Enden;
  ein Sammeleintrag „Linux" hätte gar keins und wäre schlimmer als eine Lücke. Genauso beim
  Windows-Server: Übernommen wird nur eine Rolle, die der Dienstekatalog bereits führt.
- **Jeder Token darf ausschließlich dokumentieren** – bei einem Leak kein weiterer Zugriff.

Die einzige Ausnahme bei den Kennwörtern ist das **WLAN-Kennwort**: Es steht im Klartext in der
Controller-Konfiguration, DokuVault hat eine verschlüsselte Spalte dafür, und in einer
Dokumentation ist es genau das, was man nachschlägt. `--ohne-kennwoerter` schaltet das ab.

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
- **Erfassung** – Erstaufnahme-Assistent (16 geführte Schritte), **installierte Agenten** für
  Windows, Proxmox und Linux (Rollen, Intervall und „Jetzt melden“ je Rechner, Ergebnis jedes
  Laufs, Selbst-Update) sowie Scripte für Proxmox, Hyper-V, VMware, Windows-Server, Windows-AD,
  Windows-Client, UniFi und Microsoft 365; Agent-Token auf eigener Seite verwaltet (anlegen,
  einmalig anzeigen, erneuern, widerrufen)
- **Active Directory** – Benutzer und Gruppen samt Mitgliedschaften, in beide Richtungen: wen eine
  Gruppe enthält, in welchen Gruppen ein Benutzer ist
- **Backup-Überwachung** – Veeam, Windows Server-Sicherung und Proxmox melden jeden Lauf; die letzten
  Läufe je Job auf der Kundenkarte und über alle Kunden, mit einstellbarer Aufbewahrung
- **Statistik** – System (Datenbank, Dateien, Platte, Arbeitsspeicher, Last, Warteschlange),
  API-Auslastung je Stunde und Endpunkt, Datenwachstum je Bereich und Kunde, Zustand der Agenten,
  Anmeldungen und Änderungen; ein nächtlicher Schnappschuss hält den Verlauf
- **Eigene Sicherung** – im Adminbereich eingestellt: Zeitplan, SFTP- oder FTP/FTPS-Ziel mit
  Verbindungstest, Aufbewahrung, Archivpasswort (dann kommt auch die `.env` mit dem `APP_KEY` mit),
  Benachrichtigungsadresse, Download vorhandener Sicherungen und Anleitung zum Wiederherstellen; eine
  Zustandszeile auf dem Dashboard wird gelb oder rot, wenn die Sicherung aus, alt, fehlgeschlagen oder
  nur lokal ist
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
  Aufbewahrung der PDF-Ausgaben; Kennwortregeln, Anmeldesperre und Sitzungsdauer; Vorgaben für
  Agenten (Intervall, ab wann einer als still gilt, Token-Gültigkeit und Höchstlaufzeit); wie lange
  die Statistik aufbewahrt wird; die Bremse für Kennwortabrufe
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
- **Kennwörter nur auf Klick und protokolliert** – kein gespeichertes Kennwort steht im
  ausgelieferten HTML. Jedes angesehene oder kopierte Kennwort steht mit Benutzer und IP im
  Protokoll, der Fernwartungs-Knopf trägt das RustDesk-Kennwort nicht mehr im Link (er läuft über
  DokuVault, das die Verbindung protokolliert), und eine einstellbare Bremse hält auf, wer eine Liste
  Zeile für Zeile abgreift
- **PDF-Ausgaben** enthalten alle Zugangsdaten eines Kunden im Klartext: Ihr Download wird
  protokolliert, und sie werden nach einer einstellbaren Frist automatisch gelöscht
- **Verschlüsselte Sicherungen** – mit Archivpasswort ist die Backup-ZIP AES-256-verschlüsselt und
  enthält auch den `APP_KEY`, ohne den nach einer Wiederherstellung kein gespeichertes Kennwort
  lesbar wäre
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
| **Pakete** | spatie/laravel-activitylog 4.12 *(Audit-Log)* · barryvdh/laravel-dompdf 3.0 *(PDF-Export)* · spatie/laravel-backup 9.3 · league/flysystem-sftp-v3, -ftp *(Backup-Ziele)* |
| **Frontend** | Tailwind CSS 4 · Alpine.js 3 · Flowbite 4 · Vite 6 |
| **Datenbank** | MySQL / MariaDB |
| **Qualität** | Pest 3 *(1394 Tests)* · Laravel Pint · GitHub Actions CI |

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

### DokuVault selbst sichern

Unter **Admin → Einstellungen → Backup**: tägliche Uhrzeit, ein externes SFTP- oder FTP/FTPS-Ziel
(vor dem Speichern getestet), Aufbewahrung und ein **Archivpasswort**. Nur mit Archivpasswort
enthält die Sicherung die `.env` mit dem `APP_KEY` – ohne ihn ist nach einer Wiederherstellung jedes
gespeicherte Kennwort unlesbar. Die Seite listet vorhandene Sicherungen zum Download und erklärt die
Wiederherstellung Schritt für Schritt.

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

1394 Feature-Tests (Pest 3) laufen gegen eine In-Memory-SQLite – keine Einrichtung nötig, keine
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
