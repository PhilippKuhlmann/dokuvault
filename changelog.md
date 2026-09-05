# Changelog

## 26.09.05

### Added

- **Fünf neue Agenten.** Bisher füllten drei Agenten 5 der 39 Objektarten; alles andere wurde abgetippt. Dazu kommen jetzt:
  - **Hyper-V** — meldet den Host als Server und jede virtuelle Maschine als eigenen Eintrag, mit Name, Status, Kernen, Arbeitsspeicher und IP. Das Gastbetriebssystem kommt über die Integrationsdienste; fehlen sie, bleibt das Feld leer, statt geraten zu werden.
  - **Windows-Server** — legt den Rechner als **Server** an, nicht als Client. Wer den Windows-Client-Agenten auf einem Server laufen ließ, fand ihn danach unter „Clients", wo ihn niemand sucht. Zusätzlich liest er die installierten Serverrollen und trägt sie als Dienste ein, **solange das Feld leer ist** — wer die Dienste einmal von Hand gepflegt hat, weiß mehr als `Get-WindowsFeature`. Gemeldet wird der sprachunabhängige Rollenname (`AD-Domain-Services`), nicht der übersetzte Anzeigename, und übernommen wird nur, was der Dienstekatalog schon führt: der Agent legt keine Katalogeinträge an.
  - **UniFi** — ein Skript, drei Objektarten: Switches, Accesspoints und WLANs. Erkannt werden die Geräte an der MAC-Adresse, WLANs an ihrer Controller-Id; ein umbenanntes Gerät bleibt derselbe Eintrag. Das Skript spricht sowohl UniFi OS (UDM, Cloud Key Gen2+) als auch den klassischen Controller an, ohne dass man die Bauart kennen muss.
  - **VMware** — über die vSphere-REST-Schnittstelle, ohne PowerCLI. Je ESXi-Host ein Server mit seinen VMs; die Zuordnung „welche VM läuft auf welchem Host" bleibt dabei erhalten. Voraussetzung ist vCenter — ein einzeln stehender ESXi-Host bringt diese Endpunkte nicht mit.
  - **Microsoft 365 / Entra ID** — Postfächer, verifizierte Domains und gebuchte Lizenzen über Microsoft Graph. Die Lizenz trägt die Stückzahl im Namen („… (12 von 15 belegt)"), weil die Tabelle keine Spalte dafür hat und das beim Kunden die erste Frage ist.
- **UniFi und Microsoft 365 gibt es zusätzlich als Shell-Script.** Beide Agenten sprechen ohnehin nur eine Schnittstelle im Netz an — sie müssen nicht auf Windows laufen. `unifi-doku.sh` und `microsoft365-doku.sh` brauchen nur `curl` und `jq` und laufen damit auf dem Mac und auf Linux. Auf der Token-Seite stehen beide Fassungen nebeneinander; welche man nimmt, ändert nichts daran, was gemeldet wird.
  - Die Shell-Fassungen **fragen das Kennwort ab**, statt es als Argument zu nehmen — sonst stünde es in der Prozessliste und in der Shell-Historie. Wer es doch mitgeben will, kann `UNIFI_PASSWORD` bzw. `M365_CLIENT_SECRET` setzen.
- **Die WLAN-Passphrase wird mitdokumentiert.** Sie fehlte zunächst, weil ich sie mit dem AD-Agenten gleichgesetzt hatte — falsch: AD-Kennwörter *kann* niemand auslesen, sie sind gehasht. Die Passphrase steht dagegen im Klartext in der Controller-Konfiguration, DokuVault hat für sie eine verschlüsselte Spalte, und in einer Dokumentation ist genau sie das, was man nachschlägt.
  - Der Controller ist die Quelle: Wird sie dort geändert, zieht der nächste Lauf nach. Ein WLAN ohne Passphrase (offen, WPA-Enterprise) bekommt keine, statt eine leere.
  - Geschrieben wird nur bei tatsächlicher Änderung. `Crypt::encryptString` liefert jedes Mal einen anderen Chiffretext — ohne den Vergleich der Klartexte stünde nach jedem Lauf „Kennwort geändert" im Protokoll.
  - `--ohne-kennwoerter` bzw. `-OhneKennwoerter` schaltet es ab; eine selbst gepflegte Passphrase bleibt dann stehen.
- **Zugangsdaten fremder Systeme werden nicht gespeichert.** vCenter, UniFi-Controller und die Graph-App-Registrierung braucht das jeweilige Skript, nicht DokuVault: Sie werden beim Aufruf mitgegeben. Ein Konto mit reinen Leserechten genügt überall. WLAN-Kennwörter liest der UniFi-Agent gar nicht erst aus — wie der AD-Agent nie ein Kennwort anfasst.

### Changed

- **Ein Agent kann mehrere Fassungen haben.** In `config/custom.php` steht je Agent eine Liste `varianten` — PowerShell, Bash. Der Endpunkt gehört dem Agenten, nicht der Fassung: beide melden dasselbe an dieselbe Stelle, sie sind zwei Wege zur selben Aufgabe. Wo es nur eine Fassung gibt, zeigt die Seite keinen Umschalter.
- **Die Agent-Skripte stehen nicht mehr im Controller.** Sie liegen als Dateien unter `resources/agents/`, die Beschreibung je Agent in `config/custom.php`. Vorher trug der `AgentTokenController` 320 Zeilen Heredoc für drei Skripte und die Agent-Seite drei fest verdrahtete Blöcke à 45 Zeilen — fünf weitere Agenten wären rund 580 Zeilen Kopie in zwei Dateien gewesen. Jetzt ist die Seite eine Schleife, und ein neuer Agent ist ein Listeneintrag plus eine Datei.
- **Ein nicht gemeldetes Feld wird nicht mehr überschrieben.** vCenter gibt Hersteller, Modell und Seriennummer eines Hosts nicht heraus. Wäre dort stur `null` eingetragen worden, hätte jeder Lauf gelöscht, was jemand von Hand nachgetragen hat.
- **VLAN und Kennwort eines WLANs dürfen fehlen.** Beide Spalten waren `NOT NULL`. Ein am Controller gefundenes WLAN hätte sich damit nur anlegen lassen, indem der Agent etwas erfindet. Am Formular ändert sich nichts: Wer ein WLAN von Hand einträgt, wird weiter nach beidem gefragt.

### Fixed

- **Der UniFi-Agent hätte auf einem Controller mit mehreren Sites die falsche dokumentieren können.** Er nahm stillschweigend `default`. Auf einem MSP-Controller steckt aber oft je Kunde eine Site — und ein Agent-Token gehört zu genau einem Kunden. Im schlechtesten Fall wären fremde Geräte in dessen Dokumentation gelandet. Jetzt gilt: eine Site → sie wird genommen; mehrere → das Script zeigt sie an und bricht ab, bis eine gewählt ist. `--site` bzw. `-Site` nimmt den internen Namen oder den Anzeigenamen, `--sites` bzw. `-Sites` listet nur auf.
- **Der UniFi-Agent kam bei UniFi OS nicht an die Daten.** Anmeldung erfolgreich, dann 403 auf jede Abfrage: Bei UniFi OS (UDM, UDM-Pro, Cloud Key Gen2+) reicht der Sitzungskeks nicht — die Anfragen unter `/proxy/network` brauchen zusätzlich den CSRF-Token aus der Anmeldeantwort. Beide Fassungen holen ihn jetzt aus der Kopfzeile und, wo ältere Firmware ihn dort nicht mitschickt, aus der Nutzlast des TOKEN-Kekses. Der klassische Controller war nie betroffen.
- **Ein Fehler sagt jetzt, was los ist.** `curl -f` verschluckte die Antwort und ließ nur `error: 403` bzw. `error: 401` übrig — beides ohne Hinweis, wo man suchen soll. Die Shell-Fassungen zeigen jetzt den Statuscode, die Antwort des Gegenübers und bei 401/403/404 den wahrscheinlichen Grund: abgelaufener DokuVault-Token, ein Ubiquiti-Cloud-Konto statt eines lokalen Administrators, oder eine Site, die es nicht gibt.
- **Ein Agent ohne Download kann nicht mehr entstehen.** Ein Invariantentest prüft in beide Richtungen: zu jedem Endpunkt unter `middleware('agent')` gibt es einen Listeneintrag und eine Skriptdatei mit beiden Platzhaltern — und zu jedem Listeneintrag einen Endpunkt.

## 26.09.04

### Added

- **IPAM: Adressbereiche reservieren.** Ein Bereich belegt nichts — er hält fest, wofür ein Stück des Netzes gedacht ist: „10.10.250.10 bis .20 sind für die Proxmox-Server". Im Plan steht dort „reserviert · Proxmox-Server" statt „frei", auch wenn davon erst zwei Adressen vergeben sind.
  - **Markiert wird nur vorn**, mit einem schmalen Randstreifen — keine flächige Einfärbung, und die Adresse selbst bleibt so grau wie eine freie. Farbig ist nur, was etwas aussagt: die Marke „reserviert“ und die Beschreibung. Der Streifen läuft durch belegte Adressen hindurch, sodass man sieht, wie weit der Block reicht. **Belegte Adressen im Bereich bleiben belegt** und tragen zusätzlich dessen Marke; ohne das sähe eine Reservierung löchrig aus, sobald jemand eine Adresse daraus vergibt.
  - Gepflegt wird der Bereich **dort, wo man ihn sieht** — unter dem jeweiligen VLAN im IPAM, nicht in einer eigenen Liste im Menü. Dasselbe Muster wie die IP-Adressen am Gerät.
  - **Jeder Bereich eines VLANs bekommt eine eigene Farbe** — sechs im Wechsel, vergeben nach Anfangsadresse. Zwei Reservierungen, die aneinanderstoßen, wären in einer Farbe eine einzige Fläche; man sähe nicht, wo die eine aufhört. Die Liste unter der Tabelle und der Balken über dem VLAN benutzen dieselbe Zuteilung — der Balken zeigt ein Stück je Reservierung, mit dem Namen beim Überfahren.
  - **Der DHCP-Bereich schlägt die Reservierung.** Was der DHCP-Server selbst vergibt, ist kein reservierter Block mehr — und wer beides übereinanderlegt, soll das im Plan sehen.
  - Abgewiesen werden: Adressen außerhalb des Netzes, eine Endadresse vor der Anfangsadresse und Überschneidungen mit einem bestehenden Bereich. Zwei Reservierungen für dieselbe Adresse wären ein Widerspruch in der Doku.
  - Kein eigenes Recht: Wer ein VLAN bearbeiten darf (`network_update`), darf auch Bereiche pflegen. Kein Papierkorb — ein Bereich ist eine Notiz, keine Inventarposition.

## 26.09.03

### Changed

- **Fehlermeldungen in Formularen sehen anders aus.** Anlass war das VLAN-Formular: Wer ein Pflichtfeld vergaß, bekam kleinen roten Text unter dem Feld — sonst nichts.
  - **Das fehlerhafte Feld markiert sich jetzt selbst** (rote Umrandung, `aria-invalid`). Das ist der Unterschied zwischen „irgendwo stimmt etwas nicht" und „hier". Vorher musste man den Text lesen, um es zu finden.
  - **Über dem Formular steht ein Satz**, dass etwas fehlt. Ein Dialog, der sich nicht schließt, wirkt sonst wie ein hängender Knopf.
  - **Der Wortlaut sagt, was zu tun ist:** „Bitte Bezeichnung angeben." statt „Das Feld Bezeichnung ist erforderlich." — der Satz stand direkt unter der Beschriftung, die er wiederholt hat. Das gilt für alle Pflichtfeld-Meldungen der Anwendung.
  - **Eine Komponente statt vier Schreibweisen.** Derselbe Text stand im Projekt in vier Varianten — `text-sm` und `text-xs`, mit und ohne Abstand — und **18 von 37 hatten keine Farbe für den Dunkelmodus**: `text-red-600` auf dunklem Grund. Alle 35 Feldmeldungen benutzen jetzt `x-input.fehler`; ein Test hält offen, dass keine neue ohne Dunkelmodus-Farbe dazukommt.
  - Vor dem Text steht ein Zeichen. Nicht als Zierde: Wer Rot und Grau schlecht unterscheidet, sieht sonst nur Text.
- **Alle Livewire-Formulare prüfen während der Eingabe** — aber erst, nachdem einmal abgeschickt wurde. Wer ein rotes Feld ausfüllt, sieht das Rot verschwinden, sobald der Wert stimmt; ein weiterhin falscher Wert bleibt rot. Vor dem ersten Absenden wird nicht geprüft: Nach dem ersten getippten Zeichen „Bitte Netz angeben" zu melden wäre Meckern, kein Hinweis.
  - Betroffen sind sieben Formulare, darunter das generische, das rund 40 Objekttypen bedient. Die Einstellungsseiten brauchten nichts: Sie speichern beim Ändern und prüfen dadurch längst live.
  - Der Preis: Die Felder hängen jetzt live am Server, mit 400 ms Pause. Das war vorher bewusst nicht so („eine Runde je Tastenpause für jedes Feld wäre Verschwendung") — ohne die Bindung bliebe ein rot markiertes Feld aber bis zum nächsten Absenden rot. Die Pause hält es bei einer Anfrage je Tipppause, nicht je Anschlag.
  - Die Regeln stehen je Formular in **einer** Methode, die Speichern und laufende Prüfung teilen. Zwei Listen liefen früher oder später auseinander.
  - Zwei Invarianten gegen stille Ausfälle: Wer den Baustein einbindet und `updated()` selbst schreibt, muss die Prüfung aufrufen — die eigene Methode gewinnt sonst, ohne dass es knallt. Und wer ihn einbindet, muss ihn auch einschalten.

### Fixed

- **Die Kundenliste im Adminbereich hatte ihren Link auf den Kunden verloren.** In der Spalte „URL" steht `/mustermann` — ein Pfad innerhalb der Anwendung, kein `http`. Der XSS-Schutz von gestern ließ nur absolute Adressen durch und hat damit die eigene Anwendung ausgesperrt.
  - Der erste Versuch war falsch: Jeder Wert mit führendem Schrägstrich galt als Pfad — und in der VLAN-Liste steht `/24`. Aus der Netzmaske wurde ein Link auf eine Seite `/24`. Aus dem Wert allein ist das nicht zu unterscheiden.
  - Jetzt entscheidet der **Schlüssel**, nicht der Wert: Die Kundenliste übergibt den Weg unter `pfad`, die Netzmaske steht unter `CIDR` und kommt gar nicht erst in die Nähe. Geraten wird nichts mehr.
- **Die Verwaltungsoberfläche einer Firewall war nicht anklickbar.** In der Karte heißt das Feld „Oberfläche", und verlinkt wurde nach der **Beschriftung** — die Bedingung zählte vier Namen auf („URL", „Admin URL", „User URL", „Externe URL"), und „Oberfläche" stand nicht darunter. Dort steht eine `https`-Adresse, die man nur markieren und kopieren konnte.
  - Das war schon vorher so und ist beim gestrigen XSS-Umbau nicht aufgefallen. Die dortige Commit-Nachricht behauptet, dieses Feld sei als `href` ausgegeben worden — das stimmte nicht.
  - Geprüft wird jetzt der **Wert** statt der Beschriftung. Damit funktioniert jedes URL-Feld, auch künftige: Wer eins anlegt, müsste sonst eine Liste pflegen, von der er nichts weiß.
  - Unverändert bleibt, was der Schutz leistet: Verlinkt wird nur `http` und `https`, eine nackte IP bleibt Text, und ein Kennwort bleibt maskiert, auch wenn es wie eine Adresse aussieht. Drei Tests halten das fest.

### Added

- **Einstellungen → Allgemein: erlaubte Dateiendungen.** Ankreuzfelder statt Freitext — die Auswahl stammt aus `config/custom.php`. Man kann die Positivliste **kürzen, nicht erweitern**: Eine Liste, in die jemand `php` eintragen kann, ist keine. Geschnitten wird beim Lesen, nicht nur beim Speichern, damit auch ein Eintrag direkt in der Tabelle nichts hinzufügt. Ein Test hält genau das fest.
  - SVG steht weiterhin nicht zur Wahl und bleibt im Code: Eine SVG-Datei darf Skripte enthalten. Das ist eine Sicherheitsentscheidung, keine Vorliebe.
- **Einstellungen → Allgemein: Sprache, Anmeldehinweis und Seitengrößen.** Die Sprache der Installation (bisher nur über `config/app.php` zu ändern), ein Satz unter dem Anmeldeformular — etwa wer bei Fragen zum Zugang hilft — und wie viele Zeilen eine Seite zeigt (25 in den Kundenlisten, 20 im Adminbereich, an 13 Stellen fest verdrahtet).
  - Der Hinweis wird **escaped** ausgegeben. Die Anmeldeseite ist die eine Seite, die jeder erreicht, auch ohne Zugang; ein Feld, das dort HTML einschleusen könnte, wäre der schlechteste denkbare Ort dafür. Ein Test hält das fest.
  - Das Protokoll bleibt bei 50 Zeilen und ist bewusst nicht dabei: Dort sucht man nach einem Vorgang und überfliegt, statt zu lesen.
- **Einstellungen → Sicherheit: Anmeldung und Sitzung.** Fehlversuche je Konto (5), Sperrdauer (15 Minuten), Fehlversuche je Herkunft (30) — und für die Sitzung: Dauer (120 Minuten), „Angemeldet bleiben" (30 Tage), „Beim Schließen des Browsers abmelden".
  - **Auch hier standen die Zahlen zweimal im Code:** in `LoginRequest` und im `TwoFactorChallengeController`, die zweite Stelle mit dem Kommentar „wie bei der Anmeldung selbst". Ein Kommentar hält zwei Zahlen nicht zusammen. Beide lesen jetzt dieselbe Quelle; ein Test hält fest, dass keine der beiden Klassen mehr eigene Konstanten hat.
  - Bei „Beim Schließen des Browsers abmelden" steht dabei, dass es **nur die Sitzung** betrifft: Wer beim Anmelden „Angemeldet bleiben" angehakt hat, kommt trotzdem wieder herein. Der halbe Satz wäre gefährlicher als keiner.
- **Einstellungen → Fristen: Vorwarnzeiten und die Aufbewahrung der PDF-Ausgaben.** Vier Zahlen — Lizenzen/Zertifikate/Domains (60 Tage), Garantien (60), Support-Ende der Betriebssysteme (180) und wie lange eine PDF-Ausgabe liegen bleibt (24 Stunden).
  - **Die Zahlen standen an sieben Stellen einzeln im Code.** Zweimal dieselbe, mit dem Kommentar, es sei dieselbe: die 180 Tage in `OperatingSystem::laeuftAus()` und im `EolController`. Wer eine davon geändert hätte, hätte die andere stehen lassen — die Liste hätte dann ein System gezeigt, das kein Abzeichen trägt. Ein Test hält offen, dass beide derselben Quelle folgen.
  - Garantien haben eine eigene Frist bekommen, obwohl sie heute gleich eingestellt ist. Es ist die andere Arbeit: Eine Lizenz verlängert man, ein Gerät ohne Garantie muss ersetzt werden — und Ersatz braucht Budget und einen Termin.
  - Auf der Seite steht bei jeder Frist, **wo** sie wirkt. Eine Zahl ohne diesen Satz stellt man ein und weiß nicht, was sich ändert.
  - Null Tage wird abgewiesen: Das wäre keine Frist, sondern eine abgeschaltete Warnung — und die schaltet man nicht versehentlich über ein leergeräumtes Feld ab.

### Changed

- **PDF-Ausgaben werden jetzt stündlich aufgeräumt statt einmal nachts um 03:30.** Die Frist stand immer in Stunden, durchgesetzt wurde sie einmal am Tag: Eine Ausgabe von 04:00 Uhr lag mit „24 Stunden" bis zum übernächsten Lauf — fast zwei Tage. Die Dateien enthalten alle Zugangsdaten eines Kunden im Klartext, deshalb ist das die sicherheitsrelevante der vier Fristen. Dass eine Datei bis zu eine Stunde länger liegt als eingestellt, steht jetzt unter dem Feld.

### Fixed

- **Der Hinweis unter den Logos listete kurzzeitig Dokumentformate.** Die neue Eigenschaft für die Dateiendungen hieß wie die Ansichtsvariable mit den Bildformaten und überdeckte sie — unter „Logo" stand plötzlich PDF, DOCX, ZIP. Im Browser gesehen, kein Test hat es gemerkt; jetzt gibt es einen.
- **Die letzte Stufe der Sprachwahl lief nie.** `SetLocale` fragt Nutzer → Sitzung → Browser → Vorgabe. Die Browserstufe benutzte `getPreferredLanguage()`, und das liefert bei einer unbekannten Browsersprache **die erste angebotene** statt `null` — die Stufe darunter kam also nie zum Zug. Das fiel nicht auf, solange „erste angebotene" und „Vorgabe" beide Deutsch waren; die neue Einstellung wäre wirkungslos geblieben. Jetzt zählt die Browsersprache nur, wenn der Browser tatsächlich eine der angebotenen verlangt.
- **`StilKlassenTest` prüfte nur Farbklassen mit Deckkraft.** Damit blieb eine ganze Sorte ungedeckt: `space-y-5` stand in einer neuen Seite, fehlte im gebauten CSS, und die Karte hatte zwischen zwei Feldern weniger Abstand als innerhalb eines — die Gruppen lasen sich verkehrt herum. Anders als bei Farben fällt das nicht auf, wenn man nicht hinsieht: Es fehlt kein Kontrast, es fehlt ein Abstand. Der Test deckt jetzt auch Größen und Abstände ab (232 Klassen, alle vorhanden).
- **„Angemeldet bleiben" wäre auf 400 Tage zurückgefallen, wenn die Einstellungen nicht lesbar sind.** Beim Umbau lag der Aufruf, der die Frist auf 30 Tage begrenzt, zuerst im `try` — und ein Fehler beim Lesen hieß damit nicht „es bleibt bei 30", sondern „es gilt wieder Laravels Vorgabe". Ein bestehender Test hat das aufgedeckt; ein neuer hält den Aufruf jetzt außerhalb.
- **Zahlenprüfungen meldeten sich auf Englisch.** `lang/de/validation.php` kannte `min`/`max` nur für Texte, nicht für Zahlen — eine zu kleine Frist wurde mit „The … must be at least 1." abgewiesen. Im Browser gesehen, nicht im Code vermutet. Betrifft jede Zahlen-, Datei- und Auswahlprüfung der Anwendung, nicht nur die Fristen.

## 26.09.02

### Added

- **Einstellungen → Sicherheit: Länge und Komplexität der Kennwörter.** Mindestlänge (8–64) und vier Häkchen — Groß- und Kleinbuchstaben, Ziffer, Sonderzeichen, Abgleich gegen bekannte Datenlecks.
  - **Bis dahin stand hier nichts.** `Password::defaults()` wird an vier Stellen benutzt, war aber nirgends konfiguriert: Es galt stillschweigend Laravels Vorgabe von acht Zeichen, ohne Zeichenklassen und ohne Leck-Abgleich. Gesetzt wird die Regel jetzt an **einer** Stelle und wirkt dadurch an allen vier — eigenes Profil, Anlegen durch einen Administrator, Kennwort vergessen, Einladung einlösen. Eine Regel, die nur an einer Stelle greift, ist keine.
  - **Was verlangt wird, steht unter dem Kennwortfeld** — in allen fünf Ansichten, aus der Einstellung erzeugt. Wer ein Sonderzeichen verlangt, ohne es hinzuschreiben, lässt raten.
  - **Die Meldungen sind jetzt deutsch.** Sie kamen aus dem Gerüst und standen auf Englisch — samt „Das Feld password" statt „Kennwort". Betrifft alle Kennwortmeldungen der Anwendung, nicht nur die neuen.
  - Bestehende Kennwörter bleiben gültig; die Regel greift, sobald jemand ein neues setzt. Das steht auf der Seite, sonst traut sich niemand, sie anzuziehen.
  - Der Abgleich gegen Datenlecks fragt eine fremde Liste. Ist sie nicht erreichbar, lässt die Prüfung durch — sie fällt still aus, statt zu blockieren. Auch das steht dabei.
  - **Nicht betroffen:** die dokumentierten Kennwörter der Kunden. Dort wird festgehalten, was ist, nicht was sein soll — ein Kunde mit schwachem Kennwort muss dokumentierbar bleiben.

### Security

- **Postfach- und Druckerkennwort lagen im Klartext in der Datenbank.** Sie waren die einzigen beiden Kennwörter ohne Verschlüsselung — gefunden beim Abgleich aller Spalten, deren Name auf ein Geheimnis hindeutet, mit denen, die tatsächlich verschlüsselt sind. Eine Migration verschlüsselt die vorhandenen Werte mit; die Spalten sind dabei groß genug geworden.
  - **Eine Invariante hält es offen:** Wer eine Spalte anlegt, deren Name nach einem Geheimnis klingt, muss sie verschlüsseln — oder im Test eintragen, warum nicht. Beides ist eine Entscheidung; keine ist es nicht.
  - **Die Lizenzschlüssel bleiben bewusst im Klartext.** Sie waren verschlüsselt, und die Änderung wurde zurückgenommen: Nach ihnen wird gesucht — bei Windows-Lizenzen ist der Schlüssel das einzige Suchfeld —, und über eine verschlüsselte Spalte lässt sich nicht suchen. Der Grund steht jetzt im Test, statt dass die Frage offen bleibt.
- **Hinweis für Betreiber: Die Sicherung enthält den Schlüssel zur Verschlüsselung.** `spatie/laravel-backup` sichert das ganze Projektverzeichnis, also auch die `.env` mit dem `APP_KEY`. Ohne Archivkennwort liegt in einer Sicherung der Tresor neben seinem Schlüssel. `BACKUP_ARCHIVE_PASSWORD` ist jetzt in der `.env.example` erklärt — es war vorher nirgends erwähnt.

- **Kein Kennwort steht mehr in einer Antwort der Schnittstelle.** `Server` und `Accesspoint` gingen als JSON hinaus, samt `bmcPassword`, `remotePassword` und dem WLAN-Kennwort — bisher als verschlüsselter Wert. Nutzlos für den Aufrufer, und ein Leck in dem Moment, in dem jemand aus dem Attribut einen Cast macht. Sie sind jetzt aus der Serialisierung ausgenommen.

### Fixed

- **Die Server-Schnittstelle war nicht erreichbar.** `/{customer}` passt auf jedes einzelne Segment und stand vor ihr: `GET /api/servers` suchte einen Kunden namens „servers" und endete in einem 404. Aufgefallen erst beim Durchspielen mit echten Tokens.
- **`GET /api/customers` ohne Suchbegriff endete in einem Serverfehler.** Die Suche erwartete einen String und bekam `null`.

- **Eine URL an einer Firewall konnte fremden Code ausführen.** `management_url`, `url_user` und `url_external` werden nur auf ihre Länge geprüft — dort steht oft eine nackte IP, eine strenge Regel wäre falsch. Sie landeten aber als `href` in der Karte: Stand dort `javascript:…`, führte **jeder Klick** den Code aus — in der Sitzung dessen, der klickt, nicht dessen, der ihn eingetragen hat.
  - Der Schutz sitzt jetzt dort, wo der Link entsteht: Verlinkt wird nur, was `http` oder `https` ist. Alles andere steht weiterhin da, nur nicht als Link. Das deckt auch ab, was längst in der Datenbank steht.
  - Positivliste, keine Sperrliste — andersherum müsste man jedes Schema kennen, das ein Browser ausführt.
- **Sechs Alpine-Ausdrücke setzten Werte in Anführungszeichen zusammen.** Im HTML-Attribut entschlüsselt der Browser den Wert, bevor Alpine ihn auswertet: Aus `&#039;` wird wieder ein Anführungszeichen, und der Ausdruck lässt sich verlassen. Im Browser nachgestellt — ein von der Validierung abgewiesener Wert kam über `old()` zurück und führte Code aus. Alle sechs nutzen jetzt `@js(…)`.
  - **Eine Invariante hält es offen:** Ein Test durchsucht alle Blade-Dateien nach dieser Schreibweise. Wer morgen eine hinzufügt, merkt es sofort.
- SVG ist als Dateianhang nicht mehr erlaubt — ein SVG darf Skripte enthalten, und an einer Lizenz hat eines ohnehin nichts zu suchen. (Bei Bildern stand es nie zur Wahl.)

- **Ein Kundennutzer konnte Dateien in das Verzeichnis eines anderen Kunden schreiben.** Beim Hochladen an Lizenzen und Zertifikaten ging die eingegebene Bezeichnung ungeprüft in den Ablagepfad. Mit `../../../../fremder-kunde/files/vertrag` landete die Datei dort — und überschrieb, was dort lag. Nachgestellt und belegt, bevor der Schutz entstand; ein Test hält den Angriff jetzt offen.
  - Bezeichnung **und** Endung laufen jetzt durch `App\Support\Dateiname`. Die Endung kam ebenso ungeprüft durch: Sie stammt aus dem Dateinamen, den der Browser mitschickt, und den bestimmt der Absender.
  - Der Zeitstempel im Namen war nie ein Schutz davor — er verhindert nur, dass zwei gleichnamige Dateien sich überschreiben.
- **Diese Uploads wurden überhaupt nicht geprüft.** Weder wie groß die Datei ist noch was sie ist: Eine 60 MB große Datei ging durch, eine `.php` auch. Jetzt gilt eine Positivliste erlaubter Endungen (`config/custom.php`) und eine Größe.
- Der Download einer Kundendatei sendet `X-Content-Type-Options: nosniff` — wie die übrigen Ausgabestellen.

### Added

- **Einstellungen → Allgemein ist in drei Karten geteilt:** „Name und Logo", „Zeitzone", „Hochladen". Zeitzone und Uploadgrenze standen unter der Überschrift „Name und Logo" — dort suchte sie niemand.
- **Die Obergrenze für Uploads steht unter Einstellungen → Allgemein.** Vorher nur in `config/custom.php` auf dem Server.
  - Darunter steht, **was der Server überhaupt hergibt**: `upload_max_filesize` und `post_max_size` im Klartext. Ein höherer Wert wird abgewiesen — er wäre ein Versprechen, das nicht hält: Der Upload bräche mitten im Hochladen ab, ohne verständliche Meldung.
  - Auch eine bereits gespeicherte Zahl wird gedeckelt, etwa nach einem Umzug auf einen Server mit engeren Grenzen.
  - Der Hinweis nennt auch, dass ein Webserver davor eine eigene, niedrigere Grenze haben kann — die sieht PHP nicht. (Im mitgelieferten Docker-Bild sind beide auf 24 MB gesetzt.)

### Fixed

- **Für Uploads galten zwei verschiedene Grenzen, und niemand kannte die kleinere.** Livewire lässt ohne Angabe höchstens 12 MB durch, die Anwendung erlaubte 20: Über ein gewöhnliches Formular ging eine 15-MB-Datei, über ein Modal nicht. Jetzt gilt überall dieselbe Zahl, und sie steht an einer Stelle.

## 26.09.01h

### Fixed

- **`composer install` scheiterte auf einem frischen Auscheck-Verzeichnis.** Die neue Begrenzung für „Angemeldet bleiben" spricht den Anmelde-Guard an; der zieht die Sitzung, die Sitzung ist verschlüsselt — und ohne `APP_KEY` wirft der Verschlüssler. Genau das ist der Zustand, in dem `composer install` sein `package:discover` ausführt: vor der `.env`. Der ganze CI-Lauf brach daran ab, bevor auch nur ein Test lief. Die Zeile läuft jetzt nur mit vorhandenem Schlüssel — ohne einen gibt es ohnehin keine Sitzung.

## 26.09.01g

### Added

- **Die Benutzerliste zeigt offene Einladungen.** „offen seit 30.08.2026" oder „abgelaufen 23.08.2026" — bis dahin sah man nicht, wer nie reagiert hat. Der Stand steht auch auf der Benutzerseite über dem Knopf, sonst weiß ein Administrator nicht, ob er gerade zum ersten oder zum zweiten Mal schickt.
  - Der Zeitpunkt steht am Benutzer, nicht in `password_resets`: Dort liegen Einladungen und Kennwort-Zurücksetzungen in derselben Tabelle ohne Merkmal, welche Zeile welche ist — und der Eintrag verschwindet beim Einlösen, die Frage „wer hat nie reagiert?" ließe sich damit gar nicht stellen.
  - Die Ablauffrist wird beim Broker gelesen, nicht noch einmal aufgeschrieben.
- **Ein verbrauchter Wiederherstellungscode sagt, wie viele noch da sind.** Beim letzten steht dabei, was zu tun ist. Wer nicht mitzählt, merkt beim nächsten verlorenen Telefon, dass keiner mehr übrig war — und kommt dann gar nicht mehr herein.
  - Der Hinweis blendet sich **nicht** von selbst aus. Die grüne Meldung nebenan verschwindet nach vier Sekunden — richtig für „gespeichert", falsch hierfür.

### Fixed

- **Eine Pluralform hätte deutschen Nutzern den englischen Satz gezeigt.** `trans_choice` fällt bei fehlender deutscher Übersetzung auf die Ersatzsprache zurück, `__()` tut das nicht. Beim Bauen aufgefallen, nicht im Betrieb.
- Der Test, der Übersetzungen gegen ihre Verwendung abgleicht, übersah Aufrufe, die der Formatierer umgebrochen hatte — und meldete sie als verwaist.

## 26.09.01f

### Added

- **Die Zeitzone lässt sich unter Einstellungen → Allgemein wählen.** Die Anwendung rechnete in UTC, in Deutschland standen damit alle Zeitpunkte zwei Stunden daneben.
  - **Gespeichert wird weiter in UTC** — das ist der Kern. Würde stattdessen `app.timezone` umgestellt, schriebe die Anwendung ab der Umstellung lokale Zeiten in dieselben Spalten, in denen bereits UTC steht: zwei Zeitzonen in einer Spalte, ohne Merkmal, welche Zeile welche ist. Umgerechnet wird deshalb erst beim Anzeigen.
  - **Datumsangaben ohne Uhrzeit** (Ablaufdatum, Beschaffung, EOL) laufen bewusst nicht durch die Umrechnung: Ein Datum um Stunden zu verschieben kann es auf den Vortag kippen lassen.
  - Unter der Auswahl steht die aktuelle Uhrzeit, damit man sieht, was die Umstellung bewirkt.

### Changed

- **„Angemeldet bleiben" hält 30 Tage statt fünf Jahre.** Ohne Angabe lässt Laravel das Cookie praktisch unbegrenzt gelten — in einem Werkzeug, das die Kennwörter ganzer Kundennetze hält, wäre ein gestohlenes Notebook damit ein Dauerzugang. Die Zahl steht in `config/custom.php`.

### Fixed

- Der Protokolleintrag „Zweite Stufe zurückgesetzt" hatte keinen Ereignisnamen — in der Spalte stand „—", und filtern ließ er sich auch nicht.
- Die Ereignisnamen des Protokolls waren im Englischen deutsch geblieben. Der Test, der übersetzte Zeichenketten gegen ihre Verwendung abgleicht, kannte diese Quelle nicht; jetzt schon.

## 26.09.01e

### Added

- **Anmeldungen stehen im Protokoll.** Bis dahin stand dort, wer welchen Server geändert hat — aber nicht, wer sich überhaupt angemeldet hat. In einem Werkzeug, das die Kennwörter ganzer Kundennetze hält, war das die erste Frage nach einem Vorfall und die einzige, die sich nicht beantworten ließ.
  - Drei neue Ereignisse: **Angemeldet**, **Anmeldung gescheitert**, **Anmeldung gesperrt** — mit Herkunft und Browser unter „anzeigen".
  - Auch ein **falscher zweiter Faktor** landet dort. Laravel löst dabei kein Ereignis aus, das Kennwort stimmte ja — und genau dieser Fall ist der interessante: Jemand kennt das Kennwort und kommt trotzdem nicht herein.
  - Das **versuchte Kennwort** steht nirgends. Es steckt im Ereignis mit drin; wer sich beim Benutzernamen vertippt, hätte sein richtiges Kennwort sonst im Klartext im Protokoll.
  - Die Einträge laufen mit derselben Aufbewahrungsfrist ab wie der Rest des Protokolls.
- **„Zuletzt angemeldet" in der Benutzerliste.** Verwaiste Zugänge waren bis dahin unsichtbar — der Werkstudent von vorletztem Jahr hat vielleicht noch ein Konto. Wer sich nie angemeldet hat, steht als „noch nie" da.

### Fixed

- **Zwei Seiten versprachen einen Papierkorb, den es nicht gibt.** Benutzer und Dienste werden **endgültig** gelöscht — die Warnung sagte „wandert in den Papierkorb und lässt sich von dort wiederherstellen". Wer sich darauf verlassen hat, hat einen Zugang im Glauben gelöscht, ihn zurückholen zu können.
- **Der Erstaufnahme-Assistent leerte seine Felder nicht.** Der Server tat es, der Browser nicht: Livewire schützt Eingabefelder beim Morphen, der getippte Name blieb stehen — und ein zweiter Klick auf „Hinzufügen" legte denselben Standort noch einmal an.

## 26.09.01d

### Added

- **Einstellungen → Mail.** Server, Port, Verschlüsselung, Benutzername, Kennwort und Absender lassen sich im Adminbereich pflegen — bis dahin ging das nur über die `.env` auf dem Server.
  - **Was hier steht, gilt statt der `.env`.** Bleibt der Server leer, bleibt alles wie vorher: Eine Installation, die ihren Versand über die Umgebung konfiguriert, läuft unverändert weiter. Unter dem Serverfeld steht, was zurzeit aus der Umgebung käme.
  - **Das Kennwort liegt verschlüsselt.** Die Einstellungen gehen über einen Cache, und ein Kennwort, mit dem sich im Namen der Firma Mail verschicken lässt, hat weder dort noch in einem Datenbank-Abzug etwas im Klartext zu suchen. Es geht auch nie wieder auf die Seite hinaus — nur die Auskunft, dass eines hinterlegt ist. Ein leeres Feld heißt „unverändert"; zum Entfernen gibt es einen eigenen Knopf.
  - **Testmail mit Fehlermeldung.** Schlägt der Versand fehl, steht die Antwort des Servers auf der Seite. „Verbindung fehlgeschlagen" hilft niemandem, „535 Authentication failed" schon. Ohne diese Probe erfährt ein Administrator erst dann von falschen Zugangsdaten, wenn ein Benutzer auf seine Einladung wartet.

## 26.09.01c

### Added

- **Benutzer per E-Mail einladen.** Haken „Per E-Mail einladen" im Formular für einen neuen Benutzer: Statt eines Kennworts, das der Administrator sich ausdenkt und per Zuruf weitergibt, bekommt der Benutzer einen Link und vergibt es sich selbst.
  - Das Kennwortfeld verschwindet, sobald der Haken sitzt. Beides nebeneinander stehen zu lassen wäre die Einladung zum Missverständnis: ein getipptes Kennwort, das nie gilt.
  - **Der Link gilt eine Woche**, nicht eine Stunde. Eine Stunde reicht für „ich habe mein Kennwort vergessen"; eine Einladung geht an jemanden, der vielleicht im Urlaub ist. Dafür gibt es einen eigenen Broker `einladung` — dieselbe Maschinerie wie beim Zurücksetzen, andere Frist.
  - **Erneut schicken** geht auf der Benutzerseite. Im Spam gelandet, Link abgelaufen, versehentlich gelöscht: Das ist der Alltag, nicht der Fehler.
  - Die Mail geht **synchron** hinaus: Ein Administrator soll sofort erfahren, ob sie ankam — und nicht erst der Benutzer, der drei Tage auf nichts wartet. Schlägt sie fehl, steht das auf der Seite statt in einer 500er-Meldung.
  - Der Link ist nur für den Eingeladenen: Als angemeldeter Benutzer landet man auf der eigenen Startseite. Sonst setzte der Administrator am eigenen Rechner eben doch das Kennwort des neuen Benutzers.

- **Die Mails sehen aus wie die Anwendung.** Kopfband in `cerulean-950`, Knopf in `cerulean-600`, heller blauer Untergrund — dieselben Töne wie in der Oberfläche statt des Schwarzgraus aus dem Gerüst. Gilt für alle Mails, nicht nur die Einladung.
  - **Das eigene Logo steht im Kopf**, sofern eines hinterlegt ist. Sonst der Name der Installation als Text: Das eingebaute Zeichen ist ein SVG, und SVG zeigen die meisten Mailprogramme gar nicht erst an — ein Name, der immer ankommt, ist besser als ein Bild, das es meistens nicht tut.
  - Der Kopf zeigt den **eingestellten** Namen, nicht den aus der Konfiguration — eine umbenannte Installation hieß in ihren Mails sonst weiterhin anders.
  - Die Höhe des Logos ist begrenzt, die Breite läuft: Fest auf 75 × 75 gesetzt sah ein breites Logo gequetscht aus.
  - Im Fuß steht jetzt, dass die Nachricht automatisch verschickt wurde, statt „All rights reserved" — diese Mails gehen an Kollegen und Kunden, nicht an Abonnenten.

### Changed

- **Der Text der Einladung.** Er war im Passiv geschrieben und erklärte, was der Knopf direkt darunter ohnehin sagt: „für Sie wurde ein Zugang angelegt. Vergeben Sie sich über den Knopf ein Kennwort." Jetzt: „Sie haben jetzt Zugang zu DokuVault. Es fehlt nur noch Ihr Kennwort."
  - Und **dieselbe Handlung heißt überall gleich**: „Kennwort festlegen" — in der Mail, auf dem Formular dahinter und in der Bestätigung. Vorher hieß sie dreimal anders (vergeben, setzen, gesetzt).
  - Der Hinweis zur Frist klang, als käme die neue Einladung von selbst. Sie kommt nicht von selbst: „Ist er abgelaufen, fragen Sie Ihren Administrator nach einer neuen Einladung."
  - Die Grußformel schließt jetzt mit dem Namen der Installation. „Viele Grüße" ohne Absender darunter sah aus wie abgeschnitten.

### Fixed

- **„Kennwort vergessen" war gar nicht gangbar.** Beide Formulare stammten unverändert aus dem Gerüst: englisch, im alten Layout, und sie fragten nach der **E-Mail** — die Anwendung meldet aber mit dem **Benutzernamen** an, und genau den erwartete auch der Controller. Wer den Weg ging, kam nie an. Beide Seiten sehen jetzt aus wie die Anmeldung und fragen nach dem Benutzernamen.
- **Ein Zugang ohne E-Mail-Adresse führte dabei in einen Serverfehler**, weil die Nachricht an niemanden gehen konnte. Jetzt steht da, woran es liegt.
- Die Anmeldeseite zeigt jetzt Rückmeldungen aus dem vorigen Schritt an. Wer über eine Einladung oder ein zurückgesetztes Kennwort dort ankam, stand vor einer leeren Maske und wusste nicht, ob es geklappt hat.

## 26.09.01b

### Added

- **Zweite Stufe der Anmeldung (TOTP).** Im Profil einzurichten: QR-Code scannen oder das Geheimnis abtippen — beides steht nebeneinander, der Kopierknopf ist derselbe wie überall in der Anwendung. Danach verlangt die Anmeldung zusätzlich zum Kennwort einen Einmalcode aus der Authentifizierungs-App.
  - **Bestätigen, bevor es scharf wird.** Zwischen „Geheimnis erzeugt" und „eingeschaltet" liegt ein stimmender Code. Wer seine App falsch eingerichtet hat, merkt es hier — und nicht bei der nächsten Anmeldung, ausgesperrt.
  - **Acht Wiederherstellungscodes**, einmal angezeigt, jeder genau einmal gültig. Ohne sie wäre ein verlorenes Telefon in einem Werkzeug voller Kundenkennwörter ein verlorener Zugang.
  - **Ein Administrator kann sie zurücksetzen** — der Fall dahinter ist banal und häufig: neues Telefon, Codes im Papierkorb. Es steht im Protokoll, wer es getan hat.
  - **Geheimnis und Codes liegen verschlüsselt** und sind vom Aktivitätsprotokoll ausgeschlossen. Wer das Geheimnis liest, kann jeden Code erzeugen — es ist so schützenswert wie ein Kennwort. Der Invariantentest, der verschlüsselte Spalten selbst findet, greift dafür automatisch.
  - **Der QR-Code entsteht hier**, nicht bei einem Dienst. Ein Geheimnis an einen fremden Server zu schicken, nur damit er ein Bild daraus malt, wäre das Gegenteil dessen, was diese Stufe leisten soll.
  - **Auch der Einmalcode ist gebremst**: fünf Fehlversuche, dann eine Viertelstunde Pause. Sechs Ziffern sind eine Million Möglichkeiten — ohne Zähler in Ruhe durchzuprobieren.
  - Zwischen Kennwort und Code ist niemand angemeldet: In der Sitzung steht nur, **wer** hereinmöchte.
  - Neu dabei: `pragmarx/google2fa` und `bacon/bacon-qr-code`.

- **Ein Administrator kann sie verlangen.** Haken im Benutzerformular: „Zweite Stufe der Anmeldung verlangen". Der Benutzer kommt nach der Anmeldung dann nur bis zu seinem eigenen Profil, bis er eine App verbunden hat — mit einem Hinweis, der sagt warum. Abschalten kann er sie danach nicht, sonst wäre die Pflicht ein Knopf, den man einmal drückt.
  - Die Sperre hängt in der Middleware-Gruppe `web`, nicht an einzelnen Routen: sonst bliebe über `/livewire/update` weiterhin jede Liste und jedes Formular erreichbar, und die Pflicht wäre eine Anzeige.
  - Eine Umleitung, kein 403: Der Benutzer hat nichts falsch gemacht, ihm fehlt ein Schritt — und der steht genau dort, wohin er geschickt wird.
  - Die Benutzerliste zeigt drei Zustände statt zwei: `—`, „verlangt, offen", „eingerichtet". Zwischen verlangt und eingerichtet sitzt genau der Benutzer, der noch nichts getan hat.
  - Auf der Demo wird niemand gezwungen: Dort teilen sich alle Besucher einen Zugang, der erste mit einer App sperrte alle übrigen aus.

## 26.09.01

### Security

- **Anmeldung: die Bremse gegen Durchprobieren greift jetzt auch beim Kennwort-Spraying.** Es gab bisher genau einen Zähler, und der stand auf `Nutzername|IP`. Wer *ein* gängiges Kennwort gegen *viele* Nutzernamen probiert, löste ihn nie aus — jeder Name bekommt einen eigenen, frischen Zähler. Daneben steht jetzt ein zweiter, der nur die Herkunft kennt: 30 Fehlversuche je IP über alle Konten hinweg. Bewusst hoch, weil ganze Büros hinter einer IP hängen; eine erfolgreiche Anmeldung leert ihn wieder, damit ein vertippter Vormittag niemanden dauerhaft aussperrt.
- **Die Sperre hält eine Viertelstunde statt einer Minute.** Der Laravel-Standard erlaubte nach 5 Fehlversuchen schon nach 60 Sekunden die nächsten fünf — 300 Versuche pro Stunde und Konto. Jetzt sind es 20.
- **Kennwort vergessen, Kennwort zurücksetzen und die Kennwortabfrage sind gedrosselt** (5 Versuche je Viertelstunde). Ungedrosselt ließen sich damit Reset-Mails auslösen, vorhandene Nutzernamen abfragen und Zurücksetz-Token raten.
- **Eine Sitzung überlebt die Kennwortänderung ihres Nutzers nicht mehr.** `AuthenticateSession` hängt jetzt in der Middleware-Gruppe `web` und bindet jede Sitzung an den Kennwort-Hash ihres Nutzers. Ändert der Nutzer oder ein Administrator das Kennwort, verfallen alle übrigen Sitzungen — bisher überlebte eine gestohlene Sitzung jede Kennwortänderung, und es gab **keinen Weg, sie loszuwerden**. Bewusst in der Gruppe und nicht an einzelnen Routen: so greift es auch für Livewire, das über `/livewire/update` läuft. Bestandssitzungen fliegen dabei nicht raus, sie bekommen den Hash beim nächsten Aufruf.
- **Die Selbstregistrierung ist entfernt.** `/register` stand offen, obwohl Nutzer in dieser Anwendung ein Administrator anlegt. Ein Konto entstand dabei ohnehin nie — die Route lief in einen Fehler, weil sie keine Rolle vergibt. Übrig blieb eine öffentliche Seite, die es nicht geben sollte.

- **Die Rollennummern 98 und 99 sind aus dem Benutzerformular verschwunden.** `UserRequest` verlangte einen Kunden, wenn die Rolle 98 oder 99 war — feste Nummern aus einer Zeit vor dem Rollen-Adminbereich. Rollen bekommen dort fortlaufende Nummern; solche Rollen gibt es in keiner Installation, die Regel war **nie wahr**. Es war dieselbe tote Annahme, an der auch das Gate `isCustomer` hing (siehe 26.08.31). An ihrer Stelle steht jetzt, was wirklich zählt: die Kundennummer muss zu einem Kunden gehören, den es gibt und der nicht im Papierkorb liegt — geprüft wurde das bisher gar nicht. Dasselbe für die Rolle.
- **Die Kundenauswahl sagt jetzt, was sie bedeutet.** Sie bot „Kein Kunde" an. Wer das aus Versehen stehen lässt, legt einen Benutzer an, der die Daten **aller** Kunden sieht — das steht jetzt dran.

### Changed

- **Vom Administrator vergebene Kennwörter müssen so lang sein wie selbst gesetzte.** Im Adminbereich reichten sechs Zeichen, im eigenen Profil galten acht. Jetzt gilt überall dieselbe Regel.
- Die Sperrmeldung der Anmeldung stand auf Englisch — sie ist jetzt deutsch und nennt Minuten statt Sekunden.

## 26.08.31

### Security

- **Drei Wege, auf denen ein Kunde Daten eines anderen Kunden sehen konnte.** Gefunden beim gezielten Angriff auf die Mandantentrennung — mit einem Nutzer, der **alle Rechte** hatte, damit ihn nur die Trennung aufhält und nicht ein fehlendes Recht.
  - **Die Geräteliste.** `ObjektListe`, mit einem fremden Kunden aufgerufen, lieferte dessen Geräte aus — samt **Benutzername, Kennwort und Seriennummer**.
  - **Die Dateiliste** gab die Dateien des fremden Kunden heraus, **die VLAN-Liste** seine Netze samt Adressen.
  - **Die Kundensuche** zeigte einem Kundennutzer die Namen fremder Kunden.
  - Warum die Route allein nicht reicht: Die `isCustomer`-Middleware hängt an den Routen, **Livewire ruft aber über `/livewire/update`** — dort läuft sie nicht. Wer den Kunden als Parameter entgegennimmt, nimmt ihn von außen entgegen und muss selbst prüfen. `RackEditor`, `PatchPanelPorts` und der Erstaufnahme-Assistent taten das längst; sechs weitere Komponenten nicht.
  - Die Prüfung steht jetzt als `GehoertZumKunden` an einer Stelle und wird von acht Komponenten benutzt. Die Blöcke für Zugangsdaten und IP-Adressen prüfen zusätzlich beim Einhängen statt erst bei der Aktion: Ein Block, der sich mit einem fremden Gerät überhaupt aufbauen lässt, hat schon zu viel gesagt.
  - **Eine Invariante hält es offen:** Ein Test geht alle Livewire-Komponenten durch und verlangt von jeder, die einen Kunden entgegennimmt, eine Prüfung. Wer morgen eine hinzufügt, merkt es sofort.
  - Und ein Test prüft, dass der Angreifer wirklich alle Rechte hat — ein erster Anlauf gab ihm versehentlich gar keine, und die Tests wären grün gewesen, ohne irgendetwas zu beweisen.

### Fixed

- **Ein Kundennutzer wurde nicht als solcher erkannt.** Das Gate `isCustomer` fragte nach den Rollen-Ids **98 und 99** — aus einer Zeit mit festen Rollen. Rollen entstehen längst im Adminbereich und bekommen dort fortlaufende Nummern; die Kundenrollen dieser Installation tragen 11 und 12. Das Gate war damit in jeder echten Installation **nie wahr**.
  - Folge: Die Kopfzeile bot jedem Kundennutzer **Kundensuche, globale Suche und Fernwartung** an — Wege, die für ihn auf eine Weiterleitung oder eine 403-Seite führen.
  - Es fragt jetzt nach der gesetzten `customer_id` — dieselbe Frage, die die `isCustomer`-Middleware und die API längst so stellen.
  - `isCustomerR` und `isCustomerRW` sind entfallen. Sie hingen an denselben Nummern, und ihre einzige Verwendung (der Neu-Knopf) steht ohnehin hinter der Rechtematrix. Ob jemand nur lesen darf, sagt die Rechtematrix.
  - Der vorhandene Test legte eigens eine Rolle mit der Id 99 an und hielt die Abhängigkeit im Kommentar fest — er bewies damit nur, dass es funktioniert, *wenn* die Rolle zufällig diese Nummer hat. Jetzt prüft er den wirklichen Fall: eine frisch angelegte Rolle.

- **Der Verweis auf die Fernwartung stand im Menü ohne das zugehörige Recht.** Die Route verlangt `remote_search`, der Menüpunkt fragte nicht danach — an drei Stellen (Kopfzeile, schlanke Kopfzeile, Seitenleiste). Wer das Recht nicht hat, landete auf einer 403-Seite.

- **„Daschboard"** hieß der Tooltip in der Kopfzeile — samt Eintrag in der Übersetzungsdatei.

- **Ein Gerät durfte ohne Kennwort nicht dokumentiert werden — je nach Gerät.** Ein Etikettendrucker im Netz hat oft gar keinen Login; speichern ließ er sich trotzdem nicht, während Switch, Kamera und Accesspoint es erlaubten. Dahinter standen **fünf verschiedene Schreibweisen** derselben Regel (`required|max:255`, `nullable|max:255`, `nullable`, `max:255` und die leere Zeichenkette).
  - Jetzt gilt ein Prinzip: **Ein Gerät darf ohne Login dokumentiert sein, ein reiner Zugangsdatensatz nicht.** Pflicht bleibt das Kennwort nur bei Webzugang und DynDNS — dort ist es der Gegenstand.
  - Fünfzehn Regeln angeglichen; zwei davon hatten gar keine Längenprüfung.
  - Ein Test hält die Regel als Invariante fest, statt sie an einer Stelle zu prüfen.

- **Telefon, DECT und Ansprechpartner hießen „#15".** Im Protokoll, im Papierkorb und in der globalen Suche. Aufgefallen bei der Suche nach einer MAC-Adresse: Sie fand das richtige Telefon und nannte es „#14".
  - `config('custom.name_fields')` kannte weder `extension` noch `role` — beide stehen jetzt darin, und zwar **vor** `username`: Ein Telefon führt beides, und „admin" wäre die schlechtere Auskunft.
  - Ansprechpartner und TK-Anlage haben ein eigenes `protokollName()` bekommen. Die Anlage hieß vorher tatsächlich „admin" — der Rückfall auf den Benutzernamen, und damit schlimmer als eine Nummer.

- **Die globale Suche benutzte die zentrale Namenslogik gar nicht.** Sie hatte eine eigene Kette `name ?? ssid ?? wan_ip ?? '#id'` und ging damit an `name_fields` und `protokollName()` vorbei. Jetzt fragt sie dieselbe Quelle wie Protokoll und Papierkorb.
  - Die Netzwerkdose wäre dabei beinahe abhandengekommen: Sie führt die Methode nicht, weil sie nicht protokolliert wird. Ein Test prüft jetzt für **jede** der 25 Trefferarten, dass sie sich benennen kann.

- **Die Vorschau zeigte beim Öffnen die falsche Blende.** `mount()` reichte sieben Werte an eine Methode mit drei Parametern durch — die eigene Zeichnung landete auf dem Platz der Portanzahl und ging verloren. Sichtbar wurde es erst nach einer Änderung im Formular, weil dann der andere Weg griff. Ein Test hält jetzt fest, dass ein USW-Pro-48-PoE schon beim Öffnen seine achtundvierzig Buchsen zeigt.

### Changed

- **Aufgeräumt, was beim Umbau liegen geblieben ist.**
  - **Die Erklärungen in `config/custom.php` standen über den falschen Schlüsseln.** Neun Kommentarblöcke waren von ihren Arrays abgerückt — über `secret_columns` stand die Erklärung zum SSH-Verfahren, über `activity_events` die zu den Admin-Rechten. Jeder sitzt jetzt wieder bei dem, was er beschreibt. Ein zehnter beschrieb eine Konfiguration, die es seit dem Rack-Katalog als Tabelle nicht mehr gibt; der ist weg.
  - **Die Bildverwaltung stand zweimal im Code.** `bildUrl()`, `bildPfad()`, `bildLoeschen()` und der Haken zum Aufräumen beim Löschen waren im Rack-Katalog und in den Gerätemodellen wortgleich — jetzt einmal als `HatBild`. Das Model nennt nur noch Ordner und Route.
  - **Dasselbe in den beiden Admin-Controllern**: Ablegen, Ersetzen, Entfernen und Ausliefern liegen als `PflegtBilder` an einer Stelle. Beim Anlegen entsteht das Bild jetzt zusammen mit dem Datensatz, statt in einem zweiten Schritt nachgereicht zu werden.

## 26.08.29

### Added

- **Gerätemodelle: ein Foto, das für alle Kunden gilt.** Wer bei einem Kunden eine „APC Smart-UPS 1500" fotografiert, sieht das Bild bei jedem weiteren Kunden wieder, bei dem dieselbe USV steht — und in Racks, die längst dokumentiert sind.
  - **Ohne neue Verknüpfung an neun Tabellen.** Hersteller und Modell stehen an jedem rack-fähigen Gerät längst als Felder; der Katalog wird darüber gefunden. Niemand muss etwas nachpflegen, und im Geräteformular ändert sich nichts.
  - Verglichen wird über normalisierte Schlüssel — kleingeschrieben, getrimmt, Mehrfachleerzeichen zusammengezogen. „APC " und „apc" sind dasselbe. Mehr Normalisierung wäre Raten: „Hewlett Packard" und „HP" zusammenzuführen ist eine Entscheidung, die ein Mensch trifft.
  - Der Gerätetyp gehört zum Schlüssel. Ohne ihn träfe ein Switch „RS-1000" auf einen Recorder gleichen Namens.
  - Die Reihenfolge beim Zeichnen: Foto des Katalogelements, sonst Foto des Gerätemodells, sonst die gezeichnete Blende — in der Rack-Ansicht, im Editor und im PDF.
  - **Die Höhe kommt mit.** Switch, NAS, Router, USV und Recorder führen keine eigene Höheneinheit; beim Einbau zählt jetzt die des Modells. Die eigene Angabe eines Geräts geht ihr weiterhin vor.
  - Das Bild geht ohne Mandantenprüfung heraus, anders als Gerätedaten und mit Absicht: Es zeigt eine Frontblende, die im Katalog des Herstellers genauso steht, und soll gerade kundenübergreifend gelten.
  - **Hochladen geht auch direkt im Geräteformular**, nicht nur im Adminbereich: Wer eine USV anlegt, gibt das Foto gleich mit. Es landet trotzdem im Modellkatalog, nicht am Gerät — der Hinweis darunter sagt das. Das Feld erscheint abgeleitet aus `rack_device_types` (wer im Rack sitzt, hat eine Frontblende) und nur mit dem Recht `admin_catalog`: Das Bild gilt für alle Kunden, wer nur die eigene Dokumentation pflegen darf, soll nichts hinterlegen, das anderswo erscheint. Ohne Hersteller wird es abgelehnt, statt im Nichts zu landen.
  - **Der Treffer meldet sich beim Tippen**, nicht erst beim Speichern: Sobald Hersteller und Modell stehen, erscheint ein bereits hinterlegtes Bild samt „Hinterlegt für APC Smart-UPS 1500". Das ist die Rückmeldung, die den Abgleich über die Schreibweise erst brauchbar macht — man sieht, dass er getroffen hat, und lädt nichts doppelt hoch. Laufend übertragen werden nur diese beiden Felder und nur bei Geräten mit Frontblende; jedes andere Feld bliebe sonst eine Serveranfrage je Tastenpause schuldig.
  - Alle Einträge werden einmal je Anfrage geladen; eine Rack-Ansicht mit zwanzig Einbauten bräuchte sonst zwanzig Abfragen. Abgelegt im Container, nicht in einer statischen Eigenschaft — die hätte den Rollback zwischen zwei Tests überlebt.

- **Vier Gerätemodelle als Beispiel, jedes mit einer eigenen Zeichnung.** Zwei UniFi-Switche, eine Rack-USV und ein 1-HE-Server — sie stehen im Demo-Datensatz und sitzen im Schrank „Rack HH-01".
  - Der Zweck ist zu zeigen, was der Katalog kann: Ein Gerät, dessen Hersteller und Modell zu einem Eintrag passen, bekommt dessen Blende — bei jedem Kunden, ohne dass jemand etwas verknüpft.
  - Die Zeichnungen liegen als Blade-Ansichten im Projekt (`resources/views/components/rack/modelle/`) und werden über einen Schlüssel aus `config('custom.rack_model_drawings')` gewählt. Der Wert kommt aus der Datenbank und wird deshalb gegen die Liste geprüft, bevor `@include` ihn sieht — sonst wäre der Name einer Blade-Datei von außen bestimmbar.
  - **Kein Herstellerkatalog.** Vier reichen als Beispiel; ein vollständiger wäre Pflegearbeit ohne Ende. Alles Übrige zeichnet die Blende des Gerätetyps.
  - Ein Eintrag beschreibt nur, **wie das Gerät aussieht**: Hersteller, Modell, Höhe, Einbautiefe und dann entweder eine Zeichnung oder ein Bild. Portzahlen und Ähnliches standen zwischenzeitlich dort und sind wieder weg — an einer USV gibt es nichts zu zählen, und ein Feld, das nur bei Switchen etwas bewirkt, gehört nicht in ein Formular für alle Gerätetypen.

- **Die erlaubten Bildformate stehen an einer Stelle.** `config('custom.bild_formate')` statt zweier wortgleicher Konstanten in `AdminAllgemein` und `RackCatalogItem` — ein später erlaubtes Format hätte man sonst an drei Stellen nachtragen müssen.

- **Der Rack-Katalog nimmt eigene Bilder auf.** Wer ein Gerät hat, das keine der elf Zeichnungen trifft, lädt ein Foto der Frontblende hoch; es tritt in der Vorschau, im Rack und im PDF an die Stelle der Zeichnung.
  - Die gewählte Darstellung bleibt daneben stehen. Wird das Bild wieder entfernt, zeichnet die Frontansicht sofort weiter — es geht nichts verloren.
  - Kein SVG, aus demselben Grund wie bei den Logos: Eine SVG-Datei darf Skript enthalten, und von derselben Herkunft ausgeliefert wäre das ausführbarer Code in einer Dokumentation, in der Kennwörter stehen.
  - Die Datei liegt privat und geht durch einen Controller heraus, wie jede Datei dieser App. Die Adresse steht hinter `auth`, aber außerhalb des Adminbereichs: Gepflegt wird das Bild dort, zu sehen ist es in jedem Rack — auch bei einem Kunden, der den Adminbereich nie betreten darf.
  - Ein gelöschter Katalogeintrag nimmt sein Bild mit. Bereits verbaute Elemente bleiben stehen; sie haben Bezeichnung, Höhe und Darstellung beim Einbau kopiert und erscheinen dann wieder als Zeichnung. Der Hinweis über dem Löschen-Knopf sagt das jetzt auch — er versprach vorher einen Papierkorb, den es für Katalogelemente nie gab.

- **Die Vorschau zeigt einen kleinen Schrank statt einer freistehenden Blende.** Erst darin ist zu sehen, wie viel Platz ein Element einnimmt: Eine Blende allein sieht bei einer und bei drei Höheneinheiten gleich aus.
  - Sie zeichnet bei jeder Änderung neu, statt alles vorzuhalten. Ein Patchfeld mit 2 HE hat zwei Portreihen, keine gestreckte — alle elf Darstellungen in allen acht Höhen vorab auszuliefern wären 737 KB Auszeichnung auf einer Formularseite gewesen.
  - Darunter steht die empfohlene Auflösung, mitlaufend zur eingestellten Höhe: **1200 × 110 Pixel je HE**. Eine 19-Zoll-Blende ist 482,6 mm breit und 44,45 mm je Höheneinheit hoch, also 10,86 : 1 — glatt aufgehen würde nur 1086 × 100. Zwei krumme Zahlen für 0,5 % Genauigkeit sind es nicht wert.
  - Die Liste zeigt dieselbe Blende als Vorschaubild in der ersten Spalte.

### Changed

- **Die gezeichnete Frontansicht hat jetzt das Seitenverhältnis einer echten Blende.** Ihre Breite richtete sich nach der Umgebung, die Zeilenhöhe stand fest bei 2 HE-Zeilen zu je 2 rem — gemessen kam eine 1-HE-Blende so auf 7,5 : 1 statt 10,9 : 1 und sah aus wie ein Klotz. Der Schrank bekommt seine Breite jetzt aus der Zeilenhöhe (das 10,857-fache, plus Skala, Schienen, Polster und Rahmen); auf der Rack-Seite stimmt das Verhältnis damit exakt. Zeile für Zeile liegt er weiter auf gleicher Höhe mit dem Schema daneben — die Zeilenhöhe blieb unangetastet.

- **Ein Katalogelement darf höchstens acht Höheneinheiten hoch sein**, vorher 42. Größer gibt es 19-Zoll-Einbauten praktisch nicht, und es ist dieselbe Grenze, die der Rack-Editor beim Ändern einer Höhe schon zog.

- **Das Abbild wird auf nativen Runnern gebaut, je Architektur einer.** Vorher lief arm64 emuliert auf einem amd64-Runner: Ein Lauf brauchte rund zwanzig Minuten, und `npm ci` blieb dabei zeitweise ganz stehen — der Grund, warum die Bauschritte inzwischen ohnehin auf der Architektur des Bauknechts laufen.
  - Beide Architekturen bauen **parallel** und legen ihr Ergebnis nur unter dem Digest ab, ohne Markierung. Erst ein dritter Job fasst sie zu einem Manifest zusammen und setzt `latest` und die Version. Würde jeder Lauf gleich markieren, überschriebe die zweite Architektur die erste.
  - Der Cache liegt getrennt je Architektur — ein gemeinsamer überschriebe sich gegenseitig, weil die Zwischenstufen verschiedene sind.
  - Zum Schluss wird nachgesehen, was tatsächlich in der Registry steht: Fehlt eine der beiden Architekturen im Manifest, bricht der Lauf ab. Vorher hätte niemand gemerkt, wenn nur die Hälfte oben angekommen wäre.
  - QEMU entfällt damit vollständig. Native ARM-Runner sind für öffentliche Repositories kostenfrei.

- **Das Docker-Abbild liefert jetzt über nginx und php-fpm aus.** Vorher lief darin `php artisan serve` — ein Entwicklungswerkzeug: einzelthreadig, ohne Opcache, und für jede CSS-Datei sprang PHP an.
  - **nginx liefert `/build/` selbst aus**, mit dauerhaftem Cache-Header. Die Dateinamen tragen einen Inhalts-Hash, ein Neuladen kann also nichts Veraltetes bringen.
  - **Opcache ist an**, mit abgeschalteter Zeitstempelprüfung: Im Container ändert sich der Quelltext nie.
  - **Upload-Grenzen passen zur Anwendung.** Sie nimmt Dateien bis 20 MB an; nginx und PHP erlauben 24 MB. Vorher hätte nginx bei 1 MB abgebrochen — mit einem Serverfehler statt einer Meldung.
  - Ein Container bleibt es trotzdem, gehalten von supervisord. Sauberer wären zwei, einer je Prozess; dann wäre das veröffentlichte Abbild allein aber nicht lauffähig, und genau das soll es sein.
  - Der Rauchtest beweist die Umstellung, statt sie anzunehmen: Er zieht den gehashten CSS-Namen aus der Seite, prüft Auslieferung und Cache-Header und fragt Opcache ab. Ohne diese Prüfungen wäre ein stillschweigend nicht greifender nginx-Block nicht aufgefallen.

## 26.08.28

### Changed

- **Die Migrationen sind von 117 auf 78 zusammengezogen.** Sie bleiben, was sie waren — lesbare Schritte mit `migrate:rollback` —, nur ohne die Spuren der Entstehungszeit. Drei Gruppen sind verschwunden:
  - **Abgeschlossene Lebensläufe:** Tabellen, die angelegt und später wieder entfernt wurden (`rooms`, `rack_cabinets`, `rack_devices`, `securepoint_utms`, `radiocenters`, `login_nas`, `login_recorders`) — samt der Migrationen, die sie anlegten, umzogen und löschten. Auf einer frischen Datenbank entstanden sie nur, um kurz darauf zu verschwinden.
  - **Nachträgliche Spalten** wanderten in die Anlege-Migration ihrer Tabelle, mitsamt ihren Kommentaren. Ausgenommen sind Spalten mit Fremdschlüssel auf eine *später* entstehende Tabelle: `vms.cluster_id` verweist auf `clusters`, das Jahre nach `vms` angelegt wird — nach vorn gezogen ließe sich die Bedingung nicht bilden.
  - **Nachrüstungen für Bestandsdaten:** sechs Rechte-Migrationen (dieselben Rechte legt der `PermissionRoleSeeder` aus `config/custom.php` an) und vier Backfills, die auf einer leeren Datenbank nichts tun.
  - Belegt statt behauptet: Das Schema vor dem Umbau wurde gesichert und danach neu erzeugt. **66 Tabellen, jede mit exakt denselben Spalten, Typen, Vorgaben und Schlüsseln.** Nur die physische Spaltenreihenfolge weicht ab, wo eine Spalte jetzt beim Anlegen statt später entsteht — für die Anwendung ohne Bedeutung.
  - Sechs Tests prüften eine einmalige Datenmigration, indem sie deren Datei luden. Ihr Gegenstand ist entfallen, sie mit ihm.

## 26.08.28

### Added

- **Das Docker-Abbild wird auf Docker Hub veröffentlicht.** Gebaut und geprüft wurde es schon vorher — es lag nur nirgends, wo man es herunterladen kann.
  - Veröffentlicht wird bei jedem Push auf `main` und bei jedem `v*`-Tag, **nachdem** der bestehende Rauchtest durchgelaufen ist: Container hochfahren, anmelden, Demo-Daten prüfen. Ein kaputtes Abbild kommt so nicht in die Registry.
  - Der Push-Auslöser hat seinen Pfadfilter verloren. Das Abbild trägt die Anwendung in sich, also macht **jede** Codeänderung ein veröffentlichtes `latest` veraltet — vorher wurde nur bei Änderungen am Dockerfile gebaut.
  - Zwei Markierungen: `latest` für den letzten Stand, `YY.MM.DD` für einen festen. Die Version kommt aus dem Changelog, derselben Quelle, aus der die Oberfläche sie liest.
  - Gebaut für `amd64` und `arm64`, damit es ohne Emulation auch auf Apple-Silicon und Raspberry läuft. Die beiden Bauschritte davor — Composer und Vite — laufen dabei ausdrücklich auf der Architektur des Bauknechts: Sie erzeugen PHP-Quelltext, CSS und JavaScript, alles ohne Architekturbezug. Emuliert blieb `npm ci` für arm64 praktisch stehen.
  - Ohne hinterlegte Zugangsdaten wird nur gebaut, nicht veröffentlicht — der Lauf bleibt grün und sagt, welche Geheimnisse fehlen. Sonst wäre der Workflow in jedem Klon rot, ohne dass etwas kaputt ist.
  - `docker/HUB.md` wird als Beschreibung auf die Docker-Hub-Seite gespiegelt: Compose-Beispiel, Umgebungsvariablen und der Hinweis, dass ein fester `APP_KEY` Pflicht ist, sobald echte Daten im Spiel sind. Der Bildname im Beispiel steht nicht im Quelltext, sondern wird beim Hochladen aus demselben Geheimnis eingesetzt, aus dem auch der Bildname gebildet wird — so zeigt das Beispiel immer auf genau das Repository, auf dem es steht.
  - Das Aktualisieren der Beschreibung ist ein **eigener Job ohne Bindung an den Bau**: Eine Textänderung an der Seite zieht sonst zwanzig Minuten Multi-Arch-Bau nach sich.

## 26.08.27d

### Removed

- **Altlasten aufgeräumt.** Nach dem Entfernen der alten Seiten blieb Totes zurück; eine systematische Suche fand es:
  - **37 Blade-Komponenten** ohne einen einzigen Verwender — vor allem `x-create.select.*`, `x-edit.select.*`, `x-create.hidden/radio` und acht ungenutzte SVGs. Die Prüfung berücksichtigt, dass Symbole dynamisch über `svg="svg.login"` eingebunden werden; sie lief bis zum Stillstand, weil jede Löschung die nächste Komponente verwaisen lassen kann.
  - **Zwei leere Controller** (`PermissionController`, `RoomController`) — Klassen ohne Inhalt, ohne Route, ohne Referenz.
  - Die veröffentlichte View von **`livewire-ui-modal`**, einem Paket, das seit langem nicht mehr installiert ist.
  - **`alpinejs` und `@fontsource/space-grotesk`** aus `package.json`: Alpine kommt gebündelt aus Livewire, die Schrift wurde nie eingebunden. Das gebaute JavaScript ändert sich dadurch nicht — der Beweis, dass beides ungenutzt war.
  - **`rooms`**: Model, Factory, API-Controller, drei API-Routen, die Relation am Standort und die (leere) Tabelle. Eine Oberfläche gab es nie; der Rack-Standort ist bewusst Freitext. Im API-Controller für Accesspoints entfielen damit auch die Parameter `$site` und `$room`, die keine Route je lieferte und keine Methode je benutzte.

  Das gebaute CSS schrumpft von 78,6 auf 76,7 KiB.

## 26.08.27c

### Added

- **Eigene Fehlerseiten für 403, 404, 419, 500 und 503.** Gemeldet: Die 404 blieb hell, obwohl die Anwendung auf dunkel stand. Der Grund lag nicht am Hell/Dunkel-Schalter, sondern daran, dass es gar keine eigene Seite gab: Laravels eingebaute Seite bringt ihr eigenes CSS mit und entscheidet über `prefers-color-scheme` — also nach der Einstellung des **Betriebssystems**. Die Anwendung schaltet dagegen über die Klasse `dark` aus `localStorage`. Wer die Anwendung auf dunkel, das System aber auf hell gestellt hatte, bekam eine grelle Seite.
  - Die neuen Seiten nutzen dasselbe Skript wie die Layouts und dasselbe CSS wie der Rest — sie folgen also der Anwendung.
  - Bewusst ohne Navigation und ohne Livewire: Eine Fehlerseite muss auch dann noch stehen, wenn darunter etwas kaputt ist. Der eigene Anwendungsname wird über `rescue()` geholt, sonst risse ein Datenbankausfall genau die Seite mit, die ihn melden soll.
  - Zwei Wege hinaus: zur Startseite und dahin, wo man herkam.
  - Dazu ein trockener Satz, klein und grau unter der Erklärung, **zufällig aus einer Liste je Statuscode** — beim zweiten Mal steht etwas anderes da. Auf der 404 etwa „Der Vorgänger wusste, wo das war.", auf der 500 „Es lag nicht am DNS. Diesmal wirklich nicht." Die Sprüche stehen in `config/custom.php`, nicht im Blade.

## 26.08.27b

### Removed

- **Die alten /create- und /edit-Seiten sind weg.** Seit Listen und Formulare als Modal laufen, verlinkte sie niemand mehr — erreichbar waren sie nur noch, wer die Adresse von Hand eintippte. Entfernt: 78 Ansichten, 195 Controller-Methoden und die zugehörigen Routen für 39 Typen. Was bleibt: **Rack und Patchfeld** haben kein Modal und behalten ihre Seiten, ebenso der gesamte Admin-Bereich.
  - Die Ressourcen-Routen dieser Typen stehen jetzt auf `only(['index'])` statt `except(['show'])` — die Adressen liefern 404 statt einer Seite, die niemand mehr pflegt.
  - Die `index()`-Methoden luden zum Teil noch Daten, die die Livewire-Liste gar nicht verwendet — bis zu einer `paginate(25)`-Abfrage bei **jedem** Seitenaufruf. Sie holen jetzt nur noch den Kunden.
  - 93 Übersetzungen entfielen mit den Seiten („Neuer Server", „Stammdaten speichern" …). Gefunden hat sie der Übersetzungstest selbst.
  - Der Dokumentations-Assistent verlinkte erfasste Einträge auf ihre Bearbeiten-Seite; er führt jetzt in die Liste, in der das Modal sitzt.

### Fixed

- **Regeln, die ein anderes Feld heranziehen, griffen im Modal nicht.** „Das Gateway muss im Netz liegen" baut seine Prüfung aus `$this->input('subnet')` des Requests — im Modal hatte der aber keine Eingabe, die Regel prüfte gegen `null` und ließ alles durch. Ein Gateway außerhalb des Netzes wurde damit stillschweigend gespeichert. Das Formular reicht die Werte jetzt an den Request weiter.

- **Ein gelöschter Cluster ließ seine Server mit ungültiger Zugehörigkeit zurück.** Das Auflösen steckte im `destroy` des Controllers, nicht im Model — beim Löschen über das Modal lief es deshalb nie. Es steht jetzt am Cluster selbst und gilt damit auf jedem Weg.

## 26.08.27

### Fixed

- **Der Status eines AD-Benutzers stand im Fenster anders als in der Liste.** Gemeldet für einen aktiven Benutzer, der als `—` erschien. Dahinter steckten zwei Fehler:
  - **Ein deaktivierter Benutzer wurde im Fenster als „Aktiv" angezeigt** — der schwerere Fall. Beim Laden ins Formular wird jeder Wert in Text gewandelt, und `(string) false` ergibt in PHP `''` und nicht `'0'`. Das Feld kam leer an, und eine Auswahl ohne passenden Eintrag zeigt ihren ersten — also das Gegenteil des Gespeicherten. Betraf jedes Ja/Nein-Feld in einer festen Auswahlliste.
  - **Ohne gespeicherten Status behauptete das Fenster einen.** Ist nichts hinterlegt, gibt es jetzt einen Eintrag „— unbekannt —" statt stillschweigend „Aktiv". Bloßes Speichern macht daraus auch keine Aussage.

### Changed

- **Der Status steht als Haken oder Kreuz in der Liste, nicht als Wort.** Ein Zeichen erfasst man beim Überfliegen schneller als „Aktiv"/„Deaktiviert", und die drei Zustände — aktiv, gesperrt, unbekannt — sind auf einen Blick unterscheidbar. **Gesperrte Zeilen treten zurück**: Das Konto ist dokumentiert, aber selten das, wonach man sucht. Ein *unbekannter* Status graut nichts aus — nicht dokumentiert heißt nicht unwichtig. Die Bedeutung hängt nicht allein an der Form: Jedes Zeichen trägt seine Beschriftung für Vorlesewerkzeuge und für alle, denen Grün und Grau gleich aussehen.

- **Die AD-Demo-Daten sehen aus wie ein echtes Verzeichnis.** Vorher wurden Vorname, Nachname, Benutzername und E-Mail unabhängig voneinander gewürfelt — in einer Zeile standen damit drei verschiedene Personen, und jeder hatte eine eigene Mail-Domain.
  - Benutzername und Adresse kommen jetzt aus dem Namen: „Anna Berger" wird `anna.berger` und `anna.berger@kunde.de`. Umlaute werden dabei aufgelöst.
  - **Eine Domain für alle** — daran erkennt man, dass die Daten zusammengehören.
  - Nicht jeder hat eine Adresse: Dienstkonten (`svc-backup`, `svc-scan`) tragen weder Namen noch Mail. Das ist der Fall, in dem eine leere Spalte richtig ist.
  - **Alle drei Status-Zustände kommen vor**, ausdrücklich gemischt statt dem Zufall überlassen: sieben aktive, zwei gesperrte Ex-Mitarbeiter, einer ohne dokumentierten Status. Vorher ließ die Factory `enabled` ganz offen — deshalb stand bei allen 35 Benutzern `—`. Aus echten Importen kam der Wert ohnehin, der Agent liest `Enabled` aus dem AD mit.

## 26.08.26

### Added

- **SSH-Schlüssel lassen sich dokumentieren.** Neue Liste unter *Logins → SSH-Schlüssel*, mit Name, Benutzer, Verfahren (Ed25519, ECDSA, RSA), öffentlichem und privatem Schlüssel, Passphrase und Beschreibung. Beide Schlüsselfelder sind mehrzeilig und in Festbreitenschrift — in einem einzeiligen Feld ist ein Schlüssel ein Strich, den man nicht prüfen kann.
  - Ein Schlüssel liegt technisch in derselben Tabelle wie die Logins und **hängt über dieselbe Verknüpfung an Geräten**. Damit gilt für ihn, was für ein Kennwort gilt: einmal dokumentiert, an drei Servern verknüpft, unter „Verwendet bei" sichtbar, wo er gilt. Ein eigener Speicher daneben hätte das Muster ein zweites Mal gebaut.
  - Getrennt sind die beiden nur in der Anzeige: Ein Schlüssel taucht nicht in *Logins Allgemein* auf und ein Kennwort nicht in der Schlüsselliste — „welcher Key gilt auf SRV-01?" ist eine andere Frage als „wie lautet das Kennwort?".
  - Der private Schlüssel wird verschlüsselt abgelegt, der öffentliche nicht: Der ist zum Verteilen da und muss durchsuchbar bleiben, damit man ihn in einer `authorized_keys` wiederfindet. Im PDF steht er deshalb auch — der private nicht, den bekäme man aus einem weitergereichten Dokument nicht mehr zurück.

- **Am Gerät sind Schlüssel als solche erkennbar.** Gemeldet: In der Auswahl „Vorhandenes Login" sah man einem Namen nicht an, ob dahinter ein Kennwort oder ein Schlüssel steckt.
  - Die Auswahl ist jetzt nach *Kennwörter* und *SSH-Schlüssel* gruppiert.
  - Verknüpfte Schlüssel tragen ein Kürzel **SSH** — in der Tabelle und auf der Gerätekarte. Ohne das stand unter „Passwort" eine Passphrase, ohne dass man es sehen konnte. Ein Schlüssel ohne Passphrase steht als „ohne Passphrase" da statt als Strich: Das ist eine Aussage, kein fehlender Wert.

- **SSH-Schlüssel führen ihren Fingerprint.** Der SHA256-Fingerprint steht in der Liste und ist **durchsuchbar** — das ist der Weg vom `SHA256:…` aus einer `authorized_keys` auf einem Server zurück zum dokumentierten Schlüssel.
  - Abgeleitet, nicht eingegeben: Er wird bei jedem Speichern aus dem öffentlichen Schlüssel neu berechnet und kann so nicht von ihm abweichen.
  - In der Liste steht er **gekürzt mit Kopier-Knopf**, vollständig im Tooltip. Ganz ausgeschrieben sind es 50 Zeichen ohne Trennstellen — die Spalte brach auf fünf Zeilen um und bestimmte die Zeilenhöhe, obwohl man den Wert nur beim Vergleichen liest. Das `SHA256:` davor ist bei jedem gleich und entfällt in der Anzeige.
  - Gerechnet wird in PHP, nicht über `ssh-keygen`: Es ist der Base64-Hash des Schlüsselblocks, ein Unterprozess je Listenzeile wäre unverhältnismäßig. Ein Test vergleicht das Ergebnis für Ed25519, RSA und ECDSA mit dem, was `ssh-keygen -lf` ausgibt.

- **Der Fingerprint steht auch am Gerät.** Im Zugangsdaten-Block und auf der Gerätekarte — dort, wo man ihn braucht: Auf dem Server liefert `ssh-keygen -lf ~/.ssh/authorized_keys` je Eintrag ein `SHA256:…`, und erst der Vergleich damit zeigt, ob ein dokumentierter Schlüssel wirklich noch installiert ist und ob dort einer liegt, den niemand kennt. Die Spalte erscheint nur, wenn am Gerät überhaupt ein Schlüssel hängt — bei reinen Kennwörtern wäre sie durchgehend leer.

- **Ein Schlüsselpaar lässt sich im Fenster erzeugen.** Knopf *Schlüsselpaar erzeugen* im SSH-Schlüssel-Fenster: füllt öffentlichen und privaten Teil, mit optionaler Passphrase. Der Kommentar am öffentlichen Schlüssel wird aus Benutzer und Name gebildet (`deploy@deploy-ci`) — daran erkennt man ihn später in einer `authorized_keys` wieder.
  - Erzeugt wird mit `ssh-keygen`, nicht in PHP nachgebaut. Das OpenSSH-Format des privaten Teils ist eine gepackte Binärstruktur, mit Passphrase kommen bcrypt-KDF und aes256-ctr dazu; ein hier nachgebauter Schlüssel wäre im Zweifel subtil falsch — und das fällt erst auf, wenn ihn nachts ein Server ablehnt. Die Tests lassen `ssh-keygen` das Ergebnis deshalb selbst wieder ableiten.
  - Erzeugen speichert nicht: Erst der Speichern-Knopf legt an. Beim Bearbeiten wird nachgefragt, sonst ist ein dokumentierter Schlüssel mit einem Klick weg.
  - Der Mechanismus ist allgemein (`erzeuger` in `config/forms.php`) — das Fenster kennt nur „dieser Typ hat einen Erzeuger", nicht dessen Innenleben.
  - Mehrzeilige Felder machen das Fenster jetzt auch beim **Anlegen** breit, nicht erst beim Bearbeiten.

### Fixed

- **Der Testlauf brach gelegentlich grundlos ab und blockierte damit den Deploy.** Die Benutzer-Factory zog `fake()->name()` für `username` — eine Spalte mit UNIQUE-Index. Ein Test, der in einer Schleife neunzehn Benutzer anlegt, traf damit rund einmal je 14 000 Läufe denselben Namen zweimal; bei mehreren Matrix-Jobs je Push passiert das irgendwann. Der Deploy hängt am grünen Testlauf und wurde dann übersprungen. Die Factory zieht jetzt eindeutig.

- **Ein Blade-Komponenten-Tag mit `@if` in den Attributen wird nicht übersetzt.** Der Erzeugen-Knopf stand dadurch wörtlich als `<x-input.button …>` im HTML und war unsichtbar. Der Test dazu rief die Methode direkt auf und lief grün — er prüft jetzt das gerenderte Fenster.

- **Ein Schlüssel am Gerät verwies auf die Login-Bearbeitung, die ihn nicht findet.** Die Seite bindet über die Login-Klasse, und die sieht keine Schlüssel — der Verweis lief auf 404. Er zeigt jetzt in die Schlüsselliste.

- **Schlüssel und Kennwörter haben getrennte Rechte, auch am Gerät.** Beide liegen in derselben Tabelle; über den Zugangsdaten-Block hätte bisher jeder mit Login-Recht auch die Schlüssel gesehen und verknüpfen können. Jetzt zeigt und akzeptiert der Block nur, was das eigene Recht abdeckt — die Prüfung sitzt in der Validierung, nicht nur in der Auswahlliste.

- **Feldbeschriftungen der Modale gingen am Übersetzungsabgleich vorbei.** Der Test findet Zeichenketten über `__('…')` im Code; Beschriftungen aus `config/forms.php` laufen aber erst zur Laufzeit hindurch und galten deshalb als unbenutzt. Jetzt werden sie mitgelesen — samt Platzhaltern und festen Auswahlwerten.

## 26.08.25

### Changed

- **Ein FTP-Server hat jetzt beliebig viele Zugänge.** Bisher hing genau ein Benutzername samt Passwort am Server selbst — in der Praxis hat derselbe Server aber einen Zugang für den Steuerberater, einen fürs Backup und einen für den Lieferanten. Wer den zweiten dokumentieren wollte, musste den Server ein zweites Mal anlegen.
  - Die Zugänge stehen **nicht** in einer eigenen FTP-Tabelle, sondern im selben Mechanismus wie bei Server, VM oder NAS: Einträge aus *Logins Allgemein*, per Verknüpfung an den Server gehängt. Ein erster Anlauf mit eigener Tabelle wäre genau das Muster gewesen, das im August für NAS- und Recorder-Logins abgeschafft wurde — ein zweiter verschlüsselter Speicherort mit eigener Pflege daneben.
  - Der praktische Gewinn: Dasselbe Dienstkonto auf drei Servern steht einmal da statt dreimal. Beim Kennwortwechsel gibt es eine Stelle zu ändern, nicht drei — und unter *Logins Allgemein* zeigt „Verwendet bei", auf welchen Servern es gilt.
  - Die Liste zeigt je Server seine Benutzernamen als Kürzel; die Kennwörter stehen im Bearbeiten-Fenster, nicht offen in der Liste.
  - Das Bearbeiten-Fenster des FTP-Servers ist breiter: Zwei Felder, darunter die Zugangsdaten — hier ist der Block der Inhalt und nicht ein Anhang. Die übrigen Gerätetypen behalten ihre Breite, dort tragen die Felder das Fenster.

### Fixed

- **Die Blöcke im Bearbeiten-Fenster richten sich jetzt danach, was ein Gerät wirklich führt.** Vorher zog `bloecke` immer beide Blöcke nach sich, IP-Adressen und Zugangsdaten. Für einen FTP-Server wäre das nicht nur überflüssig gewesen: Der IP-Block hätte eine Beziehung aufgerufen, die es am Model nicht gibt. Ein Test prüft jetzt beide Richtungen — fehlender Block und Block, der nicht hingehört.

- **Ein FTP-Server erscheint unter „Verwendet bei" mit seinem Host.** Die Bezeichnung fiel über Name, IP und ID — der FTP-Server hat nichts davon, dort stand „#1 (FTP-Server)".

## 26.08.23

### Added

- **Die Lizenzlisten lassen sich filtern und sortieren.** Suchen ging schon; was fehlte, war die Frage „welche Lizenz läuft demnächst aus?".
  - **Software-Lizenzen:** Laufzeit (abgelaufen, läuft in 30 oder 90 Tagen ab, läuft noch) und Abonnement (jährlich, monatlich), sortierbar nach Ablauf, Name oder Anlagedatum. „Läuft noch" schließt Dauerlizenzen ohne Enddatum ein — die laufen nicht ab und fielen sonst durch jedes Raster.
  - **Windows-Lizenzen:** nach Betriebssystem. Die Auswahl kommt aus dem Bestand, nicht aus dem ganzen Katalog — ein System ohne Lizenz wäre eine Zeile, die immer nichts findet.
  - **CAL-Lizenzen:** nur Sortierung, dort gibt es weder Laufzeit noch Auswahlfeld.
  - Die Filter sind in `config/forms.php` je Typ beschrieben, nicht fest verdrahtet: Jede der gut zwanzig anderen Listen kann welche bekommen, ohne dass Code dazukommt. Wo nichts beschrieben ist, erscheint auch keine Leiste.

- **Die Dateiliste lässt sich durchsuchen, filtern und sortieren.** Bei einem Kunden mit vielen Dateien war „wo ist der Wartungsvertrag von 2024?" bisher eine Blätterübung — und die zuletzt hochgeladene Datei stand ganz hinten.
  - **Suche** über Bezeichnung und Endung: „alle xlsx" ist eine echte Frage, deshalb zählt die Endung als Suchbegriff.
  - **Art**: PDF, Bild, Text, Tabelle, Archiv oder Sonstige. Die Endung wird gespeichert, wie sie hochgeladen wurde — „Netzplan.PNG" ist ein Bild und fehlt im Filter nicht.
  - **Hochgeladen**: heute, 7, 30 oder 90 Tage, wie im Protokoll.
  - **Sortierung** nach Datum, Bezeichnung oder Größe. **Neueste zuerst ist jetzt die Vorgabe** — vorher stand die älteste Datei oben, was bei einer wachsenden Liste selten das ist, was man sucht. Dateien ohne gespeicherte Größe stehen beim Sortieren nach Größe hinten: Eine fehlende Angabe ist keine kleine Datei.
  - Löschen läuft jetzt über die Liste selbst, ohne Seitenwechsel. Die Datei-Id wird dabei gegen den Kunden geprüft — sonst ließe sich mit einer fremden Id die Datei eines anderen Kunden löschen.
  - Beschriftung und Endungen der Arten stehen jetzt zusammen in `config/custom.php` statt im Model; vorher hätte eine Filterliste daneben entstehen müssen, die auseinanderlaufen kann.

- **Eigener Name und eigene Logos** unter „Administration → Einstellungen → Allgemein". Wer DokuVault beim Kunden oder im eigenen Haus einsetzt, kann die Oberfläche jetzt auf den eigenen Namen und das eigene Logo umstellen.
  - **Drei Logos, einzeln setzbar:** Anmeldeseite, Kopfzeile und Favicon. Das sind in der Praxis verschiedene Dateien — auf der Anmeldeseite darf es groß und breit sein, in der Kopfzeile muss es neben den Namen passen, ein Favicon ist quadratisch. Jede Stelle fällt für sich auf das eingebaute Motiv zurück.
  - Ein leeres Namensfeld heißt „wieder den Namen aus der Konfiguration nehmen", nicht „kein Name".
  - **Kein SVG:** Eine SVG-Datei darf Skript enthalten, und von derselben Herkunft ausgeliefert wäre das ausführbarer Code auf jeder Seite — in einer Dokumentation, in der Kennwörter stehen. Erlaubt sind PNG, JPG und WEBP bis 512 KB.
  - Die Dateien liegen privat auf der Platte und gehen durch einen Controller heraus, wie alle Dateien dieser App — kein `public/storage`-Symlink, der auf jedem Server eingerichtet werden müsste. Ein Logo ersetzt, löscht die alte Datei.
  - Das Favicon stand bisher in vier Layouts einzeln; es ist jetzt eine Komponente.
  - **Kein Speichern-Knopf:** Die Seite läuft über Livewire, jede Änderung gilt sofort. Wer ein Logo auswählt, hat es damit gesetzt; der Name wird beim Tippen übernommen. Ein Formular mit Speichern-Knopf lässt offen, ob die letzte Änderung noch drin war — diesen Zwischenzustand gibt es hier nicht.
  - Entfernt wird mit einem **Knopf**, nicht mit einem Häkchen plus Speichern danach. Auch ohne Rückfrage: Ein Logo ist in zehn Sekunden wieder hochgeladen, und eine Rückfrage wäre derselbe Zweischritt.
  - Nach einem Logo-Wechsel lädt die Seite neu — Kopfzeile und Favicon stehen im Layout, nicht in der Komponente, und zeigten sonst noch das alte Bild neben der neuen Vorschau. Beim Tippen des Namens bleibt das Neuladen aus, das wäre störend.

- **Server-Cluster lassen sich jetzt dokumentieren.** Welche Server zusammengehören und mit welcher Technik sie ihre Daten zusammenhalten — Ceph, Replikation, gemeinsamer Speicher, nur lokal oder Sonstiges. Bisher stand davon nirgends etwas, obwohl es beim Proxmox-Host die erste Rückfrage ist.
  - **Eigenes Objekt statt eines Felds am Server:** Die Technik gilt für den Cluster als Ganzes. An jedem Knoten gepflegt stünde sie mehrfach da und könnte auseinanderlaufen.
  - **Nicht auf Proxmox beschränkt** — der Typ sagt, worum es geht, damit auch ein Hyper-V- oder Datenbank-Cluster hineinpasst. Die Typenliste steht in `config/custom.php`: eine neue Technik ist eine Zeile, keine Migration.
  - Die Liste läuft über dieselbe Livewire-Mechanik wie die Server (`config/forms.php`): Suche während des Tippens, Anlegen und Bearbeiten im Modal, ohne Seitenwechsel.
  - **Zugeordnet wird am Server**, über ein neues Feld „Cluster" — ein Weg für eine Angabe, statt derselben Zuordnung an zwei Stellen. Die Cluster-Karte zeigt die Knoten mit Betriebssystem und EOL-Abzeichen: So fällt auf, wenn ein Knoten nicht auf demselben Stand ist wie die anderen.
  - Ein gelöschter Cluster nimmt seine Server **nicht** mit — sie verlieren nur die Zugehörigkeit.
  - Ein Cluster hängt an einem Standort, wie ein Serverschrank. Papierkorb, Protokoll und PDF-Export sind dabei.

- **Der Betriebssystem-Katalog lässt sich jetzt durchsuchen.** Bei 55 Einträgen über drei Seiten half nur noch Blättern, um ein bestimmtes System zu finden — jetzt filtert ein Suchfeld während des Tippens.
  - Die Liste läuft jetzt über Livewire statt eine statische Blade-Seite zu sein (dasselbe Muster wie Protokoll und Papierkorb); Anlegen und Bearbeiten bleiben eigene Seiten, dafür gibt es hier keinen Grund für ein Livewire-Formular.
  - Rechte und Sortierung unverändert: dieselbe Route, dasselbe `admin_catalog`-Recht, weiterhin alphabetisch.

- **Eine VM läuft auf einem Host oder in einem Cluster.** In einem HA-Cluster wandert sie zwischen den Knoten — ein fest eingetragener Host wäre dort nach der ersten Migration falsch dokumentiert. Beides zugleich lässt sich nicht eintragen: Das wären zwei Antworten auf dieselbe Frage.
  - Jedes der beiden Felder verschwindet, sobald das andere steht.
  - **Die VM-Karte nennt jetzt auch den Cluster.** Vorher stand dort nur der Host — eine Cluster-VM sah damit aus, als liefe sie nirgends, obwohl die Zuordnung dokumentiert war. Host und Cluster werden mit vorgeladen, sonst fragt die Liste sie je Zeile einzeln nach.

- **Der Demo-Datensatz enthält jetzt ein Proxmox-Cluster.** Ohne Beispiel sah man in der Demo nur leere Cluster-Listen. „PVE-Cluster HH" mit drei Knoten (PVE-01 bis 03, Proxmox VE 9, Ceph auf NVMe) und zwei VMs darauf, die bewusst am Cluster hängen und nicht an einem Knoten — genau der Unterschied zur VM auf einem einzelnen Host. Die Knoten stehen auch im Serverschrank, mit eigenen IP- und BMC-Adressen im Management-VLAN.
  - Ein Test hält das fest: drei Knoten, mindestens zwei VMs, keine davon auf einen Knoten gepinnt.

- **Die Knotenliste auf der Cluster-Karte war ausgefranst.** Die Knoten standen als einzeln umrandete Kästchen da — der Kartenkörper läuft aber in CSS-Spalten, und darin brach jedes Kästchen auf eine eigene Zeile um, unterschiedlich breit. Jetzt stehen sie in derselben Tabellenform wie jeder andere Block der Karte: Name links, System rechts, EOL-Abzeichen dahinter. Untereinander ausgerichtet wird der dreimal gleiche Systemname außerdem zur Aussage („alle auf demselben Stand") statt zu Rauschen — ein abweichender Knoten fällt sofort auf.

- **Der Standort einer VM kommt jetzt vom Host bzw. Cluster.** Wer den Host auswählt, hat den Standort schon beantwortet — beides getrennt zu pflegen hieß, dass sie sich widersprechen können. Host und Cluster stehen deshalb an erster Stelle im Formular, und sobald eines gewählt ist, verschwindet das Standortfeld.
  - **Ohne beides bleibt der Standort Pflicht.** Ein vServer beim Anbieter hat keinen dokumentierten Host — dort ist die Angabe die einzige Ortsangabe, und der Standortfilter in der Seitenleiste hängt daran.
  - Wechselt eine VM auf einen Host an einem anderen Standort, zieht sie mit.
  - Die Ableitung sitzt im Model, nicht im Formular-Request: Das Livewire-Modal erzeugt den Request nur, um seine Regeln zu lesen — dessen `prepareForValidation` läuft dort nie. Im Model kommen beide Wege durch, auch der Proxmox-Agent.

### Fixed

- **Der Betriebssystem-Filter sah aus wie ein Textfeld.** Ein Auswahlfeld richtet sich nach seiner längsten Option — „Windows Server 2025 Datacenter" machte es 113 Pixel breiter als seine Rasterzelle. Der Pfeil sitzt am rechten Rand und lag damit außerhalb der Karte. Die Filterfelder füllen ihre Zelle jetzt aus, der Browser kürzt zu lange Beschriftungen.

- **Suche und Filter standen getrennt.** Die Suche saß oben rechts in der Kopfzeile, die Filter in einer Karte darunter links — zwei Bedienfelder für dieselbe Aufgabe. Wo es eine Filterleiste gibt, steht die Suche jetzt als erstes Feld darin. Listen ohne Filter behalten ihre Suche in der Kopfzeile.

- **Windows-Lizenzen boten Debian und Proxmox zur Auswahl.** Eine Windows-Lizenz für Debian gibt es nicht — das Formular führte aber den ganzen Betriebssystem-Katalog. Jetzt stehen dort nur Windows-Systeme.
  - Im **Demo-Datensatz** war es schon passiert: Dort standen Windows-Lizenzen für „Debian 13" und „Proxmox VE 7". Die Factory würfelte eine Betriebssystem-Nummer zwischen 1 und 14 — welches System dahinter steckt, hing an der Reihenfolge im Katalog. Sie zieht jetzt ein echtes Windows-System.

- **Alle Adressen sind jetzt englisch.** `/admin/allgemein` fiel zwischen lauter englischen Pfaden auf und ließ sich in einer Anleitung schlecht zitieren. Deutsch gehört in die Beschriftung, nicht in die Adresse — die Oberfläche bleibt selbstverständlich deutsch.
  - `/admin/allgemein` → `/admin/general`, `/admin/papierkorb` → `/admin/trash`, `/admin/protokoll-historie` → `/admin/log-retention`, `/<kunde>/assistent` → `/<kunde>/wizard`, `/logo/{stelle}` → `/logo/{placement}`.
  - Die drei Adressen, die es vor heute schon gab, bleiben als Weiterleitung stehen — ein Lesezeichen auf `/admin/papierkorb` läuft nicht ins Leere.
  - Ein Test hält fest, dass keine deutsche Adresse zurückkommt.

- **„Bitte melde dich an" ist von der Anmeldeseite verschwunden.** Auf einer Seite mit Benutzername, Kennwort und einem Knopf „Anmelden" sagt der Satz nichts, was nicht ohnehin dasteht.

- **Karteninhalte überschnitten sich bei mittleren Fensterbreiten.** In der Serverliste stand „10.10.30.7**Hersteller**" — die IP-Tabelle lag über der Hardware-Tabelle in der Nachbarspalte, und das Kopiersymbol der Seriennummer ragte aus der Karte heraus. Sichtbar wurde es erst mit echten Daten: langer FQDN als Name, ausgeschriebener Hersteller, lange Seriennummer.
  - **Ursache:** Der Kartenkörper läuft in CSS-Spalten. Die Beschriftungsspalte der Tabellen stand auf `whitespace-nowrap` — damit ist die Mindestbreite der Tabelle die volle Textbreite, und eine Tabelle schrumpft nicht darunter. Sie lief in die Nachbarspalte und aus der Karte.
  - Beschriftungen brechen jetzt um, wenn es sonst nicht passt; lange Werte brechen mit. Betraf `x-minitablecard`, `x-ipcard`, `x-credentialscard` und die Cluster-Karte gleichermaßen.
  - **Zwei Spalten erst ab 1024 px statt ab 768 px:** Bei 768 px blieben neben der Seitenleiste rund 486 px — zwei Spalten wären je gut 200 px breit gewesen, und darin passt „Seriennummer  CZ29470H8K-…" schlicht nicht.
  - Die Wertspalte beansprucht nicht mehr die ganze Breite; vorher drückte sie die Beschriftung auf ein Wort pro Zeile („BMC IP-/Adresse").
  - Geprüft von 375 px bis 1440 px in Schritten, auch zwischen den Breakpoints, für Server, Cluster, VMs und NAS. Ein Test hält fest, dass in diesen Tabellen kein `whitespace-nowrap` zurückkehrt.

- **Die Seitenleiste klappt jetzt erst ab 1024 px auf statt ab 640 px.** Bei 640 px blieben neben ihr rund 360 px Inhalt — auf einem Tablet quer stand mehr Menü als Dokumentation auf dem Schirm. Unterhalb von 1024 px gibt es sie als ausklappbare Schublade, der Inhalt bekommt die volle Breite.
  - Dabei fiel eine alte Unstimmigkeit auf: Der Hamburger-Knopf verschwand ab 768 px, die Seitenleiste erschien aber schon ab 640 px — dazwischen waren beide zu sehen. Jetzt hängen beide am selben Breakpoint.
  - Weil unter 1024 px keine Seitenleiste mehr Platz wegnimmt, stehen die Kartenspalten wieder ab 768 px nebeneinander: Eine Spalte ist dort rund 340 px breit — genauso viel wie bei 1024 px mit Seitenleiste.

- **Die Kacheln im Admin-Dashboard waren abgeschnitten.** „Benutzer" stand als „Benutz…", „Zertifikate" als „Zertifi…", und bei 640 px scrollte die ganze Seite waagerecht. Der Textteil der Kachel war ein Flex-Kind ohne `min-w-0` — damit kann es nicht schmaler werden als sein Inhalt und schiebt die Kachel aus ihrer Rasterzelle. Dieselbe Falle wie bei den Tabellen, nur in Flex statt in Spalten.
  - Das Inventar-Raster beginnt außerdem mit zwei statt drei Kacheln pro Zeile — bei 375 px blieben für die Beschriftung sonst rund 60 px.
  - Nachgemessen über **alle 45 Kundenbereiche und 14 Admin-Seiten** bei 375, 640, 768, 900, 1023, 1024, 1280 und 1440 px, zusätzlich mit künstlich verlängerten Namen, Modellen und Seriennummern: kein Überlauf mehr, keine Seite scrollt waagerecht.

- **Feldübergreifende Prüfregeln griffen im Modal nie.** Im Modal heißen die Felder `form.name`, in den Requests aber `name` — eine Regel wie `required_if:form_factor,rack` suchte deshalb ein Feld, das es dort nicht gibt, und war wirkungslos. Beim Server hieß das: Höheneinheiten und Einbautiefe waren beim Rackeinbau nie Pflicht, ohne dass es auffiel. Aufgefallen ist es erst, weil dieselbe Mechanik für den VM-Standort gebraucht wurde.
  - Damit daraus keine Verschlechterung wird, belegt das Modal Zahlenfelder jetzt vor (Höheneinheiten mit 1, wie das Seitenformular es längst tat) — sonst müsste man die 1 bei jedem Rackserver tippen.

- **Ein neues dokumentiertes Objekt hätte den nächsten Deploy erneut abbrechen lassen.** Die vier Rechte eines Objekts legen zwei Stellen an: eine Migration für bestehende Installationen und der Seeder für frische. Beim `migrate:fresh --seed` des Deploys laufen beide nacheinander über dieselben Namen. Der bisherige Schutz in den Migrationen (nur einfügen, solange die Rechte-Tabelle leer ist) trug nur, solange sie **vor** der ersten Migration lagen, die selbst Rechte einfügt — beim Cluster war das nicht mehr der Fall. Der Seeder verwendet vorhandene Rechte jetzt auch bei den Objekt-Rechten wieder, statt sie erneut anzulegen (bei den Admin-Rechten tat er das schon).

## 26.08.22

### Added

- **Das Protokoll sagt jetzt, wer aus welchem Haus gehandelt hat.** Ein Kundenzugang mit Schreibrecht ändert Daten wie jeder Techniker — genau dann will man nachsehen, was er getan hat. In einer Liste aus lauter Namen ließ sich aber nicht erkennen, wer zu wem gehört.
  - Die Auswahlliste „Benutzer" ist nach Herkunft gruppiert: **Mitarbeiter** oben, darunter je Kunde dessen Zugänge.
  - In der Zeile steht der Kundenname klein unter dem Benutzernamen.
  - **Jeder Eintrag nennt jetzt den Namen des Objekts.** Bisher speicherte das Protokoll nur die geänderten Felder — wer an einer Domain bloß den Registrar änderte, hinterließ einen Eintrag „Domain #1". Der Name wird mitgeschrieben, nicht beim Anzeigen nachgeladen: Ein Eintrag überlebt sein Objekt. Ältere Einträge behalten ihre Nummer.
  - **Auch Objekte ohne `name`-Spalte** werden benannt: eine IP-Adresse mit ihrer Adresse, ein Rack-Einbau mit dem Gerät, eine Zugangsdaten-Verknüpfung mit dem Gerät, an dem sie hängt. Das betraf **27 von 50** sichtbaren Zeilen.
  - Eine Migration trägt den Namen in bestehende Einträge nach, soweit Klasse und Objekt noch da sind (in der Entwicklungsdatenbank: 180 ergänzt, 122 ohne Fundstelle — dort bleibt die Nummer stehen, das ist ehrlicher als ein erfundener Name).
  - Die Feldliste steht jetzt zentral in `config/custom.php`; Protokoll und Papierkorb stellen dieselbe Frage und beantworten sie aus derselben Quelle.

- **API-Token haben jetzt eine Seite** unter „Einstellungen → API-Token". Vorher gab `/admin/apitoken` rohes JSON zurück — und legte bei **jedem Aufruf** einen weiteren Token namens „optin" an, den man nirgends wieder loswurde. Ein Menüpunkt darauf hätte beim Klicken Token erzeugt.
  - Anlegen mit einer Bezeichnung, die sagt, wofür er da ist: Beim Widerrufen ist sie das Einzige, woran sich ein Token erkennen lässt.
  - Der Klartext steht **genau einmal** da, groß und mit Kopierknopf — gespeichert wird nur der Hash. Wer ihn nicht mitnimmt, legt einen neuen an.
  - Die Liste zeigt Bezeichnung, Alter und wann der Token zuletzt benutzt wurde. Ein Token, der „nie" benutzt wurde, ist ein Kandidat zum Widerrufen.
  - Widerrufen läuft über die eigene Beziehung, nicht über die Id allein: Sonst ließe sich mit einer fremden Id der Zugang eines anderen abschneiden.

- **Ein dritter Auto-Dokumentation-Agent: Windows-Arbeitsplatzrechner.** Bisher gab es Scripts für Proxmox-Hosts und Windows-Domaincontroller — ein normaler Client blieb außen vor. Das neue PowerShell-Script läuft auf jedem Windows-PC, ohne RSAT-Modul oder Domaincontroller-Voraussetzung, und meldet sich als „Computer" beim gewählten Standort an.
  - Identifiziert wird über die `MachineGuid` aus der Registry — stabil pro Windows-Installation, übersteht auch einen Hostname-Wechsel (anders als der Hostname selbst).
  - Erneute Läufe aktualisieren denselben Eintrag (Upsert über `agent_identifier`), statt Duplikate anzulegen — wie bei den beiden bestehenden Agenten.
  - Meldet Hersteller, Modell, Seriennummer, Betriebssystem und IP-Adresse; die IP landet im Block „Weitere IP-Adressen".
  - **Das gemeldete Betriebssystem trifft jetzt einen vorhandenen Katalogeintrag.** `Win32_OperatingSystem.Caption` liefert unter Windows immer das Präfix „Microsoft" (z. B. „Microsoft Windows 11 Pro"), der Katalog führt seine Windows-Einträge aber ohne — jeder Lauf hätte eine zweite, nie zusammengeführte Zeile neben der händisch angelegten „Windows 11 Pro" erzeugt. Das Präfix wird jetzt vor dem Abgleich gekappt.
  - **Eine gemeldete IP-Adresse ordnet sich jetzt automatisch dem passenden VLAN zu**, sofern eines zum Adressbereich am Standort passt — das gilt für alle drei Agenten (Proxmox, Windows-Client), nicht nur den neuen. Vorher blieb „Weitere IP-Adressen" ohne Netz stehen, obwohl das VLAN oft längst angelegt war.
    - Nur bei der **Neuanlage** einer Adresse wird gesucht; eine bereits vorhandene Zeile bleibt unangetastet, damit ein zweiter Lauf eine von Hand korrigierte Zuordnung nicht wieder umwirft.
    - Netz- und Broadcast-Adresse zählen bewusst mit dazu — eine gemeldete Gateway-IP soll treffen.

- **Der Betriebssystem-Katalog kennt jetzt 26 zusätzliche Support-Enden.** Windows 7/8.1/XP, Debian 9/10, Rocky Linux 9, AlmaLinux 9, openSUSE Leap 15, VMware ESXi 6/7/8 und macOS Ventura/Sonoma hatten nie ein Datum — beim Anlegen fehlte schlicht der Eintrag in der Zuordnungstabelle des Seeders.
  - **Root Cause bei Ubuntu:** Die drei Ubuntu-Server-Systeme standen zwar in der Tabelle, trafen aber nie — der Seeder verglich mit dem Präfix „Ubuntu 20.04" statt „Ubuntu Server 20.04" (der tatsächliche Katalogname), und `str_starts_with()` verlangt eine exakte Übereinstimmung ab dem ersten Zeichen.
  - **Kein Datum für:** Windows 11 (der Katalog führt keine Versionsnummer wie „24H2" — ohne die wäre „Windows 11 endet am …" schlicht falsch, solange Microsoft laufend neue Versionen nachschiebt), Proxmox Mail Gateway/TrueNAS (kein fester, produktweiter Termin), Synology/QNAP (hängt vom NAS-Modell ab) und Rangee OS (kein öffentlicher Support-Zeitplan bekannt).
  - Quellen: Microsoft Lifecycle, endoflife.date, Broadcom/VMware- und Distributions-Lifecycle-Seiten. Eine Migration trägt die Daten in bestehende Installationen nach — nur dort, wo noch kein Datum gepflegt war.

- **Proxmox VE und Proxmox Backup Server stehen jetzt einzeln je Hauptversion im Katalog** (VE 7/8/9, Backup Server 1–4) statt je einem Sammel-Eintrag — die Versionen haben unterschiedliche, teils schon abgelaufene bzw. bald ablaufende Support-Enden (**Proxmox VE 8 und Backup Server 3 enden schon am 31.08.2026**), ein einziges Datum für alle wäre für die meisten falsch gewesen.
  - Der Proxmox-Agent liest die gemeldete `pve_version` jetzt aus und trifft den passenden VE-Katalogeintrag statt immer denselben unversionierten anzulegen. Ohne auswertbare Version (älteres Script, `pveversion` nicht verfügbar) fällt er auf den unversionierten Namen zurück.
  - Die alten Sammel-Einträge „Proxmox Virtual Environment" und „Proxmox Backup Server" sind jetzt im Papierkorb — durch die versionierten Einträge abgelöst, aber weich statt hart gelöscht: ein Gerät, das noch darauf zeigt, verliert dadurch nicht seine Zuordnung, zeigt aber auch keinen Systemnamen mehr an, bis es von Hand auf einen der neuen Einträge umgestellt wird.

- **Debian 13 „Trixie" fehlte komplett im Katalog** (Release war August 2025). Ergänzt mit demselben Maßstab wie die bereits vorhandenen Debian-Versionen — dem Ende der Langzeitpflege (LTS), nicht nur dem regulären Support.

### Fixed

- **Die Betriebssystem-Liste stand in Anlage-Reihenfolge statt alphabetisch.** Bei 55 Einträgen über drei Seiten war ein bestimmtes System kaum wiederzufinden. `OperatingSystemController::index()` sortiert jetzt nach Name.

- **Der Deploy brach beim Befüllen der Demo ab.** Die neuen Admin-Rechte werden an zwei Stellen angelegt: per Migration für bestehende Installationen und im Seeder für frische. Der Deploy führt `migrate:fresh --seed` aus — erst die Migration, dann den Seeder — und dessen zweites `forceCreate` lief in den UNIQUE-Index auf `permissions.name`. Der Seeder verwendet vorhandene Rechte jetzt wieder, statt sie erneut anzulegen.
  - Ein Test führt den Seeder auf einer frisch migrierten Datenbank aus — genau den Ablauf des Deploys. Die Testsuite selbst hatte den Fehler nicht gefunden, weil sie den Seeder nie aufruft.

- **Drei Stellen blieben auf Englisch stur Deutsch.** Das „Erstaufnahme fortsetzen"-Banner auf dem Kunden-Dashboard reichte seine Texte als rohe PHP-Ternary durch statt durch `__()` — die Sprache hätte dort nie etwas geändert, egal welche eingestellt war. Die Tabellenkopfzeilen von 21 Listenseiten (`x-table.head`) und die Seitentitel der 8 Admin-Listen (`x-sitetopmenu`) reichten ihre Beschriftungen ebenso roh durch; im Rack-Katalog stand deshalb im Menü „Rack catalogue", direkt daneben in der Überschrift „Rack-Katalog".
  - Beide Tabellenkopf- und Titel-Fälle jetzt an einer Stelle behoben statt an 29 Aufrufstellen einzeln: `x-table.head` schickt jedes Label durch `__()`, und die Admin-Seitentitel sind aus dem Blade-Template in `config/custom.php` (`admin_list_titles`) gewandert — dieselbe Art Quelle wie das bestehende `list_titles` für den Rest der App.
  - Drei neue Tests, dazu eine Erweiterung des bestehenden Übersetzungs-Scans (`LocaleTest`) um `x-table.head`-Aufrufe, sonst hätte er die neu übersetzten Tabellenkopf-Strings als unbenutzt gemeldet.

## 26.08.21

### Added

- **Der Admin-Bereich lässt sich jetzt aufteilen.** Bisher hing alles unter `/admin` an einer harten Prüfung auf die Rolle „Admin": entweder ganz oder gar nicht. Eine zweite Technikergruppe, die etwa den Papierkorb und das Protokoll sehen soll, aber keine Benutzer anlegt, war damit nicht baubar. Jetzt gibt es **ein Recht je Menüpunkt** — Kunden, Benutzer, Rollen, Auswahlmenüs, Betriebssysteme, Einstellungen, Papierkorb, Protokoll, API-Token — frei kombinierbar in der Rollenverwaltung.
  - Die **Rolle „Admin" darf weiterhin alles**, unabhängig davon, was angehakt ist. Das ist die Absicherung gegen das Aussperren: Wer versehentlich „Rollen und Rechte verwalten" abwählt, käme sonst nie wieder an die Rollenverwaltung.
  - Das Menü zeigt nur, was der Benutzer auch öffnen darf. Ein Menüpunkt, der beim Klick 403 liefert, ist schlechter als keiner.
  - Auch die **Fernwartungs-Suche** hängt an einem Recht statt an der Rolle „Techniker" — eine zweite Technikergruppe hätte sie sonst nicht öffnen können.
  - In der Rollenverwaltung stehen die Admin-Rechte in einem **eigenen, abgesetzten Block**: Sie gelten für die ganze Installation, nicht für einen einzelnen Kunden.
  - Bestehende Rollen behalten ihren Umfang. Admin und Techniker bekommen die neuen Rechte per Migration, alle anderen nichts — sonst bekäme eine Kundenrolle über Nacht Rechte, die ihr niemand gegeben hat.
  - **Der Weg dorthin steht im Benutzermenü**: „Administration" erscheint für jeden, der mindestens einen Admin-Bereich öffnen darf. Vorher landete nur die Rolle „Admin" beim Anmelden dort — alle anderen hätten `/admin` von Hand tippen müssen. Aus dem Admin-Bereich führt umgekehrt „Zur Kundenauswahl" zurück.
  - Das Admin-Dashboard zeigt nur Kacheln, die auch zu öffnen sind. Eine Kachel, die beim Klick 403 liefert, ist schlechter als keine — und die Zahl darauf verrät etwas über einen Bereich, den der Benutzer nicht sehen soll.
  - **Drei Links führten trotzdem ins Verbotene**: Die Betriebssystem-Liste steht im Menü unter „Auswahlmenüs", hing aber an einem anderen Recht; auf dem Dashboard zeigten „alle →" und die EOL-Zeilen der Ablaufliste auf Seiten, die nicht jeder öffnen darf. Ein Test geht jetzt für **jedes** Recht das Dashboard durch, sammelt alle sichtbaren Admin-Links ein und ruft sie auf — keiner darf 403 liefern.
  - Das Recht heißt jetzt „Support-Ende (EOL) sehen" statt „Betriebssysteme verwalten": Die Liste der Betriebssysteme gehört zu den Auswahlmenüs, das eigene Recht trägt die EOL-Auswertung.
  - Der **Speichern-Knopf im Rollenformular steht ganz unten**, hinter der Rechte-Matrix. Über einer Tabelle mit fünfzig Zeilen sah er aus, als gehörte er nicht dazu.

- **Die Benutzerliste kann jetzt anlegen und löschen.** Der „Neu"-Knopf fehlte, und der rote „Löschen!"-Textknopf unter dem Stift zeigte auf gar keine Adresse — sein Formular ging an die aktuelle Seite. Jetzt ein roter Papierkorb-Symbolknopf neben dem Stift, wie überall sonst, mit der richtigen Route. Das eigene Konto lässt sich nicht löschen: Sonst stünde man vor einer Anmeldemaske ohne Zugang.

- **Das Aktivitätsprotokoll hat Suche und Filter bekommen.** Es war eine feste Liste, 50 Einträge je Seite, absteigend nach Zeit. Bei 863 Einträgen war die Frage „wer hat gestern an der Firewall etwas geändert?" damit eine Blätterübung. Jetzt filterbar nach **Ereignis, Objektart, Benutzer und Zeitraum**, dazu eine **Volltextsuche**.
  - Die Suche geht über die Eigenschaften eines Eintrags, nicht nur über den Namen: In einem Protokoll sucht man nach dem, woran man sich erinnert — einem Gerätenamen, einer IP, einem Registrar.
  - Die Auswahllisten enthalten nur, was vorkommt. Die Tabelle kennt 114 verschiedene Verursacher-Ids, die meisten aus Beispieldaten längst gelöschter Konten; eine Auswahl mit 114 Zeilen, von denen 110 leer sind, hilft niemandem.
  - Die Filter stehen in der Adresse — ein gefilterter Stand lässt sich verlinken. Leere Filter bleiben draußen.
  - Die Kopfzeile nennt beide Zahlen („1 von 863 Einträgen"). Ohne die Gesamtzahl hält man das gefilterte Ergebnis für den Bestand.
  - „heute“ heißt heute, nicht „die letzten 24 Stunden“: Um 18:46 zeigte der Knopf sonst auch Einträge von gestern 18:20 — 46 statt 21. Ab einer Woche ist der Unterschied belanglos, dort bleibt es rollierend.
  - Ein Unterstrich oder ein Prozentzeichen im Suchbegriff wird jetzt als Zeichen gelesen, nicht als Platzhalter. Vorher fand „SRV_01“ auch „SRV101“, und die Suche nach „%“ lieferte alle 863 Einträge. Die Maskierung steckt in einem Query-Macro `whereEnthaelt()`, das auch die übrigen Suchen im Projekt übernehmen können.

- **Dieselbe Maskierung jetzt in allen Suchen.** Globale Suche, Gerätelisten, VLAN-Liste, Kundensuche, Fernwartungs-Suche und die Kunden-API lesen `_` und `%` als Zeichen, nicht als Platzhalter.
  - Drei dieser Stellen maskierten bereits — aber ohne `ESCAPE`-Klausel. Das ging auf MySQL gut und legte auf SQLite die Suche still: Ein Begriff mit Unterstrich fand dort gar nichts mehr. Da die Tests auf SQLite laufen und die Produktion auf MySQL, war der Fehler von beiden Seiten unsichtbar.
  - Die globale Suche behält ihre Präfix-Form für die Massentabellen. Der Unterschied ist gemessen: `%begriff%` kostete bei Millionen Datensätzen 2788 ms, die Präfix-Form auf indizierter Spalte 3 ms.

- **Kennwortänderungen stehen jetzt im Protokoll.** Bisher war eine Kennwortänderung dort unsichtbar: Kennwortfelder sind vom Protokoll ausgeschlossen (ihr Wert darf nie hinein), und wenn nur das Kennwort geändert wurde, entstand deshalb gar kein Eintrag. Neu ist ein eigenes Ereignis **„Kennwort geändert"** mit Zeitpunkt, Benutzer, Objekt und dem betroffenen **Feldnamen** — „Kennwort", „BMC-Kennwort", „USC-PIN" — aber **nie dem Wert**.
  - Gilt für alle protokollierten Objekte, vom Gerätekennwort bis zum **Anmeldekennwort eines DokuVault-Benutzers**.
  - Ein erneutes Speichern desselben Kennworts ist keine Änderung. Die Verschlüsselung erzeugt bei jedem Speichern einen anderen Chiffretext — ohne Klartext-Vergleich hätte jedes Absenden des Formulars eine Kennwortänderung gemeldet, auch wenn niemand das Feld angefasst hat.
  - Der Objektname wird in den Eintrag geschrieben statt beim Anzeigen nachgeladen: Ein Protokolleintrag überlebt sein Objekt, und ein Verweis auf eine entfernte Klasse bricht beim Auflösen die ganze Seite.

- **Vorherige Kennwörter bleiben nachschlagbar — im Protokoll und am Gerät.** Der Fall, um den es geht: Ein Kunde oder Techniker ändert ein Kennwort falsch, und man braucht das alte zurück.
  - **Unter `admin/activity`** verhält sich der Eintrag „Kennwort geändert" wie jeder andere: derselbe „anzeigen"-Link in der Details-Spalte, aufgeklappt dasselbe `Feld: Wert`-Muster — nur dass der Wert maskiert ist und Auge und Kopierknopf dabeistehen. Bei mehreren Feldern eine Zeile je Feld.
  - **Im Bearbeiten-Formular** direkt unter dem Kennwortfeld: „Zuletzt geändert vor 3 Tagen — vorheriges Kennwort anzeigen".
  - Der Wert steht **nicht im Protokolleintrag**, sondern wird beim Anzeigen aus einer eigenen, verschlüsselten Tabelle geholt. Im Eintrag stehen nur Verweise.
  - Geladen wird **erst auf Klick**. Sonst stünden auf einer Protokollseite fünfzig alte Kennwörter im Quelltext, auch wenn niemand danach gefragt hat.
  - Sichtbar am Gerät für jeden, der es bearbeiten darf — das aktuelle Kennwort sieht er dort ohnehin. Im Protokoll zusätzlich mit `see_hidden`.
  - Ein erneutes Speichern desselben Kennworts legt keinen Eintrag an, und beim ersten Setzen gibt es nichts aufzuheben.

- **Neuer Menüpunkt „Einstellungen → Protokoll-Historie": wie lange das Protokoll bleibt.** Bisher wuchs es unbegrenzt. Die Frist gilt für Protokolleinträge **und** die daran hängenden Kennwörter — eine Zahl, nicht zwei: Die alten Werte sind das, was ein Eintrag über eine Kennwortänderung zu zeigen hat.
  - **0 heißt: unbegrenzt** und ist die Vorgabe. Ein Protokoll, das sich ungefragt selbst leert, wäre keines mehr.
  - Die Seite nennt die Folgen, bevor man speichert: Anzahl der Einträge, ältester Eintrag, und wie viele die eingestellte Frist heute treffen würde. „365" sagt einem sonst nicht, ob damit drei Einträge verschwinden oder dreitausend.
  - Ein nächtlicher Lauf um 3:40 Uhr räumt ab. Nach Ablauf zeigt das Protokoll die Änderung weiter an, den alten Wert aber nicht mehr — dort steht dann „Nicht mehr aufbewahrt".

- **Papierkorb über alle Kunden — als eigener Menüpunkt im Admin-Bereich.** Der Papierkorb beim Kunden zeigt dessen eigene Einträge und kann sie zurückholen. Hier geht es um das Gegenteil: sehen, was sich über die Jahre angesammelt hat, und es loswerden. Gelöscht wird von Hand — ein Zeitplan, der im Hintergrund unbemerkt Daten endgültig entfernt, wäre dafür das falsche Werkzeug.
  - **Filter nach Alter, Art und Kunde.** Die Tage sind frei eingebbar; 21, 90 und 365 stehen als Knöpfe daneben, weil sie das Übliche abdecken — aber nicht jede Aufbewahrungsregel hält sich daran.
  - **Einzeln oder alles Angezeigte.** Das Sammellöschen trifft ausschließlich, was der Filter gerade zeigt, und fragt vorher mit der **Anzahl** nach: „Wirklich löschen?" ohne Angabe, wie viel, ist keine Grundlage für ein Ja. Der Warnhinweis nennt, was mitgeht — gespeicherte Kennwörter, hinterlegte Dateien und die daran hängenden IP-Adressen.
  - Endgültig heißt endgültig: Die Datei wird von der Platte gelöscht, IP-Adressen und Zugangsdaten-Verknüpfungen gehen mit. Blieben sie liegen, zeigten sie auf eine Id, die es nicht mehr gibt — und genau daran ist die Zugangsdaten-Seite schon einmal zerbrochen.
  - Die Seite lädt **je Art höchstens 500 Einträge** und sagt es, wenn sie an diese Grenze stößt. Ohne den Hinweis hielte man die Zahl in der Kopfzeile für den ganzen Bestand und wunderte sich, warum nach dem Löschen noch etwas da ist.
  - Zugang nur mit `see_hidden` — geprüft beim Aufruf **und** bei jeder Löschaktion. Die Klasse zum Slug kommt aus der Whitelist in `config/custom.php`, nie aus der Anfrage.

### Security

- **USC-PIN, Cloud-Backup-Kennwort und PPPoE-Kennwort standen im Klartext im Protokoll.** Die Ausschlussliste nannte die Namen der Accessor-Methoden (`uscpin`, `cloudBackupPassword`), die Spalten heißen aber `usc_pin` und `cloud_backup_password`; `pppoe_password` fehlte ganz. Damit hat das Protokoll diese drei Felder mitgeschrieben — alten **und** neuen Wert. In der Entwicklungsdatenbank waren 16 Einträge betroffen.
  - Die Liste steht jetzt in `config/custom.php` und wird von einem Test gegen die tatsächlich verschlüsselten Spalten abgeglichen. Eine neue Kennwortspalte, die dort fehlt, macht den Test rot.
  - Eine Migration entfernt die Werte aus bestehenden Einträgen. Der Eintrag selbst bleibt stehen — dass jemand etwas geändert hat, ist die Information, um die es geht.

### Fixed

- **Die Protokollseite schnitt die Details-Spalte ab und lief seitlich über.** Gemessen bei 839 Pixeln: 25 Pixel der Spalte lagen außerhalb des Rahmens, weil er `overflow-hidden` trug. Solange dort nur „anzeigen" stand, fiel es nicht auf. Die Tabelle scrollt jetzt in ihrem Rahmen.
  - Auch die Seitenzahl-Leiste lief über: 18 Seiten nebeneinander sind 633 Pixel. Sie bekommt einen eigenen Scrollbereich.

- **Der Löschen-Knopf im Admin-Papierkorb tat nichts.** Livewires `updated()`-Hook feuert bei *jeder* Eigenschaft, wenn man den Namen nicht prüft — also auch bei der Rückfrage selbst, die sich damit sofort wieder schloss. Jetzt reagiert der Hook nur noch auf die drei Filter.
- **Zwei Filterknöpfe lagen unter der Nachbarspalte.** Bei 839 Pixeln blieben für die Altersspalte 159 Pixel, gebraucht wurden 238 — „90" und „365" verschwanden hinter der Art-Auswahl. Drei Spalten gibt es jetzt erst ab 1024 Pixeln, und die Knopfreihe darf notfalls umbrechen.
- Bei 839 Pixeln Fensterbreite lag der Löschen-Knopf außerhalb des Sichtbaren: Die Tabelle hatte eine Mindestbreite von 40 rem. Ausgerechnet dieser Knopf ist der Sinn der Seite — jetzt sind es 30 rem, der Kundenname darf dafür umbrechen.

## 26.08.17

### Added

- **Der Papierkorb zeigt jetzt, was er verschweigt.** Er kürzt die Liste je Art auf 100 Einträge — bisher stillschweigend, was sich liest wie „mehr ist nicht da". Ein Hinweis nennt jetzt die tatsächliche Zahl. Dazu Kopfzeile mit Anzahl, die Art als Etikett, „vor 3 Tagen" statt eines nackten Zeitstempels (der genaue steht im Hover), und ein Leerzustand, der erklärt, wozu die Seite da ist.
  - Am Handy war der Wiederherstellen-Knopf abgeschnitten: Die Tabelle stand in einem Rahmen mit `overflow-hidden`. Jetzt scrollt sie darin, ohne dass die Seite selbst überläuft.

- **Die Dateiverwaltung unter „Sonstiges" hat eine Oberfläche bekommen.** Sie hatte bisher nicht einmal einen Titel: Das Upload-Formular klebte oben am Rand, die Tabelle trug zwei leere Spaltenüberschriften, und ob eine Datei 12 KB oder 18 MB groß ist, erfuhr man erst nach dem Herunterladen.
  - Kopfzeile mit Titel und Anzahl, der Upload als eigene Karte, die Liste mit **farbiger Typ-Kachel** (PDF rot, Bild lila, Tabelle grün …), Größe, Alter und getrennten Knöpfen für Herunterladen und Löschen.
  - Die **Größe wird beim Hochladen mitgeschrieben** statt bei jeder Anzeige von der Platte gelesen — das wäre ein Dateizugriff je Zeile und würde bei einer fehlenden Datei zusätzlich abbrechen. Bestandsdateien tragen ihre Größe per Migration nach.
  - Schon **vor** dem Hochladen steht da, wie groß die gewählte Datei ist — samt Hinweis, wenn sie über dem Limit von 20 MB liegt. Das erfuhr man vorher erst hinterher. Und die Bezeichnung füllt sich aus dem Dateinamen, solange das Feld leer ist.

- **Beschaffung und Garantie für jedes Gerät.** Bisher liess sich zu einem Server die Seriennummer erfassen — aber nicht, wann er gekauft wurde, wie lange er Garantie hat und bei wem er bestellt wurde. Damit blieben genau die zwei Fragen offen, für die man die Seriennummer überhaupt notiert: „Ist die Kiste noch in Garantie?" und „Wo haben wir die her?". Neu sind **Kaufdatum, Garantie bis, Support-Ende (EOL) und Lieferant** — auf allen 17 Gerätearten, von der Firewall bis zur USV.
  - Welche Tabellen das betrifft, ist nicht handverlesen, sondern folgt einem Merkmal: Wo eine Seriennummer erfasst wird, ist beschaffte Hardware dokumentiert. VMs, Netze und Konten haben keine und bleiben aussen vor — eine VM hat keine Garantie.
  - In der Geräteliste steht bei der Garantie die Restlaufzeit dabei, sobald es knapp wird („in 10 Tagen", „abgelaufen"). Ein Datum allein liesse jeden selbst rechnen, und genau in dem Moment — Kunde am Telefon, Gerät defekt — will man nicht rechnen.
  - Das Kunden-Dashboard hat eine dritte Ablauf-Karte: **Ablaufende Garantien**, über alle Gerätearten hinweg, mit derselben Frist von 60 Tagen wie Lizenzen und Zertifikate. Sie zeigt nur Gerätearten, deren Liste der angemeldete Nutzer auch öffnen darf. Kostet 5–6 ms bei einem Kunden mit vollem Bestand.
  - Auch im PDF: Die vier Felder stehen bei den Hardware-Stammdaten jedes Geräts.

- **Anlegen und Bearbeiten im Modal — Grundstein für alle Typen.** Das VLAN hatte es vorgemacht: Wer einen Eintrag nachträgt, verliert sonst die Liste, auf die er gerade geschaut hat. Statt das Muster vierzigmal zu kopieren (VLAN kostete rund 630 Zeilen), beschreibt jetzt `config/forms.php` die Felder, und **eine** Livewire-Komponente baut das Formular daraus. Nicht umgestellt bleiben Typen mit eigener Bearbeitungs-Oberfläche: Der **Serverschrank** hat seinen Editor mit Drag-und-Drop, das **Patchfeld** seine Portverwaltung. Beides braucht Platz und passt nicht in ein Modal. Ein Test hält das fest — ein Livewire-Block im Bearbeiten-Formular ist das Zeichen, dass der Typ bei seiner Seite bleibt.

  Auch die **Firewall** läuft über das Modal, ebenfalls zweispaltig. Ihre vier Securepoint-Felder erscheinen erst, wenn „Securepoint" im Herstellerfeld steht — verglichen statt auf Gleichheit geprüft, weil der Hersteller Freitext ist und „Securepoint GmbH" dasselbe meint.

  Das **Server-Modal ist zweispaltig**: Zwanzig Felder untereinander wären eine Scrollstrecke, bei der man den Anfang aus den Augen verliert. Die Dienste spannen über beide Spalten, Einbautiefe und Höheneinheiten erscheinen nur beim Rackeinbau.

  Nach der Dateiwahl schlägt das Modal den Dateinamen als Bezeichnung vor — ohne Endung und nur, solange das Feld leer ist. Wer schon etwas eingetragen hat, behält es.

  Umgestellt sind **37 Typen** — alle bis auf Serverschrank und Patchfeld, die ihre eigene Oberfläche behalten — neben den einfachen jetzt auch die Geräte: Accesspoint, Kamera, Computer, DECT, IoT-Gerät, NAS, Switch, Sonstiger Client, Telefon, TK-Anlage, Drucker, Recorder, Router, USV und VM.
  - Die Felddefinitionen der Geräte sind nicht abgeschrieben, sondern **aus den Validierungsregeln und Beschriftungen der Requests erzeugt**. Damit ist der Abgleich zwischen Modal und Request per Konstruktion erfüllt statt nur geprüft.
  - Die Liste lädt dieselben Nebendaten vor wie die Controller (Zugangsdaten, Adressen, Einbauort, Betriebssystem, Standort). Ohne das kostete eine Seite mit 25 Geräten 88 Abfragen statt acht.
  - Validiert wird mit demselben Request, den auch der Controller benutzt — eine zweite Regelmenge wäre die Stelle, an der die beiden Wege auseinanderlaufen.
  - Die Feldlisten wurden aus den bestehenden Formularen ausgelesen und **gegen die Validierungsregeln geprüft**. Nur Typen, bei denen beide deckungsgleich sind, kommen in Frage: Ein Feld, das aus der Definition fällt, ließe sich im Modal nicht mehr ausfüllen — ohne Fehlermeldung. Ein Test hält das fest.
  - Deshalb bleiben Lizenzen, WLAN, Postfach, AD-Benutzer und Serverschrank vorerst bei Seiten: Dort gibt es Datei-Uploads, Auswahllisten und Schalter, die die Extraktion nicht erfasst.
  - Auswahllisten können statt eines Feldnamens ein Muster tragen (`VLAN {vlanId} · {description}`). Das WLAN zeigt sein Netz damit als „VLAN 20 · Clients" und sortiert nach der Nummer statt alphabetisch — im Netz spricht ohnehin jeder von der VLAN-Nummer. Ein Muster statt einer Closure, weil `config:cache` die Datei einfriert und Closures das nicht überleben.
  - Anlegen, Speichern und Löschen quittieren sich mit derselben Einblendung unten rechts wie beim VLAN („Domain angelegt.", „Postfach gespeichert.", „Serverschrank gelöscht.").
  - Das VLAN-Modal hat die Verbesserungen zurückbekommen, die beim Umbau entstanden sind: Die Knopfleiste haftet am unteren Rand, statt bei zehn Feldern aus dem Bild zu rutschen, und unter der Lösch-Rückfrage steht wieder Abstand.
  - Knopfleiste und Suchfeld sind dieselben Bausteine wie im VLAN-Modal: Löschen links abgesetzt, Abbrechen und Speichern rechts, „Anlegen" statt „Speichern" beim neuen Eintrag, und die Suche mit Lupe im Feld.
  - Die Darstellung bleibt beim Typ: Kartenlisten liefern `_karte.blade.php`, Tabellenlisten `_zeile.blade.php` und `_spalten.blade.php`. Eine erzwungene Vereinheitlichung wäre ein zweiter Umbau im ersten gewesen — fünf der elf Listen sind Tabellen, und das aus gutem Grund.
  - Geräte führen IP-Adressen und Zugangsdaten in eigenen Blöcken mit eigenem Speichern. Die erscheinen im Modal beim Bearbeiten — beim Anlegen hängt noch nichts am Objekt, dort steht derselbe Hinweis wie früher im Formular. Zwei Tests halten das fest: einer prüft, dass die Blöcke im Bearbeiten-Modal stehen, der andere, dass jedes Model, das sie führt, auch als solcher Typ eingetragen ist.
  - Ein Test prüft, dass jeder Typ aus `config/forms.php` auch wirklich umgestellt ist und sein Teilstück hat. Bleibt eine Liste zurück, fällt das sonst erst auf, wenn jemand dort auf „Neu" klickt und auf einer Seite landet statt im Modal.

- **Die Fernwartungslösung ist einstellbar.** Der Verbinden-Knopf war fest auf RustDesk verdrahtet — in vier Views, mit vier Beschriftungen und einem Icon. Wer TeamViewer benutzt, hätte jede Stelle einzeln ändern müssen. Unter **Administration → Einstellungen** steht jetzt zur Wahl: RustDesk, TeamViewer, AnyDesk oder ein eigenes URL-Muster. Bestehende Installationen bleiben ohne Zutun bei RustDesk.
  - Der Knopf kommt aus **einer** Komponente statt aus vier Views, und die Felder am Gerät heißen nach dem eingestellten Werkzeug („TeamViewer ID" statt „Rustdesk ID") — in Formularen, Listen und im Erstaufnahme-Assistenten.
  - **Nur RustDesk übergibt das Kennwort im Link.** TeamViewer und AnyDesk können das nicht; dort öffnet der Knopf die Verbindung, und das Kennwort steht weiterhin zum Kopieren am Gerät. Die Einstellungsseite sagt das bei jeder Lösung dazu, statt es den Anwender herausfinden zu lassen.
  - Erreichbar über **Administration → Einstellungen → Fernwartung**. Als Aufklappmenü angelegt, weil weitere Einstellungen dazukommen werden — eine Kachel im Zähler-Raster des Dashboards war dafür der falsche Ort, sie hatte nichts zu zählen.
  - Beim eigenen Muster wird geprüft, womit es beginnt: Aus dem Muster wird ein anklickbarer Link in jeder Geräteliste, `javascript:` oder `data:` wären damit ausführbarer Code. Fehlt `{id}`, führt der Knopf nirgendwohin — auch das wird abgelehnt.

- **Die Securepoint UTM ist in der Firewall aufgegangen.** Zwei Objekte für dieselbe Gerätegattung bedeuteten zwei Einträge in Sidebar, Dashboard, PDF und Suche — und ein Gerätetausch von Securepoint auf Sophos hieß löschen und neu anlegen. Jetzt unterscheidet der **Hersteller**, nicht der Gerätetyp.
  - Die vier Felder, die es nur bei Securepoint gibt (USC-PIN, Cloud-Backup-Kennwort, Benutzerportal, externer Zugang), erscheinen im Formular erst, wenn „Securepoint" im Herstellerfeld steht — dasselbe Muster, mit dem die Bauform beim Server über Einbautiefe und Höheneinheiten entscheidet. In der Liste blendet sich der Block aus, wenn nichts gefüllt ist.
  - `urlAdmin` ist die Verwaltungsoberfläche, die es ohnehin gibt; `type` („Appliance" oder „VM") wurde zur **Bauform**, die für jede Firewall taugt — eine OPNsense läuft oft als VM.
  - **Kein Datenumzug**, die Anwendung ist noch nicht produktiv: Die Tabelle `securepoint_utms` und ihre vier Berechtigungen werden entfernt, die Demo-Daten entstehen als Firewall mit Hersteller Securepoint neu.
  - Was dabei leicht übersehen wird: IP-Adressen, Zugangsdaten, Schrankeinbauten und das Änderungsprotokoll zeigen mit einem **Klassennamen** auf das Gerät. Bleibt der alte stehen, bricht jede Seite, die ihn auflöst, mit „Class not found" — nachgemessen, nicht vermutet. Die Migration entfernt diese Verweise, statt sie umzubiegen: Die alten Ids würden sonst zufällig auf ein fremdes Gerät zeigen.
  - Die kundenübergreifende UTM-Übersicht unter `/utmsearch` entfällt ersatzlos, samt ihrer drei Verknüpfungen in den Navigationen.

- **Firewall als eigenes Gerät.** Firewalls gab es nur herstellergebunden als Securepoint UTM. Wer eine Sophos, Fortigate, OPNsense oder pfSense dokumentiert, musste sie in „Router" pressen — dabei ist die Firewall in fast jedem Netz das Gerät, nach dem zuerst gefragt wird. Vollständig eingebunden: Liste, Anlegen, Bearbeiten, Papierkorb, Serverschrank, globale Suche, PDF, Erstaufnahme-Assistent und eine Dashboard-Kachel.
  - Drei Felder, die es nur hier braucht: **Firmware** (bei einer Firewall ist der Versionsstand eine Sicherheitsfrage, keine Randnotiz), **Verwaltungsoberfläche** (die hängt selten auf der WAN-Adresse) und **Subscription bis** — ohne gültige Subscription bekommt eine UTM keine Signaturen mehr. Das ist ein anderes Datum als die Hardware-Garantie und deshalb ein eigenes Feld.
  - Das Kennwort liegt verschlüsselt in der Tabelle, der Standort wird gegen den Mandanten geprüft. Beides hält je ein Test fest.

### Fixed

- **Das Anlegen eines Ansprechpartners im Modal wäre abgestürzt.** In der Felddefinition stand `contactpeople` als Relation, am Kunden heißt sie `contactpersons` — `BadMethodCallException` beim ersten Klick auf Speichern. Aufgefallen ist es erst, als ein neuer Test prüfte, ob jede genannte Relation überhaupt existiert; die Listen rendern ja, und die Feldprüfung sieht Relationsnamen nicht an.

- **Das Anlegen im Modal scheiterte an Pflichtfeldern mit Standardwert.** Drei Fehler auf einmal, alle beim Server sichtbar: Die Bauform *zeigte* „19-Zoll", der Wert im Formular war aber leer — dadurch blieben Einbautiefe und Höheneinheiten unsichtbar und das Speichern verlangte etwas, das ausgefüllt aussah. Die Meldung nannte dabei den internen Namen (`form.form factor`) statt der Beschriftung. Und schließlich brach das Anlegen still ab: `Column 'height_units' cannot be null` — leere Felder werden als `null` gespeichert, was bei einer `NOT NULL`-Spalte mit Standardwert nicht geht.
  - Feste Optionslisten sind jetzt beim Anlegen mit ihrem ersten Eintrag belegt, Beschriftungen kommen notfalls aus der Felddefinition, und bei `NOT NULL`-Spalten wird der leere Wert weggelassen, damit die Datenbank ihren Standard setzt.

- **Die ausgewählte Fernwartungslösung war im Dunkelmodus nicht lesbar.** Die Karte trug `dark:bg-cerulean-900/20` — diese Klasse fehlte im ausgelieferten CSS, weil der Build älter war als die Seite. Damit blieb der *helle* Hintergrund `bg-cerulean-50` stehen, mit hellem Text darauf: **Kontrast 1,02:1**, praktisch dieselbe Farbe. Jetzt `dark:bg-cerulean-950` ohne Deckkraft-Angabe (13,5:1), und das CSS ist neu gebaut.
  - Ein Test prüft jetzt, dass jede in einer View verwendete Farbklasse **mit Deckkraft** auch im gebauten CSS steht. Genau diese Klassen sind der Fallstrick: Jede Stufe (`/10`, `/20`, `/30`) braucht eine eigene Regel, und wer eine View ändert ohne neu zu bauen, bekommt kein Fehlerbild — die Darstellung fällt still auf etwas anderes zurück.

- **Der Cache in der Testumgebung war nie isoliert.** `phpunit.xml` setzte `CACHE_DRIVER` — seit Laravel 11 heißt die Variable `CACHE_STORE`. Die Tests liefen damit auf dem echten Treiber, und ein Cache-Eintrag aus einem Test war im nächsten noch da. Gefunden, als ein neuer Test genau daran scheiterte.

- **Vier Spaltengruppen liefen im PDF um.** Die Spaltenbreite wurde in Stufen gesetzt (1 → 97 %, 2 → 47 %, sonst 30 %); bei vier Gruppen ergab das 120 %, also brach die vierte Spalte in die nächste Zeile. Betraf schon „Internet / WAN". Die Breite wird jetzt aus der Gruppenzahl gerechnet.

## 26.08.15

### Added

- **Das PDF entsteht im Hintergrund.** Gemessen an einem Kunden mit 40 Servern, 90 VMs und 160 Computern braucht DomPDF 370 MB und 15 Sekunden — im Request lief das erst in den Speicher und dann gegen das Zeitlimit. Jetzt legt der Klick einen Auftrag an, den der Zeitplan abarbeitet; das Dashboard zeigt den Stand und bietet die fertige Datei zum Laden an. Nur der Besteller darf sie holen — sie enthält alle Zugangsdaten des Kunden —, und nach 24 Stunden wird sie gelöscht. **Der Server braucht dafür eine Cron-Zeile** (`* * * * * php artisan schedule:run`) und `QUEUE_CONNECTION=database`; fehlt sie, sagt das Dashboard nach fünf Minuten, dass der Auftrag liegen bleibt. Beschrieben in [DEPLOYMENT.md](DEPLOYMENT.md).


- **Subnetzmaske und CIDR rechnen sich gegenseitig aus**: Beide sagen dasselbe in zwei Schreibweisen. Wer `255.255.0.0` einträgt, bekommt `16`; wer `28` einträgt, bekommt `255.255.255.240`. Gilt im VLAN-Modal und im Assistenten. Eine unvollständige oder falsche Eingabe lässt das andere Feld in Ruhe, statt es zu leeren — ein Vertipper soll nicht die schon getippte Angabe kosten. Das alte Formular unter `/network/create` ist kein Livewire; dort wird die fehlende Schreibweise beim Speichern ergänzt. Sind beide von Hand gefüllt und widersprechen sich, bleiben beide stehen: Das ist eine Eingabe und keine Lücke. Die vollständige Präfixtabelle von /0 bis /32 liegt als Test bei.

- **Suche in der VLAN-Liste**: Ein Feld in der Kopfleiste filtert während des Tippens über Bezeichnung, VLAN-Nummer, Netzadresse und Gateway — die vier Angaben, nach denen man ein VLAN sucht. DNS und DHCP bleiben draußen, danach sucht niemand. Der Begriff steht in der Adresse (`?search=…`), ein gefiltertes Ergebnis lässt sich also weitergeben; nach jedem Tastendruck geht die Liste zurück auf Seite eins, sonst suchte man auf Seite drei ins Leere. Findet nichts, sagt die Liste „Kein VLAN passt zu …" statt „Noch keine Einträge vorhanden." — das ist ein Unterschied.

- **Erfolgsmeldungen unten rechts**: Anlegen, Speichern und Löschen im VLAN-Modal quittieren sich mit einer Einblendung („VLAN angelegt.", „VLAN gespeichert.", „VLAN gelöscht."), die nach vier Sekunden von selbst geht und einen Schließen-Knopf hat. Den Kasten gab es schon, er hing aber allein an `session('success')` — bei Aktionen ohne Seitenwechsel kam die Meldung frühestens beim nächsten Laden an, also nie. Jetzt steht er immer im Dokument und hört zusätzlich auf ein Ereignis aus Livewire. Für Vorleseprogramme ist er als `role="status"` ausgezeichnet, wird also angesagt, ohne die Eingabe zu unterbrechen.

### Fixed

- **Globale Suche und Admin-Dashboard bei großen Beständen.** Gemessen an 10 Millionen Datensätzen (5.000 Kunden, 4 Mio AD-Benutzer, 2 Mio Computer): Die Suche brauchte allein für die AD-Benutzer 2788 ms, das Admin-Dashboard 3787 ms für seine Zähler. Jetzt 3 ms bzw. 1 ms.
  - Die Massentabellen (AD-Benutzer, Computer, VMs, Telefone, Kameras) werden mit Präfix durchsucht und haben Indizes auf den durchsuchten Spalten — ein `LIKE '%begriff%'` kann keinen Index nutzen. Alle übrigen Tabellen suchen weiter mitten im Wort: Das Rack heißt „Rack HH-01" und wird als „HH-01" gesucht, die Dose steht als „EG 2.14" drin und wird als „2.14" gesucht. Bei diesen Größen ist ein Tabellendurchlauf billiger als der Verlust an Treffern.
  - Die Zähler des Admin-Dashboards werden eine Viertelstunde gemerkt. `COUNT(*)` ohne Einschränkung liest den ganzen Index; es sind Kennzahlen, keine Kontostände.

- **Der Speicherpuffer für die PDF-Ausgabe war zu knapp bemessen.** Ein Testlauf mit größeren Mengen (40 Server, 90 VMs, 160 Computer, 420 AD-Benutzer) zeigt: 370 MB Spitzenverbrauch und 15 Sekunden für ein 1,5-MB-PDF — gegenüber 136 MB und 2 Sekunden beim kleineren Demo-Kunden. Der Puffer liegt jetzt bei 768 MB. Das bleibt ein Puffer und keine Lösung: Bei doppelter Menge reicht auch das nicht, und die 15 Sekunden rücken an jedes übliche Zeitlimit heran. Wer regelmäßig solche Mengen exportiert, braucht die Erzeugung im Hintergrund statt im Request.

- **Die Gerätelisten laden Einbauort, Betriebssystem und Standort jetzt vor.** Bei einem Kunden mit 40 Servern und 22 Switches kostete eine Seite mit 25 Zeilen rund 100 Abfragen, weil `einbauort()` je Gerät Einbau und Schrank einzeln nachlud. Jetzt sind es 5 bis 8. Lokal war der Unterschied kaum messbar — mit Netzwerk zwischen Anwendung und Datenbank werden daraus Sekunden. Zwei Tests halten die Grenze fest.

- **„PDF erstellen" scheiterte an fehlenden Schreibrechten.** DomPDF legt seine Schriftenliste (`installed-fonts.json`) und die aufbereiteten Schriften im konfigurierten Schriftordner ab — der zeigte auf `public/fonts`, wo der Webserver zu Recht nicht schreiben darf: `Permission denied`, Fehlerseite statt PDF. Der Ordner liegt jetzt unter `storage/fonts`, außerhalb des Web-Verzeichnisses, und wird beim Deploy angelegt.

- **Die Testsuite fiel gelegentlich grundlos aus.** `roles.name` trägt einen UNIQUE-Index, die Factory zog aber Personennamen aus einem endlichen Vorrat — auf der CI kam derselbe Name zweimal („Edgar Rudolph") und riss den ganzen Lauf mit. Der übersprungene Deploy war die Folge. Rollennamen sind jetzt fortlaufend nummeriert und können nicht mehr kollidieren.

- **„PDF erstellen" endete auf der Demo in einer Fehlerseite.** DomPDF hält das ganze Dokument im Speicher: Aus 0,4 MB HTML werden bei einem Kunden mit 26 Servern, 46 VMs und 53 Computern **136 MB Spitzenverbrauch**, davon 84 MB allein im PDF-Aufbau. Auf einem PHP mit den üblichen 128 MB bricht das ab — lokal mit 512 MB fiel es nie auf. Die PDF-Ausgabe hebt das Limit jetzt für ihren eigenen Aufruf auf 256 MB an, sonst bleibt alles beim eingestellten Wert.

- **Die USC-PIN der Securepoint UTM wurde nicht gespeichert.** Formular und Anzeige führten das Feld, die Validierung nicht — und der Controller speichert nur Validiertes. Die Eingabe verschwand kommentarlos. Ein Abgleich über alle 40 Formulare zeigt: Das war der einzige Fall dieser Art.
- **Dieselbe IP-Adresse ließ sich mehrfach vergeben** — zweimal am selben Gerät und zusätzlich an einem zweiten. Danach stand in der Dokumentation, die Adresse gehöre zu beiden, und der IP-Plan zählte sie doppelt als belegt. Jetzt ist eine Adresse pro Kunde eindeutig; was im Papierkorb liegt, blockiert sie nicht.
- **Das Bearbeiten eines Ansprechpartners endete im Fehler.** Die Routen laufen in `scopeBindings()`, Laravel leitet aus `{contactperson}` den Relationsnamen „contactpeople" ab — die Relation heißt hier `contactpersons`. Liste und Anlegen funktionierten, deshalb fiel es nie auf. Die Bindung wird jetzt gezielt aufgelöst, ohne dieselbe Beziehung ein zweites Mal zu benennen.
- **Die Löschwarnung war falsch:** „wird das Objekt unwiederruflich gelöscht" — es gibt einen Papierkorb, aus dem sich alles zurückholen lässt. Neuer Text sagt das, samt korrigierter Schreibweise. Betrifft alle 43 Bearbeiten-Formulare.
- **Das Betriebssystem war beim Anlegen vorausgewählt** (der erste Eintrag der Liste). Wer das übersah, dokumentierte still das falsche. Jetzt „— bitte wählen —"; bei VM und Windows-Lizenz wurde die Pflichtregel nachgezogen, deren Spalten NOT NULL sind — eine leere Auswahl hätte sonst einen Datenbankfehler statt einer Meldung ergeben. Nebenbei die Schreibweise „Betriebsystem" korrigiert.

### Added

- **Ansprechpartner haben eine Funktion** („Geschäftsführung", „IT-Verantwortlicher", „Lagerleitung"). Bisher standen nur Name, Telefon und E-Mail da — bei drei Kontakten wusste hinterher niemand mehr, wen er wofür anruft. Steht auch in der Liste und im PDF.
- **Das Kunden-Dashboard zählt die ganze Infrastruktur**: Internetanschluss, Firewall, Router, Switches, Accesspoints, Serverschränke und Patchfelder fehlten in der Übersicht — man konnte sie erfassen und sah sie dort nie wieder. Die Kacheln stehen jetzt von außen nach innen: erst der Anschluss und was daran hängt, dann die Server, dann die Arbeitsplätze.
- **Der Listen-Rauchtest prüft auch Anlegen- und Bearbeiten-Formulare.** Nur die Listen zu prüfen hätte den kaputten Ansprechpartner nie gezeigt.

### Changed

- **Die Kundensuche lädt höchstens 50 Treffer.** Sie holte bisher alle — die einzige Stelle der Anwendung, die mit dem Bestand linear mitwuchs (alle Listen paginieren, die globale Suche begrenzt je Objekttyp auf 20). Gibt es mehr Treffer, steht das jetzt unter der Liste, damit niemand den Kunden vermisst, der knapp nicht mehr dabei ist.

- **Die Inventar-Kacheln auf dem Kunden-Dashboard sind kleiner.** Seit auch Firewall, Router, Switches, Accesspoints, Schränke und Patchfelder mitzählen, sind es siebzehn statt zehn — in der alten Größe füllten sie den Bildschirm, bevor irgendetwas Inhaltliches kam. Kleineres Symbol, kleinere Zahl, mehr Kacheln je Zeile (bis zu acht auf breiten Monitoren, zwei am Telefon, damit dort nichts abgeschnitten wird).

- **Kennwörter ab 32 Zeichen ließen sich nicht speichern**: Die Kennwortfelder werden verschlüsselt abgelegt, ihre Spalten waren aber `varchar(255)` — so breit wie früher der Klartext. Ein Chiffrat ist länger als sein Klartext: 16 Zeichen ergeben 228, ab 32 Zeichen sind es 256. MySQL meldete dann „Data too long for column", das Speichern brach ab — bei erzeugten Kennwörtern also regelmäßig. 26 Spalten in 23 Tabellen sind jetzt `text` (Server, VMs, Switches, Router, WLAN, Accesspoints, Telefone, Kameras, Logins, Lizenzen und weitere). Bestehende Einträge bleiben unverändert und lesbar. Ein Test findet die verschlüsselten Felder künftig selbst und meldet jedes neue, dessen Spalte zu klein ist oder dessen Accessor gar keine Spalte trifft — der Fehler beim DSRM-Kennwort wäre damit aufgefallen.

- **Das DSRM-Kennwort lag im Klartext in der Datenbank**: Das Model trug einen Verschlüsselungs-Accessor namens `password()` — eine Spalte dieses Namens gibt es in `ad_domains` aber nicht, sie heißt `dsrmpassword`. Der Accessor lief also ins Leere, während alle übrigen Gerätekennwörter (BMC, Rustdesk) längst verschlüsselt gespeichert wurden. Model und Spalte passen jetzt zusammen; eine Migration verschlüsselt vorhandene Einträge nach und lässt bereits verschlüsselte in Ruhe. Die Spalte wächst dabei von `varchar(255)` auf `text`: Ein Chiffrat misst schon für ein kurzes Kennwort rund 200 Zeichen, bei den erlaubten 255 Eingabezeichen über 600 — in der alten Spalte wäre es still abgeschnitten und damit unbrauchbar gewesen. An Formular, Anzeige und PDF ändert sich nichts.

- **Die Factory für AD-Domänen war leer**: `domain`, `netbios` und `dsrmpassword` sind Pflichtspalten, die Vorgabe lieferte aber ein leeres Array — jedes `ADDomain::factory()->create()` ohne vollständige Angaben brach an der Datenbank ab. Im Seeder fiel das nie auf, weil der alle drei Felder mitgibt. Jetzt erzeugt sie stimmige Werte (`ad.<firma>.de`, NetBIOS in Großbuchstaben und auf 15 Zeichen begrenzt, ein Kennwort), und der Listen-Rauchtest duldet keine Liste mehr, die mangels Testdaten nur leer geprüft wird.

- **Die Maschinen-Liste antwortete mit 500**: In der Schleife stand ein Zugriff auf `$adressen` — eine Zeile, die aus den Listen mit Kartenansicht stammt, wo sie oberhalb der Schleife gesetzt wird. Sobald eine Maschine angelegt war, war die Seite nicht mehr aufrufbar; leer fiel es nicht auf. Ein Rauchtest ruft jetzt jede der 40 Listen einmal **mit Inhalt** auf und hätte das gefunden.

- **Die Kopfzeile der Gerätekarten am Telefon**: Der Name stand in `text-2xl` und brach mitten durch — aus `srv-hyperv-01.mustermann.local` wurden zwei Zeilen —, und was dahinter gehört (Betriebssystem, Support-Ende) lag unter dem Bearbeiten-Knopf. Am Telefon ist der Name jetzt kleiner gesetzt und passt in eine Zeile; ab 640 px bleibt alles wie bisher. Der Block darf außerdem schmaler werden als sein Inhalt, sonst schiebt er seine Nachbarn aus der Karte, statt umzubrechen. Gilt für alle 27 Listen, die diese Kopfzeile verwenden.

- **Am Handy fehlten Dashboard und die Suchen ganz**: Die fünf Symbole in der Kopfleiste (Dashboard, Kundensuche, Globale Suche, UTM, Rustdesk) tragen `hidden md:flex` und verschwinden unterhalb von 768 px — ersatzlos. Wer am Telefon arbeitete, kam an keinen dieser Wege. Sie stehen jetzt zusätzlich oben in der Seitenleiste, die dort ohnehin die Navigation trägt, mit Beschriftung statt nur als Symbol. Auf breiten Bildschirmen bleibt der Block ausgeblendet, damit nichts doppelt dasteht. Für Kunden-Zugänge sind sie wie bisher nicht sichtbar.

- **„Neu" öffnete nach einem abgebrochenen Bearbeiten das alte VLAN**: „Abbrechen" schloss nur das Fenster und ließ die Kennung des bearbeiteten Netzes stehen. Der nächste Klick auf „Neu" kam deshalb als „VLAN bearbeiten" mit den alten Werten hoch — ein Speichern hätte das bestehende Netz überschrieben statt ein neues anzulegen. Abbrechen, Escape und „Neu" räumen jetzt alle auf, über dieselbe Stelle im Code.

- **Die Überschrift der VLAN-Liste blieb nach jeder Aktion weg**: `x-sitetopmenu` leitet den Seitentitel aus dem Routennamen ab. Das geht, solange die Seite normal geladen wird — beim Rerender einer Livewire-Komponente heißt die laufende Route aber `livewire.update`, und die Überschrift verschwand ersatzlos, nach dem Anlegen, nach dem Löschen und künftig bei jedem Tastendruck in der Suche. Die Kopfleiste nimmt jetzt einen Titel entgegen; die 47 übrigen Listen leiten ihn weiter aus der Route ab.

- **Demo-Daten: DHCP-Bereiche als volle Adressen**: Im Seeder standen `100` und `200` — nur die letzten Oktette. Der IP-Plan versteht beides, das VLAN-Formular verlangt aber `ipv4`: Was die Demo-Daten enthielten, hätte man selbst gar nicht eintragen können. Jetzt `10.10.30.100` bis `10.10.30.200`.
- **Die VLAN-Nummer steht jetzt an der IP-Adresse**: In der Übersicht stand unter der Adresse nur der Netzname („Server & Management"), im Formular dasselbe. Beides zusammen ist das, was man braucht — der Name sagt wofür, die Nummer braucht man am Switch: „Server & Management · VLAN 30". Fehlt eines von beiden, bleibt das andere stehen; heißt die Bezeichnung schon wie das Netz, bleibt nur die Nummer. Gilt für alle 18 Listen und den IP-Block samt Auswahlliste. Die IPAM-Ansicht zeigte beides schon.
- **Alle 19 Geräteformulare auf dasselbe Muster gezogen** (Accesspoint, Kamera, Computer, DECT, IoT, Maschine, NAS, Switch, Sonstige Clients, Telefon, TK-Anlage, Drucker, Recorder, Router, UMA, UTM, USV, VM, Server): Abschnitte nach Identität, Hardware, Zugang, Fernwartung, Diensten und Notizen, zweispaltig auf 1024 px, „← Zurück" oben, IP-Adressen und Zugangsdaten in derselben Karte. 36 Formulare, alle mit einem Rauchtest über sämtliche Anlegen- und Bearbeiten-Routen abgesichert.
- **Bearbeiten läuft über dasselbe Modal**: Der Stift an einer VLAN-Karte öffnet es mit geladenen Werten, Titel und Knopf wechseln auf „VLAN bearbeiten" und „Speichern". Kein zweites Formular, kein Seitenwechsel — die Liste zeigt die Änderung sofort. Löschen gibt es dort ebenfalls — die Rückfrage erscheint als roter Kasten im Modal selbst statt als Browser-Dialog, und der Eintrag landet wie gewohnt im Papierkorb. IP-Adressen behalten dabei ihre VLAN-Zuordnung: Sie zeigen „—", solange das Netz im Papierkorb liegt, und stehen nach dem Wiederherstellen wieder am richtigen VLAN.
- **Das VLAN-Modal gibt es jetzt auch über der VLAN-Liste**: Es ersetzt dort das bisherige „Neu"; die Liste ist dafür selbst eine Livewire-Komponente geworden, damit das neue VLAN ohne Seitenwechsel erscheint, das auf eine eigene Seite führte — sonst stünden zwei Anlegen-Knöpfe nebeneinander. Beide Stellen nutzen dieselbe Komponente, damit das Formular nicht zweimal gepflegt werden muss. Unterschied: Am Gerät erbt das Netz dessen Standort, in der Liste wird er abgefragt — dort gibt ihn nichts vor.
- **VLAN anlegen, ohne das Formular zu verlassen**: Im Block „Weitere IP-Adressen" steht neben der VLAN-Auswahl ein „+ Neues VLAN". Das Modal führt dieselben zehn Felder wie das VLAN-Formular — Bezeichnung, VLAN-ID, Netz, Subnetzmaske, CIDR, Gateway, DNS 1/2 und den DHCP-Bereich. Nach dem Anlegen ist das neue Netz gleich ausgewählt, man macht also dort weiter, wo man war. Vorher kostete ein fehlendes VLAN den Weg aus dem Geräteformular heraus und die halb ausgefüllte Zeile.
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
