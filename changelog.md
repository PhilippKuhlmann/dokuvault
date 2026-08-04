# Changelog

## 26.08.02

### Added

- **Bauform und Einbautiefe beim Server**: 19-Zoll oder Standserver, und ob das Gerät die volle Schranktiefe belegt. Bei einem Standserver entfällt die Tiefe – das Feld erscheint nur beim Rackeinbau. Dazu die Höheneinheiten: Ein 2-HE-Server belegt beim Einbau direkt zwei HE. Nur 19-Zoll-Server erscheinen im Rack-Editor und lassen sich einbauen – auch bei direktem Aufruf. Bestandsserver gelten als 19-Zoll in voller Tiefe. Die Tiefe ist die Vorarbeit für die geplante Rückansicht der Schränke.
- **Docker-Setup**: `docker compose up` startet DokuVault samt MariaDB auf http://localhost:8000, inklusive Demo-Daten. Geseedet wird nur bei leerer Datenbank. Zum Ausprobieren und für kleine Installationen gedacht – ein Container, kein nginx.
- **Patchfelder mit Dosendokumentation**: Je Port Dosennummer, Raum und Ziel-Switch samt Portnummer. Dosennummer und Raum sind getrennte Felder. Enthalten in globaler Suche, Rack-Einbau und PDF-Export. Portanzahl lässt sich erhöhen; verkleinern wird abgelehnt, solange oberhalb dokumentierte Ports liegen. Bestehende Installationen erhalten die vier Berechtigungen per Migration.
- **Vordefinierte Demo-Zugänge sind gesperrt**: `admin`, `techniker`, `kunde-rw` und `kunde-r` lassen sich bei `DEMO_MODE=true` weder ändern noch löschen – auch der Benutzername nicht, denn daran erkennt der Schutz sie. Selbst angelegte Benutzer bleiben frei bearbeitbar.
- **Nutzung der Demo auswerten**: `php artisan demo:stats` zeigt Besuche und Seitenaufrufe gesamt, je Tag, je Tageszeit, je Rolle und je Herkunftsnetz. Kein User-Agent, keine aufgerufenen Seiten. Aufzeichnung als Datei unter `storage/app/demo-usage/`, damit sie den stündlichen Reset übersteht.

### Changed

- **Anleitung zum Aktualisieren** in DEPLOYMENT.md – mit Backup vorab und Wartungsfenster nur um die Migrationen.
- **Deploy schaltet die Demo nur noch für Sekunden ab**: Wartungsmodus erst ab den Migrationen statt über den ganzen Vorgang. Gemessen: 10 statt 17 Sekunden, bei geänderten Abhängigkeiten deutlich mehr. `deploy.sh` und der `demo:reset`-Cronjob teilen sich eine Dateisperre.
- **Tests laufen gegen mehrere PHP-Versionen und gegen MariaDB**: PHP 8.2 und 8.3 je mit SQLite und MariaDB 11; PHP 8.4 läuft mit, blockiert aber nicht.
- **Vertrauenswürdiger Proxy in der Konfiguration** statt fest im Code: `TRUSTED_PROXIES` in der `.env`, mehrere Einträge und CIDR erlaubt. Ohne Eintrag sieht die App die Adresse des Proxys statt die des Besuchers – betrifft auch das Audit-Log.
- **Herkunft in der Demo-Statistik**: standardmäßig auf das Netz gekürzt (/24 bzw. /48), umschaltbar über `DEMO_IP_LOGGING` auf `aus` oder `voll`. `voll` speichert ein personenbezogenes Datum.
- **Ein Gerät bringt seine Höhe mit ins Rack**: Ein 48er-Patchfeld belegt direkt zwei HE, statt dass man sie nachträgt.
- **Die Rack-Zeichnung nutzt die echte Portanzahl** statt fester 24.

### Fixed

- **Kein Umschalter für dunkel/hell auf der Anmeldeseite**: Seiten ohne Navigationsleiste hatten keinen. Das Gast-Layout hat ihn jetzt oben rechts; der Knopf ist eine Komponente statt dreimal kopiert.
- **Seitenfehler auf Seiten ohne Navigationsleiste**: Der Theme-Umschalter griff ungeprüft auf Knopf und Icons zu und brach die Ausführung von `app.js` ab. Steigt jetzt früh aus. Dazu das fehlende Semikolon hinter `Livewire.start()`.

### Internal

- **Abhängigkeiten nachgezogen**: Pint 1.30.3, Debugbar 4.4, Livewire 4.3, Tinker 3.0, `laravel-vite-plugin` 0.8.1, Tailwind 3.4.19, Vite 3.2.11, dazu die JS-Patchgruppe; `actions/checkout` und `actions/setup-node` auf v7.
- **`spatie/laravel-pdf` entfernt** – wurde nirgends benutzt, der PDF-Export läuft über DomPDF.
- **Dependabot**: kleine Aktualisierungen gebündelt je Ecosystem, Major-Sprünge einzeln. `vite`, `laravel-vite-plugin`, `tailwindcss` und `flowbite` sind für Majors ausgenommen – sie hängen voneinander ab und gehören in eine gemeinsame Umstellung.
- **Code-Stil wird geprüft**: Pint einmal über das ganze Repository (293 Dateien, rein mechanisch), CI hält den Stand mit `pint --test`.
- **Vorlagen für Issues und Pull Requests**, `.mailmap`, Tag-Schema `vJJ.MM.TT` in CONTRIBUTING.md.

## 26.08.01

### Added

- **Serverschrank im PDF-Export als Zeichnung**: Die Belegung stand bisher als Aufzählung im Text („U1–U2: USV-01, U4–U5: SRV-DC01 …"). Jetzt steht dort dieselbe Frontansicht wie in der Oberfläche – mit HE-Skala, farbigen Blenden je Gerätetyp und offenen Einschüben. Dafür war `phenx/php-svg-lib` nötig: DomPDF bringt von Haus aus keine SVG-Unterstützung mit und ließ den Platz kommentarlos leer. **Nach dem Deployment `composer install` ausführen.**
- **Gezeichnete Frontansicht neben dem Rack-Schema**: Neben der beschrifteten Belegung steht jetzt eine zweite Ansicht, die den Schrank ohne Beschriftung so zeigt, wie er tatsächlich aussieht – Server mit Laufwerksschächten, Switches mit RJ45-Ports, Patchfelder, Rangierfelder, Blindplatten, Fachböden, Kabeldurchführungen, Steckdosenleisten und USV mit Display. Die Zeichnungen sind SVG und passen sich der Höhe an: ein 2-HE-Server bekommt zwei Reihen Schächte, ein 2-HE-Patchfeld zwei Portreihen. Zu sehen im Rack-Editor (ab 1024 px Breite) und in der Serverschrank-Liste. Welche Zeichnung ein Katalogelement bekommt, wählt der Admin je Eintrag aus – mit sofortiger Vorschau im Formular; bei dokumentierten Geräten ergibt sie sich aus dem Gerätetyp. Die Darstellung wird beim Einbau kopiert, ändert also nachträglich keine bestehende Bestückung.
- **Rack-Katalog im Adminbereich pflegbar**: Die passiven Rack-Elemente (Patchfelder, Fachböden, Blindplatten, Kabeldurchführung, PDU) standen fest im Code und ließen sich nur per Deployment erweitern. Sie liegen jetzt in einer eigenen Tabelle und werden unter „Auswahlmenüs → Rack-Katalog" angelegt, bearbeitet und gelöscht – je Eintrag Bezeichnung, Höheneinheiten und Reihenfolge in der Palette. Die elf bisherigen Einträge legt die Migration selbst an, bestehende Installationen brauchen also nur `php artisan migrate`. Beim Einbau wird die Bezeichnung in die Rack-Dokumentation kopiert: ein später umbenannter oder gelöschter Katalogeintrag verändert keine bestehende Bestückung.

## 26.07.31

### Added

- **Serverschränke mit Drag-&-Drop-Bestückung**: Unter „Netzwerk → Serverschränke" lassen sich Racks anlegen (Standort, Ort, Höheneinheiten) und in einer Frontansicht bestücken. Dokumentierte Geräte (Server, Switch, NAS, Router, USV, Recorder, E-Mail-Archiv) werden aus einer Palette auf freie Höheneinheiten gezogen – Name kommt aus der Doku, jedes Gerät ist nur einmal verbaubar. Dazu ein Katalog passiver Elemente (Patchfelder, Fachböden, Blindplatten, Kabeldurchführung, PDU). Einbauten lassen sich verschieben, in der Höhe ändern (＋/－) und entfernen; Kollisionen und Überstände lehnt der Editor mit Meldung ab. Für Touch/Tastatur gibt es je Palette-Eintrag einen „Einbauen"-Knopf (unterster freier Platz). Die Liste zeigt jede Belegung read-only, der PDF-Export bekommt einen Abschnitt mit Belegungsliste (U1–U2: USV-01 …), Racks landen im Papierkorb (Einbauten überleben die Wiederherstellung) und der Standortfilter wirkt. Bestehende Installationen erhalten die vier neuen Berechtigungen per Migration (Admin/Techniker sofort, andere Rollen über die Rollen-Seite).

### Removed

- **Toten Rack-Ansatz von 2023 entfernt**: Models, leere Controller und API-Routen ohne Oberfläche (darunter ein ungeschützter `GET /api/test`, der Rack-Positionen löschte), ein Seeder mit 27 Katalogeinträgen, deren SVG-Dateien nie im Repo lagen, sowie die zugehörigen Tabellen. Die Tabellen waren über keine Oberfläche erreichbar und enthielten keine Nutzdaten.

### Changed

- **README erweitert**: eigener Abschnitt zum Erstaufnahme-Assistenten (Phasen, Reihenfolge, Fortsetzen), ein Abschnitt „Aufbau", der die vier steuernden Listen in `config/custom.php` erklärt, ausführlicherer Test-Abschnitt und aktualisierte Testzahl (134 → 172).
- **Screenshots neu aufgenommen**: alle sechs bestehenden Bilder zeigten noch den Stand vor Schriftart, Logo, IPAM-Überarbeitung und Dashboard-Umbau. Neu dazu: Erstaufnahme-Assistent und Anmeldeseite – die bisherige `login.png` zeigte tatsächlich die Admin-Übersicht und war nirgends eingebunden.
- **Demo-Daten realistischer**: VLANs hießen nach Personen („Frau Prof. Dr. Antonie Dorn B.Eng."), weil die Factory `fake()->name()` nutzte; jetzt stammen Bezeichnung, VLAN-ID und Netzadresse aus einem festen Pool gängiger Zwecke (VoIP, WLAN-Gast, Drucker …). Computer bekamen zufällig ein Windows-**Server**-Betriebssystem und ein Modell, das nicht zum Hersteller passte (ThinkCentre unter „HP"); beides ist jetzt stimmig.

## 26.07.30

### Added

- **Erstaufnahme-Assistent**: Unter „Sonstiges → Erstaufnahme-Assistent" (und über eine Karte auf dem Dashboard) führt ein Assistent in 16 Schritten durch die Dokumentation eines Kunden – Standort, Ansprechpartner, Internet-Anschluss, Router, VLANs, WLAN, Switches, Accesspoints, Server, VMs, NAS, Computer, Drucker, AD-Domäne, TK-Anlage und Backup. Jeder Eintrag wird **sofort** angelegt, bereits vorhandene Einträge werden je Schritt angezeigt, einzelne Bereiche lassen sich überspringen. Der Fortschritt liegt in der Datenbank und übersteht Logout und Gerätewechsel – ein angefangener Durchlauf kann später fortgesetzt werden. Der einmal gewählte Standort gilt für alle Folgeschritte, und Schritte ohne passende Berechtigung werden übersprungen. Die Reihenfolge ist nicht beliebig: WLAN setzt ein bereits angelegtes VLAN voraus und steht deshalb danach.

### Changed

- **Schriftart auf Space Grotesk umgestellt** (Überschriften in Semibold, Fließtext in Regular), selbst gehostet ohne externe Abhängigkeit. Die Monospace-Darstellung von IP-Adressen, Seriennummern und Schlüsseln bleibt unverändert.
- **Logo als Wortmarke und Favicon**: Die Kopfzeile zeigt neben dem App-Namen jetzt ein Logo, dasselbe Motiv liegt als `logo.svg` und ist als Favicon eingebunden (die App hatte bisher gar keins). Die Login-Seite nutzte ein abweichendes Motiv und ist angeglichen.
- **IPAM überarbeitet**: Jede VLAN-Karte zeigt eine Auslastungsleiste (belegt / DHCP / frei), Gateway-Adressen sind wie DHCP-Bereiche als Kennzeichen markiert statt nur im Fließtext, die Kopfzeile nennt VLAN-Anzahl und belegte Adressen, und die zehnfach wiederholten Tabellenköpfe treten optisch zurück.
- **Dashboard umgestellt**: Ablaufende Lizenzen und Zertifikate stehen jetzt in einer eigenen Zeile über Standorten und Ansprechpartnern und sind exakt zwei Inventar-Kacheln breit.
- **Seitentitel auf allen Listen-Seiten**: Jede Liste zeigt oben ihren Namen, vertikal auf einer Höhe mit dem „Neu"-Button. Der Titel ist jetzt auch für Nutzer ohne Anlage-Recht sichtbar – vorher war die ganze Zeile inklusive Überschrift hinter der Berechtigungsprüfung versteckt. Die Titel stehen in der Mehrzahl („Zertifikate" statt „Zertifikat"), passend zur Seitenleiste.
- **Standortfilter wirkt jetzt auch im IPAM und in der Auto-Dokumentation** – die letzten beiden Listen mit Standortbezug, die ihn ignoriert haben. Beim IPAM werden bewusst nur die angezeigten VLANs gefiltert, **nicht** die darin belegten Adressen: Liegt ein Gerät eines anderen Standorts in diesem Netz, muss es sichtbar bleiben, sonst erschiene eine tatsächlich vergebene Adresse als frei.
- Doppelte Relationen auf `Customer` aufgeräumt: `contactpeople()` war ein unbenutztes Duplikat von `contactpersons()`, bei DECT waren zwei Namen für dieselbe Beziehung in Gebrauch.

### Fixed

- **Dateien und Windows-Lizenzen brachen mit HTTP 500 ab, sobald in der Seitenleiste ein Standort gewählt war**: Der Standortfilter hängte ein `where site_id = ?` an, obwohl diese beiden Tabellen gar keine solche Spalte haben. Beide Listen filtern jetzt nur nach Kunde; zusätzlich prüft der Filter selbst, ob die Tabelle überhaupt einen Standortbezug hat, damit derselbe Fehler nicht wiederkehren kann. (Die Testsuite konnte das nicht finden, weil SQLite als Testdatenbank den unbekannten Spaltennamen als Text auswertet und still eine leere Liste liefert, während MySQL hart abbricht – der Regressionstest prüft deshalb die erzeugte Abfrage statt nur den HTTP-Status.)
- **Klicks in Livewire-Komponenten blieben zeitweise wirkungslos**: Die App startete Alpine.js zweimal – einmal selbst und einmal über Livewire. Die zweite Registrierung brach die Initialisierung ab, bevor `wire:click`/`wire:model` verbunden waren. Betraf alle Seiten mit Livewire (u. a. die IP-Adressen in den Geräte-Formularen und die globale Suche). Nebeneffekt: das JavaScript-Bundle ist rund 43 KB kleiner.
- **„Securepoint UMA" als Überschrift bei der E-Mail-Archivierung**: Seitenleiste, Formulare und PDF-Export nannten den Bereich längst „E-Mail-Archivierung", nur die Überschrift nicht.
- Papierkorb und Auto-Dokumentation hatten oben zu wenig Abstand: Beide nutzten einen Außenabstand, der aus dem Inhaltsbereich herausfiel, wodurch der Inhalt bündig an der Kopfzeile klebte.

## 26.07.25

### Added

- **Auto-Dokumentation für Windows Active Directory**: Der Agent kann jetzt neben Proxmox auch AD-Benutzer und -Gruppen automatisch dokumentieren. Unter „Sonstiges → Auto-Dokumentation" liefert der erzeugte Token jetzt zwei Scripts zum Auswählen (Proxmox / Windows AD). Das PowerShell-Script läuft auf einem Domaincontroller (bzw. Rechner mit RSAT-AD-Modul), liest alle Benutzer sowie **nur selbst angelegte Gruppen** aus (Standard-/Built-in-Gruppen und System-Konten wie Gast/krbtgt werden anhand von SID-RID bzw. `isCriticalSystemObject` bereits am DC herausgefiltert – der eingebaute Administrator bleibt erhalten) und meldet sie per Token an `POST /api/agent/windows-ad`. Passwörter werden nie ausgelesen oder übertragen; wiederholte Läufe aktualisieren bestehende Einträge statt sie zu duplizieren.
- AD-Benutzer haben jetzt die Felder **E-Mail** und **Status** (Aktiv/Deaktiviert) – erfassbar manuell im Formular, automatisch durch den AD-Agent, sichtbar in Liste und PDF-Export.

### Changed

- **Demo-Kunden reduziert und angereichert**: Statt 10 spärlich befüllter Zufallskunden erzeugt der Seeder jetzt **5 zusätzliche Demo-Kunden** (neben „Mustermann") – jeweils mit Standorten, Ansprechpartnern, Netzwerk/VLAN, Router, Switches, Access Points, WLAN, Servern, VMs, Computern, Druckern, AD-Benutzern/-Gruppen, Software-Lizenzen und UTM-Firewalls, statt nur Server/VM/UTM.

### Fixed

- Beim Bearbeiten eines AD-Benutzers ohne Passwort (z. B. per Agent importiert) ließ sich das Formular wegen der Pflichtfeld-Validierung für „Passwort" nicht speichern. Das Feld ist jetzt optional.
- **„Kopieren"-Button bei den Auto-Dokumentations-Scripts ohne Funktion**: Die Buttons riefen direkt `navigator.clipboard.writeText()` auf, das nur in einem sicheren Kontext (HTTPS bzw. `localhost`) verfügbar ist – auf einer lokalen `.test`-Domain über HTTP schlug der Aufruf lautlos fehl. Umgestellt auf den bereits im Projekt vorhandenen `copyText()`-Helfer (mit Fallback für unsichere Kontexte), der auch bei den Passwort-/IP-Kopier-Buttons zum Einsatz kommt.
- **Techniker landete nach dem Login ohne Navigation**: Da Techniker (und andere Nutzer ohne festen Kunden) keinen `customer_slug` besitzen, führte der Login-Redirect ins Leere und bounct nur zufällig auf die Kundensuche zurück – dort fehlte zudem jeglicher Link zur globalen Suche. Der Login leitet jetzt anhand von `hasCustomer()` direkt und zuverlässig weiter (Kunde → eigenes Dashboard, alle anderen → Kundensuche), und die schlanke Navigation auf der Kundensuche zeigt jetzt wie gewohnt Kundensuche, Globale Suche, UTM- und Rustdesk-Suche.

## 26.07.23

### Fixed

- **Produktiv-Installation ohne Dev-Pakete möglich**: Die Startdaten-Seeder (Admin, Rollen/Rechte, Betriebssysteme, Mail-Anbieter) nutzten `::factory()` und damit `fakerphp/faker` – eine Dev-Abhängigkeit. Bei `composer install --no-dev` schlug das Seeding mit „Call to undefined function fake()" fehl. Diese Seeder legen ihre festen Referenzdaten jetzt direkt per `create()`/`forceCreate()` an (kein Faker nötig). README um getrennte Demo-/Produktiv-Installationsanleitung ergänzt.

### Added

- **Admin-Dashboard mit echten Kennzahlen**: statt nur vier Zähl-Kacheln jetzt eine globale **Inventar-Statistik** (dokumentierte Objekte je Typ über alle Kunden), eine **globale Ablauf-Übersicht** (Lizenzen, Zertifikate & Domains, die demnächst ablaufen – kundenübergreifend, mit Link), ein **Feed der letzten Aktivitäten**, **Top-Kunden nach Geräteanzahl** und ein **14-Tage-Aktivitätsdiagramm**.

### Changed

- **Rollen-Berechtigungen als Matrix**: Im Rollen-Formular (Anlegen/Bearbeiten) sind die ~160 Rechte jetzt als sortierte Tabelle dargestellt – eine Zeile je Bereich, Spalten *Sehen · Erstellen · Bearbeiten · Löschen* – statt als lange, unsortierte Checkbox-Liste. Mit „Alle auswählen" und Klick auf den Bereichsnamen zum Umschalten der ganzen Zeile. Sonderrechte stehen gesammelt darunter.
- Admin-Kopfzeile zeigt jetzt den App-Namen (statt „Admin - Dokumentation").
- **Startdaten erweitert**: Betriebssystem-Liste ergänzt (Ubuntu Server LTS, Windows Server 2025, VMware ESXi, Windows 10 Enterprise/LTSC, Rocky/AlmaLinux, Synology DSM, TrueNAS, macOS …) und Duplikat „Windows 10 Pro" korrigiert. Mail-Anbieter um Microsoft 365, Google Workspace, Telekom/T-Online, GMX, Web.de, mailbox.org, Posteo und Vodafone ergänzt.

## 26.07.21

### Added

- **Auto-Dokumentation (Agent) – Proxmox**: Geräte können sich per Script selbst dokumentieren. Unter „Sonstiges → Auto-Dokumentation" (Admin/Techniker) wird ein an einen Standort gebundener **Agent-Token** erzeugt und ein fertiges **Proxmox-Bash-Script** zum Download angeboten. Auf dem Proxmox-Host ausgeführt, meldet es Host-Hardware, Version, Storage sowie alle VMs/LXC-Container an die API (`POST /api/agent/proxmox`). Der Host wird als Server, die Gäste als VMs (mit Host-Verknüpfung) angelegt bzw. aktualisiert – wiederholte Läufe erzeugen keine Duplikate. Der Token darf ausschließlich dokumentieren und nur für seinen Kunden/Standort.

### Fixed

- **Auto-Doku: 500 bei größeren Proxmox-Hosts behoben** – das `services`-Feld war auf 255 Zeichen begrenzt (VARCHAR); längere Angaben (Version, CPU, RAM, mehrere Storages) führten auf MySQL zu „Data too long". Feld auf TEXT erweitert (Server + VMs).

### Removed

- **„Funk" (Funkzentrale) entfernt**: Der komplette Bereich inkl. Menü, Formularen, PDF-Abschnitt, Route, Berechtigungen und Datenbanktabelle wurde entfernt.

### Changed

- **Projekt umbenannt in „DokuVault"**: Anzeigename (APP_NAME), README, Screenshots, composer-/npm-Paketname und interne Verweise. Funktionalität unverändert.

### Internal

- **Upgrade auf Laravel 12** (von Laravel 10) inkl. Sanctum 4, Livewire 3.8, Pest 3/PHPUnit 11, dompdf 3, spatie-Pakete (backup 9, pdf 1.9). Klassische App-Struktur beibehalten; PSR-4-Namespace der API-Controller korrigiert. Alle 134 Tests grün.

### Changed

- **E-Mail-Archivierung statt „Securepoint UMA"**: Der Bereich unter E-Mail heißt jetzt „E-Mail-Archivierung" und hat ein Feld **Hersteller / Produkt** (z. B. Reddoxx, Securepoint UMA). So lassen sich verschiedene Archiv-/Mail-Security-Produkte an einem Ort dokumentieren, ohne eigenes Menü. Bestehende Einträge bleiben erhalten.
- **Accesspoint: Raum-Zuordnung entfernt** – das ungenutzte Feld „Room ID" wurde entfernt (Standort bleibt).
- **Telefonanlage: IP 2 und IP 3 entfernt** – zusätzliche IPs werden jetzt über „Weitere IP-Adressen" dokumentiert (wie bei anderen Geräten). Formular, Anzeige, globale Suche, IPAM und PDF-Export entsprechend bereinigt.

## 26.07.20

### Added

- **Passwort kopieren**: Neben dem Auge zum Anzeigen gibt es jetzt einen Kopier-Button, der das Passwort in die Zwischenablage legt (kurzes grünes Häkchen als Bestätigung) — in Listen und Detail-Karten. Mit Fallback für unsichere Kontexte.
- **Kopieren von IP, MAC & Seriennummer**: Auch diese Felder haben in den Detail-Karten jetzt einen Kopier-Button (z. B. IP direkt für RDP/SSH übernehmen).
- **Leerzustände**: Leere Listen zeigen jetzt einen freundlichen Hinweis „Noch keine Einträge vorhanden" statt einer leeren Seite.
- **SSL/TLS-Zertifikate** (Dienste → Zertifikate): Verwaltung von Zertifikaten mit Bezeichnung, Domain/CN, Aussteller, Typ, Ausstell- und Ablaufdatum. Bald ablaufende oder abgelaufene Zertifikate erscheinen als Warnung auf dem Kunden-Dashboard (analog zu ablaufenden Lizenzen), sind über die globale Suche auffindbar und im PDF-Export enthalten.

### Changed

- **Mobile Darstellung verbessert**: Listen-Tabellen (AD-User, Lizenzen, Logins, WLAN, Dateien … und IPAM) sind auf schmalen Bildschirmen jetzt horizontal scrollbar statt abgeschnitten. Formular-Doppelfelder stapeln auf dem Smartphone untereinander statt gequetscht nebeneinander. Detail-Karten nutzen auf kleinen Displays die volle Breite.

### Changed

- **Lösch-Bestätigung**: Vor jedem Löschen (Löschen-Karte und Löschen-Button in Listen) erscheint jetzt eine Sicherheitsabfrage.

### Fixed

- **Abstürze bei Objekten im Papierkorb behoben**: Wurde ein referenziertes Objekt in den Papierkorb verschoben, stürzten abhängige Seiten ab, weil die Beziehung dann leer ist. Betroffen und jetzt null-sicher (mit „—"-Anzeige): Betriebssystem → Server/VM/Computer/Windows-Lizenz (Liste & Bearbeiten); Netzwerk → WLAN; NAS → NAS-Logins; Recorder → Recorder-Logins; Standort → Funkzentrale; Mail-Anbieter → Postfächer; außerdem Standort/Kunde in der UTM-Suche und Rolle in der Admin-Benutzerliste.
- **Auth-Seiten repariert**: Passwort-Zurücksetzen, Registrierung, E-Mail-Bestätigung und Passwort-Bestätigen stürzten beim Aufruf ab (verwiesen auf beim Logo-Umbau entfernte Breeze-Komponenten). Die fehlenden Komponenten wurden wiederhergestellt; alle Auth-Seiten laden wieder.
- Passwort-Feld in Listen/Detail-Karten hob sich beim Überfahren mit der Maus als andersfarbiger Kasten ab (fester weißer Hintergrund statt der Zeilenfarbe) — Hintergrund jetzt transparent, das Feld fügt sich in Normal- und Hover-Zustand nahtlos ein.

## 26.07.19

### Added

- **IPAM (IP-Adressverwaltung) je VLAN** (Netzwerk → IPAM): listet alle Adressen eines Subnetzes auf — belegte Adressen mit Gerätename, freie Bereiche zusammengefasst (z. B. „192.168.1.2 – 192.168.1.9 frei"), Gateway und DHCP-Bereich markiert
- **Mehrere IP-Adressen je Gerät**: Geräte (Router, Firewalls, Switches, Server … alle Typen) können zusätzliche IP-Adressen bekommen — optional je einem VLAN zugeordnet. So erscheint z. B. ein Router im IPAM in jedem VLAN, in dem er Gateway ist. Bearbeitbar direkt auf der Geräte-Bearbeiten-Seite unter „Weitere IP-Adressen". Bei Auswahl eines VLANs wird das IP-Feld automatisch mit dem Netz-Präfix vorbefüllt (nur das letzte Oktett muss noch eingegeben werden).
- **Aktivitätsprotokoll**: Alle Änderungen (Anlegen/Ändern/Löschen/Wiederherstellen) werden mit Benutzer und Zeitpunkt protokolliert — einsehbar im Admin-Bereich unter „Protokoll → Aktivitäten". Passwörter werden niemals protokolliert.
- **Papierkorb**: Gelöschte Objekte können pro Kunde eingesehen und wiederhergestellt werden (Sonstiges → Papierkorb, für Admin/Techniker)
- **Globale Suche**: Suche über alle Gerätetypen nach Name, IP, Seriennummer oder MAC (Icon in der Top-Navigation)
- **Admin-Dashboard** mit Statistik-Kacheln (Benutzer, Kunden, Rollen, Aktivitäten)
- CI (GitHub Actions): Tests laufen automatisch bei jedem Push
- Community-Dateien: CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md, Pint-Konfiguration

### Changed

- Sessions werden jetzt verschlüsselt gespeichert und Cookies in Produktion nur über HTTPS gesendet — **alle Nutzer müssen sich nach dem Update einmal neu anmelden**
- In Produktion werden URLs automatisch auf HTTPS erzwungen

### Fixed

- Sicherheit: Stored-XSS über Dateinamen in der Software-Lizenz-Liste behoben
- Sicherheit: Unescaptes Rendern von Passwörtern in Bearbeiten-Formularen entfernt (Umstellung auf gebundene Attribute); behebt zugleich mögliches Doppel-Escaping von Sonderzeichen

### Internal

- Tote Blade-Komponenten entfernt (deleteFrage/, link-old, table/row)
- Testabdeckung ausgebaut: 112 Tests (Sicherheit/XSS-Roundtrip, Audit-Log, Papierkorb inkl. IDOR, globale Suche, Admin-Dashboard)

## 26.07.18

### Added

- Neue Dokumentations-Bereiche: **Backup**, **Internet/WAN-Anschluss**, **Registrierte Domains** (mit Ablaufdatum) und **USV**
- VMs können jetzt ihrem **Host-Server** zugeordnet werden
- Dashboard: Inventar-Übersicht (klickbare Zähl-Kacheln je Gerätetyp) und Warnliste für ablaufende Software-Lizenzen
- PDF-Export komplett überarbeitet: neues Design und Farben (passend zur App) und jetzt **alle** Objekttypen enthalten (statt bisher nur ~10)

### Changed

- Überarbeitetes UI: neue Farbpalette, einheitliche Karten, Buttons und Tabellen (hell & dunkel)
- Standort-Filter filtert direkt bei Auswahl (ohne separaten Button)
- Listen werden paginiert und laden schneller (Eager Loading, feste Sortierung)
- Login- und Kundensuche-Seite modernisiert inkl. Passwort-Anzeigen-Funktion
- Datenbank-Index auf `computers.operating_system_id` für schnellere Joins

### Fixed

- PDF hat jetzt Druckränder (funktioniert auch bei Druckern ohne Randlosdruck)
- Sicherheit: Standort/Netzwerk eines fremden Kunden kann nicht mehr zugewiesen werden (IDOR)
- Standortfilter: ein gespeicherter Standort eines anderen Kunden führte zu leeren Listen — wird jetzt ignoriert
- Dark-Mode auf der Login-Seite

### Internal

- Testabdeckung ausgebaut: CRUD-Lebenszyklus (Computer), Credential-Verschlüsselung (NAS), Standort-Scoping/IDOR
- Demo-Daten (Mustermann) vollständig & realistisch: alle Objekttypen befüllt, zusammengehörige Hersteller/Modelle, deutsche Namen/Adressen (faker de_DE), VMs mit Host, ablaufende Lizenzen

## 24.07.12

### Changed

- DHCP Start DHCP End muss jetzt eine IP und keine Zahl mehr sein

### Fixed

- NAS Port konnte nicht richtig eingegeben werden



## 24.06.17

### Fixed

- Bug Fixes



## 24.01.28

### Changed

- Livewire Version 2 -> 3



## 24.01.20

### Added

- Funk mit Funkzentrale



## 24.01.12

### Changed

- Migration from Unit Test to Pest
- Man konnte einen Benutzer nur bearbeiten indem man ein neues Passwort vergibt



## 24.01.11

### Fixed

- Wenn NAS gelöscht wurde war die Loginseite nicht mehr aufrufbar
- Drucker konnten nicht bearbeitet werden
- Dateien Konnten nicht runtergeladen werden


## 24.01.06

### Added

- Lizenzen haben Ein Start und Enddatum

### Fixed

- Lizenzen lassen sich wieder runterladen



## 23.11.18

### Changed

- Routen verkürzt



## 23.11.04

### Added

- Switche
- Accesspoints

### Changed

- Bei einigen Objekten Type gegen Modell getauscht. 



## 23.10.31

### Added

- Mail Anbieter im Admin bereich

### Changed

- Etage zum Raum hinzugefügt



## 23.10.30

### Added

- Backuptool

### Changed

- Es können jetzt mehr Objekte versteckt werden



## 23.10.28

### Changed

- PDF Desing



## 23.10.28

### Added

- API-Schnitstelle



## 23.10.26

### Added

- Sonstige Clients



## 23.10.25

### Added

- Maschinen
- Zusätzliche Logins für Recorder



## 23.10.22

### Added

- Datei Upload für Windows Lizenzen
- Datei Upload für CAL Lizenzen
- Datei Upload für Software Lizenzen

### Changed

- Desing anpassung



## 23.10.21

### Added

- IoT Geräte

### Changed

- Seeder erweitert
- Wifi mit Netzwerk verbunden

### Fixed

- Datein Datei Upload



## 23.10.18

### Added

- Permission Tests

### Changed

- Rustdesk Logo
- UTM Logo
- Pakte Updates



## 23.10.14

### Added

- Title der Seite Dynamisch gemacht



## 23.10.10

### Added

- Recht zum erstellen von der PDF Dokumentation
- Zugriffs Lizenzen 

### Fixed

- Im Seitenmenü wurde der Hauptmenü Punkt nur angezeigt wenn man auf alle unterpunkte zugriff hatte



## 23.10.08

### Added

- AD-User können jetzt versteckt werden und nur von Benutzern mit der Permission see_hidden gesehen werden



## 23.10.07

### Added

- Neues Rolen- und Zugriffs- System!!!



## 23.10.03

### Added

- Zentrale Suche für Remoteverbindungen von Servern und VMs
- Changelog öffentlich Sichtbar und automatisiert

### Fixed

- 1+N Fehler bei der Zentralen UTM Suche

### Changed

- Remote Verbindung Logo geändert



## 23.10.02

### Added

- Login Tests
- Permission Tests
- Redirection Tests
- Mange Users from Admin
- Mange Operating Systems from Admin



## 23.10.01

### Added

- Zentrale suche nach UTMs
- UTM Externer Link
- Middelware isTechniker

### Changed

- Admin Bereich
- Top Navigation



## 23.09.27

### Changed

- Admin Bereich



## 23.09.26

### Changed

- Desing Profile Page



## 23.09.19

### Added

- USC-PIN zur Securepoin UTM hinzugefügt
- DECT
- Lizenzen Windows
- Lizenzen Software

### Changed

- Desing

### Fixed

- Phone and PhoneSystem passwort encrypted



## 23.09.09

### Changed

- Desing
- Update dependincies



## 23.08.20

### Added

- Custom Font

### Changed

- Light Desing

### Fixed

- CIDR not shown in edit



## 23.08.20

### Added

- Filter isCustomer behinalttet isCustomerR und is CustomerRW

### Changed

- Admin desing Update

## Fixed

- Erstellen einer neuen VM war nicht möglich
- Kunden haben nichts mehr gesehen bei Geräten die einer Site zugeordnet waren
- Suchleiste für Kunden ausgeblendet
- Kunde konnte sein Profiel nicht bearbeiten



## 23.08.14

### Added

- Ansprechpartner

### Changed

- Add ContactPerson to Dashboard
- Change Hover Color Buttons 


## 23.08.13

### Changed

- Layout der PDF Doku
- Site has now City ZIP Street
- Daschboard shows Sites



## 23.08.12

### Added

- Standort Server
- Standort VM
- Standort NAS
- Standort Drucker
- Standort Telefonanlage
- Standort Telefone
- Menü Kamera
- Kamera + Standort
- Recorder + Standort

### Changed

- Desing Files



## 23.08.08

### Added

- Menü Kunde
- Standort des Kunden



## 23.08.06

### Added

- Router
- Login Allgemein

### Changed

- UTM Standortfähig gemacht
- WLAN Standortfähig gemacht



## 23.08.05

### Added

- Standort

### Changed

- Code angepasst, verkürzt
- Benötigte Felder angepasst
- Tabellen umbennant


## 23.07.30

### Added

- Menü Dienste
- FTP-Server
- DynDNS

### Changed

- Datenbank Tabelle umbenannt


## 23.07.29

### Added

- NAS
- Zusätzliche Logins für NAS

### Changed

- Logo für Login



## 23.07.28

### Added

- Profile page
