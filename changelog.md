# Changelog

## 26.08.15

### Changed

- **Die VLAN-Nummer steht jetzt an der IP-Adresse**: In der Übersicht stand unter der Adresse nur der Netzname („Server & Management"), im Formular dasselbe. Beides zusammen ist das, was man braucht — der Name sagt wofür, die Nummer braucht man am Switch: „Server & Management · VLAN 30". Fehlt eines von beiden, bleibt das andere stehen; heißt die Bezeichnung schon wie das Netz, bleibt nur die Nummer. Gilt für alle 18 Listen und den IP-Block samt Auswahlliste. Die IPAM-Ansicht zeigte beides schon.
- **Alle 19 Geräteformulare auf dasselbe Muster gezogen** (Accesspoint, Kamera, Computer, DECT, IoT, Maschine, NAS, Switch, Sonstige Clients, Telefon, TK-Anlage, Drucker, Recorder, Router, UMA, UTM, USV, VM, Server): Abschnitte nach Identität, Hardware, Zugang, Fernwartung, Diensten und Notizen, zweispaltig auf 1024 px, „← Zurück" oben, IP-Adressen und Zugangsdaten in derselben Karte. 36 Formulare, alle mit einem Rauchtest über sämtliche Anlegen- und Bearbeiten-Routen abgesichert.
- **VLAN anlegen, ohne das Formular zu verlassen**: Im Block „Weitere IP-Adressen" steht neben der VLAN-Auswahl ein „+ Neues VLAN". Das Modal fragt Bezeichnung, VLAN-ID, Netz und Subnetzmaske ab — Gateway, DNS und DHCP trägt man später im richtigen VLAN-Formular nach. Nach dem Anlegen ist das neue Netz gleich ausgewählt, man macht also dort weiter, wo man war. Vorher kostete ein fehlendes VLAN den Weg aus dem Geräteformular heraus und die halb ausgefüllte Zeile.
- **Suchbegriff in der URL über `#[Url]`**: Die vier Suchkomponenten deklarierten ihn über `$queryString`. Verhalten identisch (`?search=…`), aber die Angabe steht jetzt an der Eigenschaft statt in einer separaten Zeile darunter — ein Test hält fest, dass geteilte Links auf ein Suchergebnis weiter funktionieren.
- **Die globale Suche ist einzeln prüfbar**: Die Trefferlogik über 24 Objekttypen stand inline in `render()` und war nur zusammen mit der View testbar. Sie ist jetzt eine `#[Computed]`-Eigenschaft — dieselbe Ausgabe, aber abrufbar ohne zu rendern.
- **Kennungen der Livewire-Bloecke gegen Manipulation gesperrt**: In den Bausteinen für IP-Adressen, Zugangsdaten und Patchfeld-Ports lagen Geräteklasse, Geräte-ID und Kundennummer als offene Eigenschaften — der Browser konnte sie umbiegen, und erst die Prüfung in der Aktion fing das ab. Mit Livewires `#[Locked]` weist das Framework die Änderung ab, bevor überhaupt eine Aktion läuft. Die bisherigen Prüfungen bleiben als zweite Verteidigungslinie stehen.
- **Zwischen den Schritten springen**: Die Abschnitte der Fortschrittsleiste sind anklickbar und zeigen beim Überfahren Name und Stand des Schritts („Internet-Anschlüsse — erfasst"). Man merkt oft erst drei Schritte weiter, dass etwas fehlt. Der Sprung vermerkt den verlassenen Schritt weder als erledigt noch als übersprungen, und Schritte ohne Anlege-Recht sind nicht erreichbar — der Schlüssel kommt vom Client.
- **Der Assistent zeigt alles, was schon da ist**: „Schon erfasst" listete nur Einträge am Standort des laufenden Durchlaufs — vorhandene Geräte anderer Standorte blieben unsichtbar, obwohl sie längst dokumentiert waren. Jetzt steht dort alles, was der Kunde hat. Der Assistent ist außerdem mittig im Inhaltsbereich statt linksbündig.
- **Schon erfasste Einträge sind anklickbar**: Die Kacheln im Assistenten führen auf das Bearbeiten-Formular des Eintrags — zum Nachtragen dessen, was der Assistent nicht abfragt. Der Link öffnet einen neuen Tab, damit der angefangene Durchlauf nicht verloren geht.
- **Assistent optisch aufgeräumt**: „Bereits erfasst" war eine Zeilenliste mit Trennstrichen mitten im Ablauf — jetzt eine abgesetzte Fläche mit Kacheln, die man überfliegt statt Zeile für Zeile liest. Beim Standort-Schritt entfällt sie ganz: Dieselben Einträge standen schon in der Auswahl „Vorhandenen Standort verwenden" darüber. Im Fortschrittsbalken trägt jeder Abschnitt den Namen seines Schritts als Titel, und der aktuelle ist doppelt so hoch — vorher waren es sechzehn namenlose Striche.
- **Im Assistenten fehlten reihenweise Felder** — was man dort nicht erfassen konnte, musste man hinterher im Formular nachtragen. 52 Felder ergänzt, verteilt auf 13 Schritte: Der Internetanschluss führte vier von dreizehn Feldern (ohne Vertragsnummer, Hotline, Subnetz, PPPoE), beim Server fehlten BMC und Rustdesk-Zugang, bei Switch, Accesspoint und TK-Anlage Seriennummer, Benutzer, Passwort und Port, beim Netzwerk DNS und DHCP-Bereich, bei Router, NAS und Drucker Hersteller, Modell und Seriennummer, beim Backup Quelle, Aufbewahrung und letzter Erfolg. Der Standort bleibt bewusst draußen — den setzt der Durchlauf selbst.
- **Dosen durchnummerieren statt 24-mal tippen**: Im Patchfeld trägt man die erste Dosennummer ein (`1.01`) und der Knopf zählt für die folgenden Ports hoch — `1.02`, `1.03` und so weiter. Führende Nullen und das Präfix bleiben erhalten, egal ob `1.01`, `A-07` oder `EG12`. Bereits ausgefüllte Felder werden **nicht** überschrieben: Eine abweichend beschriftete Dose an Port 10 bleibt stehen. Gefüllt wird nur das Formular, geschrieben wird erst beim Speichern. Daneben steht „Dosen leeren" für den Fall, dass man sich beim ersten Feld vertippt hat — es räumt alle Dosennummern ab, lässt Raum, Switch und Notiz stehen und wirkt ebenfalls erst mit dem Speichern, ein Fehlklick ist also folgenlos.
- **Bandbreiten ohne „Mbit/s" tippen**: Download und Upload sind Zahlenfelder mit fest danebenstehender Einheit — man trägt `250` ein, in der Liste und im PDF steht `250 Mbit/s`. Gespeichert wird nur die Zahl; Altbestand wie „1000 Mbit/s" wird beim Lesen und beim nächsten Speichern auf die Zahl zurückgeführt.
- **Einwahldaten am Internetanschluss** (PPPoE): Dafür gab es bisher keine Stelle — wer Benutzer und Kennwort festhalten wollte, schrieb sie in die Notizen, wo sie unverschlüsselt liegen und in der Suche auftauchen. Jetzt zwei eigene Felder im Formular; das Kennwort wird verschlüsselt abgelegt wie bei Router, NAS und den übrigen Geräten. Liste und PDF zeigen den Block nur, wenn er gepflegt ist.
- **Die IP-Spalten sind aus der Datenbank verschwunden.** `ip`, `ip1` und `ip2` gab es an 19 Gerätetabellen; die Formulare führten sie schon nicht mehr, gelesen wurden sie aber weiter. Jetzt sind sie ganz weg — Adressen stehen ausschließlich in `ip_addresses`, wo Netz und Bezeichnung dranhängen. Die Spalten sind aus den ursprünglichen Migrationen entfernt statt per Nachtrags-Migration: Die Doku ist noch nirgends produktiv im Einsatz, es muss nichts überführt werden.
- **Der Assistent trug die IP noch in die alte Spalte ein.** Er hat sein Feld „IP-Adresse" behalten, legt daraus aber einen Eintrag im Block „Weitere IP-Adressen" an. Dasselbe beim AutoDoc-Agenten: Der meldet Hosts und VMs wiederholt, deshalb `updateOrCreate` statt `create` — sonst stünden dort nach einer Woche sieben gleiche Zeilen, und gepflegte Angaben wie Netz oder Bezeichnung blieben auf der Strecke.
- **Mitgezogen**: IP-Plan, globale Suche, PDF-Export, alle Listenkarten, 19 Request-Klassen, 19 Factories und die Demo-Daten lesen bzw. schreiben keine IP-Spalte mehr. Im PDF zeigte die FTP-Zeile „Host" ohnehin auf ein `ip`-Feld, das es dort nie gab — die Tabelle heißt `host`.
- **Dienste werden ausgewählt statt getippt.** Das Feld war ein Textfeld, in das man die Dienste mit Kommas eintrug — was im Katalog steht, sah man dabei nicht. Jetzt stehen die gewählten Dienste als farbige Kacheln da, darunter der Katalog aus der Administration zum Anklicken, darunter ein Feld für alles, was dort (noch) nicht steht. Bereits gewählte verschwinden aus der Auswahl, und Groß-/Kleinschreibung macht keinen zweiten Dienst daraus. Gespeichert wird unverändert eine Komma-Liste, die Spalte am Gerät bleibt Freitext — es braucht keine Datenmigration.
- **Dienste haben eine Beschreibung.** „DFS" oder „RDS" erklärt sich nur dem, der es schon kennt. Sie wird in der Administration gepflegt und erscheint beim Überfahren — an der Kachel in den Listen und am Katalogknopf im Formular, also genau dort, wo man beim Auswählen wissen will, was der Name bedeutet. Kein `title`-Attribut: Der Browser-Tooltip kommt erst nach einer Sekunde und lässt sich nicht gestalten. Das Fenster hängt am Viewport statt am Element, sonst schneiden der Spaltensatz der Karten und die Scrollbereiche es ab — dieselbe Lösung wie bei den Buchsen der Patchfelder. Der Demo-Katalog bringt für alle zwölf Dienste eine Beschreibung mit.
- **Beim Anlegen steht jetzt dabei, warum IP-Adressen und Zugangsdaten fehlen.** Beide hängen am gespeicherten Objekt und erscheinen erst im Bearbeiten-Formular; der Abschnitt „IP-Adressen und Zugangsdaten — Lassen sich eintragen, sobald das Gerät angelegt ist." sagt das in allen 19 Anlegen-Formularen, statt eine Lücke zu lassen.
- **Die IP-Felder sind aus allen Geräteformularen verschwunden.** Adressen werden nur noch im Block „Weitere IP-Adressen" gepflegt, wo Netz und Bezeichnung dranhängen. Die Spalten `ip`/`ip1`/`ip2` bleiben in der Datenbank — Altbestand und der AutoDoc-Agent schreiben sie weiter, und ein Speichern ohne die Felder lässt vorhandene Werte stehen. Neun Request-Klassen verlangten die IP bisher als Pflichtfeld (Router, Accesspoint, Switch, UTM, UMA, Maschine, Recorder, Kamera, NAS); die Regel ist auf `nullable` gelockert. Alle Listenkarten zeigen die erste dokumentierte Adresse, wenn die Spalte leer ist.
- **Server-Formular in Abschnitte gegliedert**: Anlegen und Bearbeiten sortieren die Felder nach Identität, Hardware, Fernwartung und Diensten und setzen sie zweispaltig auf 1024 statt 768 px Breite. Der Name steht an erster Stelle. Kurze Felder stehen nebeneinander statt untereinander; BMC-IP und Dienste laufen über die volle Breite. Unter 640 px bleibt es einspaltig.
- **Weitere IP-Adressen und Zugangsdaten stehen in derselben Karte** wie das Formular, direkt unter den Abschnitten statt als zwei lose Karten darunter. Ein gemeinsames Speichern ist damit nicht verbunden: HTML erlaubt keine verschachtelten Formulare, und beide sind eigenständige Livewire-Komponenten — deshalb der Hinweis „speichert sofort" an beiden.
- **IP 1 und IP 2 sind aus dem Server-Formular verschwunden.** Adressen werden nur noch im Block „Weitere IP-Adressen" gepflegt, wo Netz und Bezeichnung dranhängen. Die Spalten bleiben in der Datenbank: Altbestand und der AutoDoc-Agent schreiben sie weiter, und ein Speichern ohne die Felder lässt vorhandene Werte stehen. Zwei Folgen davon sind mitgezogen — die Listenkarte zeigt die erste dokumentierte Adresse, wenn `ip1` leer ist, und die globale Suche durchsucht jetzt auch die Adressen aus dem IP-Block (für alle Gerätetypen, nicht nur Server).
- **Die drei Speichern-Stellen sind auseinanderzuhalten**: Der Knopf am Formularende heißt „Stammdaten speichern" — er hieß vorher „Speichern" und ließ glauben, er sichere auch die zwei Karten darunter. Weitere IP-Adressen und Zugangsdaten tragen jetzt den Hinweis „speichert sofort". Das gilt in allen 19 Bearbeiten-Ansichten, die diese Karten einbinden.
- **Beim Anlegen fehlten IP-Adressen und Zugangsdaten kommentarlos.** Beide hängen am gespeicherten Gerät und stehen deshalb erst im Bearbeiten-Formular. Das Anlegen-Formular sagt das jetzt, statt die Lücke zu lassen.

### Added

- Demo-Daten: zwei weitere Serverschränke (Etagenverteiler 1. OG und Technikraum Filiale, je 12 HE) und insgesamt fünf Patchfelder — zwei im großen Schrank, zwei im Etagenverteiler (darunter ein 48er auf 2 HE), eines in der Filiale. Damit sind Gruppierung nach Schrank und die zweireihige Blende im Datensatz sichtbar.
- **Patchfelder nach Schrank gruppiert**, mit Blende auch im Bearbeiten-Formular: Beim Pflegen sieht man direkt, welche Buchse man beschriftet. Ein Hover über einer belegten Buchse zeigt Dose, Raum, Switch, Switch-Port und Notiz; die Dosentabelle darunter klappt man bei Bedarf auf.
- **Patchfelder grafisch**: Die Liste zeigt die Blende mit allen Buchsen — eine Reihe je Höheneinheit, so wie das Gerät gebaut ist (24 Ports auf 1 HE nebeneinander, ein 48er auf 2 HE in zwei Reihen), mit der Lücke nach je sechs Buchsen. Belegte Ports sind gefüllt, freie leer; der Tooltip nennt Dose, Raum und Ziel-Switch. Darunter weiterhin die Tabelle mit allen Angaben.
- **EOL-Übersicht nach Kunde** (Administration → Betriebssysteme): Alle Geräte, deren Betriebssystem aus dem Support läuft, nach Kunde gruppiert — je Kunde die Anzahl ohne Support, je Gerät Typ, System und Datum. Die Gerätenamen verlinken in die Liste des Kunden. Oben zwei Kennzahlen über alle Kunden. Kunden mit den meisten abgelaufenen Systemen stehen oben.
- **Support-Ende (EOL) am Betriebssystem**: In der Administration lässt sich je Betriebssystem das Support-Ende pflegen. Server, VMs und Computer, die darauf laufen, bekommen ein Abzeichen neben dem Namen — rot, sobald es keine Sicherheitsupdates mehr gibt, bernstein im letzten halben Jahr davor. Ohne gepflegtes Datum erscheint nichts. Das Admin-Dashboard listet betroffene Systeme unter „Läuft demnächst ab", aber nur solche, auf denen tatsächlich Geräte laufen. Die bekannten Termine (Windows Server 2012 R2 bis 2025, Debian, Ubuntu, CentOS) sind im Demo-Datensatz hinterlegt.

### Fixed

- **Aus einem Formular kam man nur über das Formularende zurück.** „Abbrechen" steht unten neben Speichern — wer nichts ändern will, musste erst durch das ganze Formular scrollen. Über der Überschrift steht jetzt ein „← Zurück" auf die Liste, in allen Formularen.
- **Die Knöpfe „Hinzufügen" und „Verknüpfen" standen 6 px niedriger als die Eingabefelder daneben** (36 statt 42 px, unten bündig, oben versetzt). `x-input.button` hat dafür eine Größe `feld` bekommen, die auf dieselbe Höhe kommt — kleinere Schrift, aber gleiche Zeilenhöhe und ein durchsichtiger Rand als Ausgleich für den fehlenden Feldrahmen. Die beiden Textknöpfe daneben („oder neu anlegen", „Abbrechen") ziehen mit.
- **Aus der Administration führte kein Weg zurück aufs Dashboard.** Das Logo im Kopf zeigt auf `/`, und das leitet auf die Anmeldung. Die Seitenleiste hat jetzt ganz oben einen Eintrag „Dashboard".

## 26.08.14

### Added

- **Alle Gerätelisten auf ein Layout umgestellt**: Die Kopfzeile trägt jetzt, was man fast immer nachschlägt — primäre IP mit Kopierknopf, Einbauort wo das Gerät einbaubar ist, Host bei VMs. Das Betriebssystem steht klein hinter dem Namen. Betrifft alle 18 Kartenlisten; Maschinen bleiben eine flache Tabelle. Der Rustdesk-Knopf bleibt unverändert das erste Bedienelement der Zeile.
- **Alle IP-Adressen in der Liste**: Die weiteren Adressen eines Geräts (`ipAddresses`) kamen in keiner Listen-Ansicht vor — sie standen ausschließlich im Bearbeiten-Formular. Jetzt zeigt die Karte primäre, sekundäre und alle zusätzlichen Adressen samt VLAN bzw. Bezeichnung.
- **Zähler für Mehrfach-Angaben**: `10.10.30.10 +3` in der Kopfzeile — man sieht schon vor dem Lesen, dass mehr hinterlegt ist. Für IP-Adressen und Zugangsdaten.
- **Fernwartung als Datenblock**: Rustdesk-ID und -Passwort zum Ablesen, wenn der Knopf nicht greift (anderer Rechner, kein Client).
- **Dienste-Katalog in der Administration**: Unter Auswahlmenüs lassen sich Dienste vorgeben und einfärben — freie Hex-Farbe mit Farbwähler, Hex-Feld und Vorschau. Die Schriftfarbe wird aus der Helligkeit berechnet und ist in hellem wie dunklem Erscheinungsbild lesbar. Die Dienste am Gerät bleiben Freitext; der Katalog färbt über den Namen, alles ohne Eintrag bleibt neutral. Ein gelöschter Katalogeintrag nimmt keine Dokumentation mit.

### Changed

- **Kantiger statt rund**: Die Radien sind zentral in der Tailwind-Konfiguration heruntergesetzt (`rounded-xl` 12 → 4 px), die Klassennamen bleiben. Abzeichen und Dienste-Kacheln sind keine Pillen mehr; rund bleiben der Avatar und die dünnen Fortschrittsbalken.
- **Karteninhalt läuft in Spalten** statt in einer Flex-Reihe. Vorher zog die Reihe alle Blöcke auf die Höhe des längsten — unter den kurzen klaffte Leere. Gemessen bei 1440 px: 475 → 323 px Kartenhöhe.
- Leere Felder fallen in den Detailkarten weg, ein Block ohne verbleibende Zeile ganz.
- Die Listen laden auch die weiteren IP-Adressen vor — sonst käme je Gerät eine eigene Abfrage dazu.

### Fixed

- Der Abstand unter dem letzten Block einer Spalte fehlte: Am Spaltenende zählt `margin-bottom` nicht mit, die Dienste-Kacheln klebten mit 0 px am Kartenrand.
- Der Bearbeiten-Knopf rutschte in eine zweite Zeile, sobald Name und Kernwerte breit wurden.

## 26.08.09

### Added

- **Zugangsdaten stehen direkt in der Geräteliste**: Benutzername und Passwort (maskiert, mit Auge und Kopierknopf) je verknüpftem Login – kein Umweg mehr über das Bearbeiten-Formular. In allen 19 Gerätelisten; bei den Maschinen als eigene Tabellenspalte, weil die Liste kein Kartenlayout hat. Sichtbar nur mit `logingeneral_viewAny`.
- Beschriftung ist die Notiz, falls gepflegt („Serielle Konsole"), sonst der Name des Logins – nebeneinander wäre es auf der schmalen Karte eine Dopplung.

### Fixed

- **„Abbrechen" führt zurück in die Liste.** Der Knopf zeigte über `redirect()->back()` auf die Seite, auf der man gerade stand – er lud sie also nur neu. Aus einem Formular kam man nur über die Seitenleiste heraus. Das Ziel kommt jetzt aus dem Routennamen (`vm.edit` → `vm.index`); Admin-Listen bekommen keinen Kunden angehängt.

### Changed

- Die Zugangsdaten stehen als zweiter Block in der Gerätekarte statt als letzter – bei Servern lagen sie hinter Hardware, Netzwerk, BMC, Diensten und Betriebssystem und damit unter dem Falz.
- Die Listen laden die Zugangsdaten vor (`Controller::getFilteredQuery`): ohne das käme je Gerät eine eigene Abfrage dazu, bei 25 Einträgen also 50. Ein Test misst das mit.
- Passwortfeld mit Auge und Kopierknopf als eigene Komponente `x-password`, statt den Block ein drittes Mal zu kopieren.
- Demo-Daten: Server bekommen feste Namen und alle eine Verknüpfung – vorher hießen zwei Paare gleich, und die namentlich angelegten Server oben in der Liste hatten keine Zugangsdaten.

## 26.08.08

### Added

- **Zugangsdaten an Geräte hängen**: Ein Eintrag aus „Logins Allgemein" lässt sich mit beliebig vielen Systemen verknüpfen – Server, VM, Switch, NAS, Router, Drucker, Kamera und die übrigen 19 Gerätetypen. Gedacht für das eine Passwort, das an mehreren Systemen gilt: einmal dokumentiert, mehrfach verknüpft, beim Wechseln eine Stelle statt fünf.
- Je Verknüpfung eine Notiz für den Ausnahmefall, dass dasselbe Login an zwei Geräten Verschiedenes bedeutet („Serielle Konsole" statt SSH). Bleibt normalerweise leer – die Spalte erscheint nur, wenn sie irgendwo gefüllt ist, sonst wiederholte sie den Namen.
- Im Gerät: vorhandenes Login auswählen oder direkt eins anlegen. Passwort maskiert, mit Kopierknopf.
- Umgekehrte Richtung: Login-Liste und Login-Formular zeigen, an welchen Systemen der Eintrag hängt – vor dem Passwortwechsel sichtbar. Auch im PDF.
- „Lösen" trennt nur die Verknüpfung; der Login-Eintrag bleibt. Ein Gerät im Papierkorb verschwindet aus der Liste und kommt beim Wiederherstellen zurück.

### Changed

- Demo-Daten: VMs, NAS, Recorder und die allgemeinen Logins bekommen feste Namen (die Factories ziehen sie zufällig aus kurzen Listen, derselbe Name tauchte mehrfach auf).

### Removed

- **„Logins NAS" und „Logins Recorder" entfallen**: Beide waren derselbe Mechanismus mit fest verdrahteter Geräte-ID – nur ohne Mehrfachverwendung und ohne PDF-Abschnitt. Der Bestand zieht per Migration nach „Logins Allgemein" um und hängt per Verknüpfung am selben Gerät wie vorher; benannt nach Gerät und Benutzer („NAS-Backup (admin)"). Passwörter werden als Geheimtext kopiert, nicht neu verschlüsselt. Einträge im Papierkorb kommen mit, ebenso das Verstecken-Kennzeichen. Die acht zugehörigen Berechtigungen räumt die Migration ab. Danach erscheinen Geräte-Logins zum ersten Mal im PDF-Export.
- Beim Löschen eines NAS oder Recorders verschwinden die Zugangsdaten nicht mehr mit: Sie können an weiteren Systemen hängen.

## 26.08.02

### Added

- **Geroutetes Netz beim Internet-Anschluss**: Optional ein Netz in CIDR-Schreibweise (z. B. `203.0.113.16/28`) samt Gateway – viele Anschlüsse bringen neben der WAN-Adresse einen eigenen Block mit. Beides freiwillig; die Prüfung lehnt eine Hostadresse statt der Netzadresse ab und nennt die richtige, und ein Gateway außerhalb des Netzes fällt auf. Liste und PDF zeigen zusätzlich den nutzbaren Bereich (bei IPv4).
- **Einbauort am Gerät**: Server, Switches, NAS, Router, USV, Recorder, Patchfelder und UMA zeigen in ihrer Liste, wo sie stecken – „Rack HH-01 · HE 4–5 · Vorderseite". Bisher stand das nur im Schrank selbst.
- **Serverschränke in der globalen Suche**: über Name und Ort, mit denselben Berechtigungen wie die Rack-Liste.
- **Umbauten im Aktivitätsprotokoll**: Einbauen, Verschieben, Höhe ändern und Entfernen stehen jetzt im Audit-Log. `RackItem` war bisher das einzige Model ohne `TracksChanges`.
- **Rückseite der Serverschränke**: Der Rack-Editor schaltet zwischen Vorder- und Rückseite um; beide lassen sich bestücken. Ein Gerät in voller Tiefe belegt beide Seiten und erscheint auf der Gegenseite als durchgehender Platzhalter, ein halbtiefes lässt dahinter Platz. Die Tiefe kommt beim Einbauen vom Gerät bzw. Katalogeintrag; Steckdosenleiste und Rangierfeld sind ab Werk halbtief. Bestand liegt vorne und gilt als durchgehend. Liste und PDF zeigen die Rückansicht, sobald dort etwas steht.
- **Englische Oberfläche, Sprache einstellbar**: Auswahl im Profil (Deutsch, English, oder der Browsersprache folgen) und eine Sprachauswahl in der Kopfzeile, der auch auf der Anmeldeseite und bei gesperrten Demo-Zugängen greift. Rund 460 Zeichenketten sind übersetzt – Formulare, Listen, Navigation, Assistent, Meldungen und die Beschriftungen aus der Konfiguration. Deutsch bleibt die Ausgangssprache: Fehlt eine Übersetzung, erscheint der deutsche Text. Auch die Beschriftungen in den Detailkarten und im PDF werden übersetzt.
- **Bauform und Einbautiefe beim Server**: 19-Zoll oder Standserver, und ob das Gerät die volle Schranktiefe belegt. Bei einem Standserver entfällt die Tiefe – das Feld erscheint nur beim Rackeinbau. Dazu die Höheneinheiten: Ein 2-HE-Server belegt beim Einbau direkt zwei HE. Nur 19-Zoll-Server erscheinen im Rack-Editor und lassen sich einbauen – auch bei direktem Aufruf. Bestandsserver gelten als 19-Zoll in voller Tiefe. Die Tiefe ist die Vorarbeit für die geplante Rückansicht der Schränke.
- **Docker-Setup**: `docker compose up` startet DokuVault samt MariaDB auf http://localhost:8000, inklusive Demo-Daten. Geseedet wird nur bei leerer Datenbank. Zum Ausprobieren und für kleine Installationen gedacht – ein Container, kein nginx.
- **Patchfelder mit Dosendokumentation**: Je Port Dosennummer, Raum und Ziel-Switch samt Portnummer. Dosennummer und Raum sind getrennte Felder. Enthalten in globaler Suche, Rack-Einbau und PDF-Export. Portanzahl lässt sich erhöhen; verkleinern wird abgelehnt, solange oberhalb dokumentierte Ports liegen. Bestehende Installationen erhalten die vier Berechtigungen per Migration.
- **Vordefinierte Demo-Zugänge sind gesperrt**: `admin`, `techniker`, `kunde-rw` und `kunde-r` lassen sich bei `DEMO_MODE=true` weder ändern noch löschen – auch der Benutzername nicht, denn daran erkennt der Schutz sie. Selbst angelegte Benutzer bleiben frei bearbeitbar.
- **Nutzung der Demo auswerten**: `php artisan demo:stats` zeigt Besuche und Seitenaufrufe gesamt, je Tag, je Tageszeit, je Rolle und je Herkunftsnetz. Kein User-Agent, keine aufgerufenen Seiten. Aufzeichnung als Datei unter `storage/app/demo-usage/`, damit sie den stündlichen Reset übersteht.

### Changed

- **Screenshots neu aufgenommen**: je Sprache 13 Bilder, dazu die Schrank-Übersicht mit Vorder- und Rückseite. Die Reihenfolge in der Tabelle bleibt wie gehabt, die Schrank-Übersicht kommt hinten dazu.
- **Serverschrank-Liste umgestellt**: Die Eckdaten stehen in einer Zeile, darunter ein Umschalter zwischen Vorder- und Rückseite wie im Editor – je Seite Belegung und Zeichnung nebeneinander. Vorher gab es das beschriftete Schema nur für die Vorderseite.
- **CODE_OF_CONDUCT auf Deutsch und Englisch**: Damit sind alle fünf Projektdokumente zweisprachig – Englisch als Startseite, Deutsch unter `*.de.md`.
- **SECURITY auf Deutsch und Englisch**: Englisch als `SECURITY.md`, Deutsch als `SECURITY.de.md`. Beide nennen jetzt GitHubs private Meldefunktion als ersten Weg und den Hinweis zu `TRUSTED_PROXIES`.
- **DEPLOYMENT auf Deutsch und Englisch**: Englisch als `DEPLOYMENT.md`, Deutsch als `DEPLOYMENT.de.md`. Damit sind README, CONTRIBUTING und DEPLOYMENT zweisprachig.
- **CONTRIBUTING auf Deutsch und Englisch**: Wie beim README ist Englisch die Startseite (`CONTRIBUTING.md`), Deutsch liegt unter `CONTRIBUTING.de.md`. Beide um einen Abschnitt zu Übersetzungen ergänzt.
- **README auf Deutsch und Englisch**: Englisch ist die Startseite (`README.md`), Deutsch liegt unter `README.de.md`; beide verlinken oben aufeinander. Grund: Die Repo-Beschreibung ist englisch, und der erste Besucher soll nicht an der Sprache scheitern. Screenshots neu aufgenommen, je Sprache ein Satz.
- **Anleitung zum Aktualisieren** in DEPLOYMENT.md – mit Backup vorab und Wartungsfenster nur um die Migrationen.
- **Deploy schaltet die Demo nur noch für Sekunden ab**: Wartungsmodus erst ab den Migrationen statt über den ganzen Vorgang. Gemessen: 10 statt 17 Sekunden, bei geänderten Abhängigkeiten deutlich mehr. `deploy.sh` und der `demo:reset`-Cronjob teilen sich eine Dateisperre.
- **Tests laufen gegen mehrere PHP-Versionen und gegen MariaDB**: PHP 8.2 und 8.3 je mit SQLite und MariaDB 11; PHP 8.4 läuft mit, blockiert aber nicht.
- **Vertrauenswürdiger Proxy in der Konfiguration** statt fest im Code: `TRUSTED_PROXIES` in der `.env`, mehrere Einträge und CIDR erlaubt. Ohne Eintrag sieht die App die Adresse des Proxys statt die des Besuchers – betrifft auch das Audit-Log.
- **Herkunft in der Demo-Statistik**: standardmäßig auf das Netz gekürzt (/24 bzw. /48), umschaltbar über `DEMO_IP_LOGGING` auf `aus` oder `voll`. `voll` speichert ein personenbezogenes Datum.
- **Ein Gerät bringt seine Höhe mit ins Rack**: Ein 48er-Patchfeld belegt direkt zwei HE, statt dass man sie nachträgt.
- **Die Rack-Zeichnung nutzt die echte Portanzahl** statt fester 24.

### Fixed

- **500er beim Ändern der E-Mail im Profil**: Der Controller setzte `email_verified_at` – eine Spalte, die es in `users` nicht gibt. Rest aus Breeze; das Projekt kennt kein E-Mail-Bestätigungsverfahren.
- **Profil vordefinierter Demo-Zugänge war offen**: Der Schutz saß nur im Löschen, nicht im Ändern – Name und E-Mail des geteilten Zugangs ließen sich überschreiben.
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
