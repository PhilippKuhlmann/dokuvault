# Mobile-API (`/api/mobile`) – Vertrag zwischen WebApp und iOS-App

Die eine Quelle der Wahrheit für die Schnittstelle zwischen
`dokumentation-os` (Laravel) und `dokuvault-ios` (SwiftUI).

**Regel:** Wer an `/api/mobile` etwas ändert (Route, Feld, Fehlerfall), trägt es
hier ein – im selben Commit. Neue Felder sind erlaubt (die App ignoriert
Unbekanntes), Umbenennen oder Entfernen nur nach Absprache, weil ältere
App-Versionen sonst „Die Antwort des Servers ist unlesbar“ zeigen.

Quellen: `routes/api.php` (Block `prefix('mobile')`),
`app/Http/Controllers/API/Mobile/*`, `app/Support/MobilObjekte.php`,
`app/Support/ObjectForm.php` ↔ `DokuVault/API.swift`, `DokuVault/Modelle.swift`.

## Allgemein

| Thema | Regel |
|---|---|
| Basis | `https://<server>/api/mobile/…` – nur https |
| Auth | `Authorization: Bearer <token>` (Sanctum, Ability `mobil`) |
| Sprache | `Accept-Language` → Server antwortet in dieser Sprache (`SetLocale`) |
| Format | `Accept: application/json`, Body JSON; Datei-Upload nur per `POST` multipart |
| Schlüssel | JSON in **snake_case**; die App wandelt automatisch in camelCase (`convertFromSnakeCase` / `convertToSnakeCase`) |
| Daten | Datum als `YYYY-MM-DD`, Zeitpunkte als ISO 8601 |
| Geheimnisse | Gehen **nie** in Listen/Details/Formularen hinaus – nur „ist gesetzt“. Wert einzeln per `POST` (gebremst, protokolliert) |

### Benennung (bekannte Uneinheitlichkeit)

- **Antworten** verwenden deutsche Schlüssel (`hoehe`, `ort`, `einbauten`).
- **Anfragen** an Rack und Formulare verwenden die **Spaltennamen** der Datenbank
  (`site_id`, `height_units`, `location`, `note`) bzw. die Feldnamen aus
  `config/forms.php`. Ausnahme: Einbauten (`seite`, `position`, `hoehe`, `typ`,
  `id`, `katalog_id`).
- Wird so belassen; neue Endpunkte folgen demselben Muster.

### Leere Werte / `null` – wichtig für Swift

Swifts synthetisiertes `Encodable` **lässt `nil`-Optionals weg**. Der Server
schreibt bei Updates aber nur Felder, die ankommen. Ein Feld leeren heißt
deshalb: es **ausdrücklich** schicken – `null` beim Rack, `""` in Objektformularen
(so hält auch das Formular „leer“, siehe `ObjectForm::emptyValues`).

### Fehler

| Status | Bedeutung | Body | App (`APIFehler`) |
|---|---|---|---|
| 401 | Token fehlt/abgelaufen/widerrufen | – | `.nichtAngemeldet` → zurück zur Anmeldung |
| 403 | Kein Recht, oder 2FA muss erst im Browser eingerichtet werden | `{message}` | `.keinZugriff(message)` |
| 404 | Unbekannt **oder** fremder Kunde (bewusst nicht unterscheidbar) | `{message?}` | `.nichtGefunden` |
| 422 | Validierung | `{message, errors: {feld: [..]}}` | `.pruefung(erste Feldmeldung)` |
| 429 | Bremse (Login, Kennwortabruf) | `{message}` | `.gebremst(message)` |
| 5xx | Serverfehler | – | `.server(status)` |

## Anmeldung

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| POST | `login` | `{username, password, geraet}` | `Token` oder `{zweite_stufe: true, vorgang}` |
| POST | `login/two-factor` | `{vorgang, code}` | `Token` |
| POST | `logout` | – | 204 (nur dieses Token) |
| GET | `me` | – | `Nutzer` |

```
Token  = { token, laeuft_ab (ISO 8601), nutzer: Nutzer }
Nutzer = { name, username, kunde: Kunde|null,
           rechte: { kennwoerter: bool, schluessel: bool, papierkorb: bool,
                     checklisten: bool, agenten: bool, pdf: bool,
                     erstaufnahme: bool } }
Kunde  = { slug, name }
```

`vorgang` ist verschlüsselt, zeitlich begrenzt und gibt genau **ein** Token.

## Lesen

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers?suche=` | `[Kunde]` (max. 500; Kundennutzer: nur der eigene) |
| GET | `customers/{slug}` | `{slug, name, typen: [{typ, titel, anzahl}]}` (nur Typen mit `anzahl > 0`) |
| GET | `customers/{slug}/dashboard` | `Dashboard` (siehe unten) |
| GET | `customers/{slug}/trash` | `Papierkorb` (siehe unten) |
| GET | `customers/{slug}/network-map?site=` | `Netzplan` (siehe unten) |
| GET | `customers/{slug}/checklists` | `{offen: [...], fertig: [...], darf_abhaken, darf_freigeben}` |
| GET | `customers/{slug}/agents?suche=` | `{agenten: [...], weitere, token: [...], takte: [...]}` |
| GET | `customers/{slug}/pdf` | `{auftrag: PdfAuftrag\|null}` |
| GET | `customers/{slug}/wizard` | `Erstaufnahme` (siehe unten) |
| GET | `profile` | `Profil` (siehe unten) |
| GET | `changelog` | `{version, text, gekuerzt}` (roher Markdown) |
| GET | `customers/{slug}/{typ}?site=` | `{typ, titel, eintraege: [ObjektKurz], weitere: bool, darf_anlegen: bool, standorte: [{id, name}]\|null}` |
| GET | `search?q=` | `[{typ, titel, weitere: int, treffer: [ObjektKurz]}]` |
| GET | `objects/{typ}/{id}` | `ObjektDetail` |
| POST | `objects/{typ}/{id}/secret/{feld}` | `{wert}` / 404 „Kein Kennwort hinterlegt.“ |
| POST | `credentials/{link_id}/password` | `{wert}` / 404 |

```
ObjektKurz   = { typ, id, name, untertitel|null, kunde: Kunde|null }

ObjektDetail = ObjektKurz + {
  einzahl,
  gruppen:      [{ titel, felder: [Feld] }],
  ip_adressen:  [{ id, dhcp: bool, adresse, bezeichnung|null, netz|null }],
  zugangsdaten: [Zugang],
  einbauort:    { text, rack_id, kunde: Kunde } | null,
  support_ende: { betriebssystem, datum, zustand: "abgelaufen"|"bald"|"ok" } | null,
  scan_ziele:   [ScanZiel]                                 // nur beim Scanner gefüllt, sonst []
}

ScanZiel = { id, name|null, art: "smb"|"email"|"ftp"|"cloud"|"other", art_label,
             ziel, login: { id, name } | null }

Feld = {
  name, label,
  art: "text"|"mehrzeilig"|"datum"|"link"|"ip"|"geheim",   // unbekannte Art → als "text" zeigen
  wert: string|null,                                       // bei "geheim" immer null
  verweise?: [{ typ, id, name }],                          // Host-Felder: Sprung zu objects/{typ}/{id}
  liste?:    [string]                                      // Verknüpfungen, Dienste: aufklappbare Liste
  kacheln?:  [{name, farbe: "#rrggbb"|null, schrift: "#rrggbb"|null}]  // nur Dienste: Farbe aus dem Dienste-Katalog, Schrift vom Server berechnet (Helligkeit > 0,6 → #111827, sonst #ffffff); ohne Katalogfarbe beide null = grau
}

Zugang = { id (= link_id), name, benutzer|null, art: "kennwort"|"schluessel",
           notiz|null, hat_kennwort: bool, fingerprint: string|null }
```

`?site=<id>` engt die Liste auf einen Standort ein – derselbe Filter, den
die Seitenleiste im Browser in der Sitzung hält. `standorte` ist die Auswahl
dafür und **null** bei einer Art, die an keinem Standort hängt; dann gibt es
nichts zu filtern. Ein Standort eines anderen Kunden zählt als kein Filter,
nicht als Fehler.

Die Werte in `rechte` sagen der App, welche festen Wege sie überhaupt
anbietet (Papierkorb, Checklisten, Auto-Dokumentation, PDF-Export,
Erstaufnahme-Assistent) – ein Weg, der nur in
eine 403 führt, ist keiner. Ein älterer Server schickt sie nicht; dann bleibt
der jeweilige Weg verborgen.

Leere Felder werden **nicht** ausgeliefert.

`darf_anlegen` ist `true`, wenn der Nutzer das Recht `{typ}_create` hat **und**
es in der App einen Weg zum Anlegen gibt: Formular-Typen (`customers/{slug}/forms/{typ}`)
`rack` (`POST customers/{slug}/racks`) und `patchpanel`
(`POST customers/{slug}/patchpanels`). Für Netzwerkdosen (`patchport`) usw.
immer `false` – eine Dose entsteht mit ihrem Patchfeld.

**Bewusste Lücke – Dateien:** Felder der Art `datei` fehlen in `gruppen`
(`MobilObjekte::OHNE`). Solange nicht geklärt ist, wie die App eine geschützte
Datei abruft (eigener Endpunkt mit Recht und Protokoll), zeigt sie keine.
Hochladen geht dagegen schon (`datei` beim Anlegen/Bearbeiten, multipart).

## Dashboard

`GET customers/{slug}/dashboard` liefert, was im Browser auf dem
Kunden-Dashboard steht. Gerechnet wird es in `App\Support\Kundenuebersicht` –
dieselbe Klasse bedient die Blade-Seite. Zwei Rechnungen für dieselbe Frist
wären zwei Wahrheiten.

```
Dashboard = {
  slug, name,
  fristen: {
    lizenzen:     [Frist],   // Software-Lizenzen, Frist aus Admin -> Fristen (Verträge)
    zertifikate:  [Frist],   // dieselbe Frist
    garantien:    [Frist],   // Hardware aller Arten, eigene Garantiefrist
    support_ende: [Frist]    // Geräte (eol_date) und Betriebssysteme, EOL-Frist
  },
  standorte:       [ObjektKurz],
  ansprechpartner: [ObjektKurz + { rolle|null, telefon|null, mail|null }],
  agenten:         [{ name, art, text, schwer: bool }] | null,
  aenderungen:     [{ name, art, ereignis, wer|null, wann (ISO 8601),
                      typ|null, id|null }]
}

Frist = { name, art, datum (ISO 8601), tage: int, typ|null, id|null }
```

`tage` ist das Vorzeichen, an dem gefärbt wird: negativ abgelaufen, `0` heute.
Der Browser nimmt 14 Tage als Schwelle für Gelb, beim Support-Ende 90 – dort
steht Fernes als Monat statt als Tagezahl.

`typ` und `id` sind der Weg zum Objekt (`objects/{typ}/{id}`). `typ` ist
**null**, wenn die App die Art nicht öffnen kann: Die Fristen sammeln über
`config('custom.trashables')`, die App kennt `MobilObjekte::typen()` – eine
Checkliste liegt im Papierkorb, hat in der App aber keine Ansicht. Bei
`aenderungen` ist zusätzlich `id` null, wenn `ereignis` `deleted` ist: Das
Objekt liegt im Papierkorb, es gibt nichts zu öffnen.

`agenten` ist `null`, wenn der Angemeldete keine Agenten verwaltet
(`agent_manage`) oder der Kunde weder Agent noch Token hat – dann zeigt die
App keine Karte, keine leere. `schwer` trennt Rot von Gelb.

Jede Kachel prüft die Rechte einzeln (`{typ}_viewAny`): Ohne das Recht kommt
sie leer zurück, nicht gefüllt und versteckt.

## Papierkorb

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/trash` | `{eintraege: [PapierkorbZeile], gekuerzt: [{titel, anzahl}]}` |
| POST | `customers/{slug}/trash/{typ}/{id}/restore` | `{objekt: ObjektKurz|null}` |

```
PapierkorbZeile = { typ, titel, name, geloescht_am (ISO 8601), id,
                    darf_wiederherstellen: bool }
```

Gerechnet wird in `App\Support\Papierkorb`, derselben Klasse, die die Seite
im Browser fuellt.

Sehen braucht `see_hidden` (sonst 403) **und** das Recht auf die Art
(`{typ}_viewAny`) – ohne das stuenden hier Namen von Objekten, von denen der
Nutzer nichts wissen soll. Zurueckholen braucht zusaetzlich `{typ}_delete`:
Es macht ein Loeschen rueckgaengig, und das darf nicht jeder, der in den
Papierkorb sehen darf. `darf_wiederherstellen` sagt es je Zeile, damit die
App den Knopf gar nicht erst anbietet.

Je Art hoechstens 100 Eintraege, das Neueste zuerst. Was abgeschnitten wurde,
steht in `gekuerzt` – ein stilles Abschneiden liest sich wie „mehr ist nicht
da“. `gekuerzt` ist leer, wenn nichts gekuerzt wurde.

`typ` ist ein Schluessel aus `config('custom.trashables')`, nie etwas
Beliebiges: ein anderer Wert ist 404. Ein Objekt eines anderen Kunden hinter
der eigenen Adresse ist ebenfalls 404.

`objekt` in der Antwort auf das Zurueckholen ist `null`, wenn die App die Art
nicht oeffnen kann (`MobilObjekte::typen()`) – zurueckgeholt ist es trotzdem.

## Netzplan

`GET customers/{slug}/network-map` liefert den logischen Netzplan: Internet →
Gateway → VLAN → Geräte, je Standort. Gebaut wird er in
`App\Support\NetworkMap`, derselben Klasse, die die Seite im Browser
zeichnet. Der Browser stellt die Spalten nebeneinander, auf dem Telefon ist
dasselbe ein aufklappbarer Baum – die Daten sind dieselben.

```
Netzplan = { standorte: [{
  id, name,
  spalten: [{
    gateway: Knoten|null,        // null: das Gateway ist nicht dokumentiert
    uplinks: [Knoten],
    netze:   [{ netz: {id, name, adresse|null, gateway|null},
                gruppen: [{ titel, geraete: [Knoten] }] }]
  }],
  einzeln:              [Knoten],  // Firewall/Router ohne VLAN und Anschluss
  uplinks_ohne_geraet:  [Knoten],  // Anschluss, der sich keinem Gerät zuordnen ließ
  ohne_vlan:            [Knoten]   // Gerät mit Adresse außerhalb jedes VLANs
}] }

Knoten = { name, art|null, beisatz|null, adresse|null, typ|null, id|null }
```

`?site=<id>` filtert auf einen Standort – derselbe Filter, den die
Seitenleiste im Browser in der Sitzung hält. Ein Standort eines anderen
Kunden zählt als kein Filter, nicht als Fehler.

`typ` und `id` sind der Weg zum Gerät (`objects/{typ}/{id}`); `netz.id` führt
ins IPAM (`customers/{slug}/networks/{id}`). `typ` ist **null**, wo der
Nutzer die Liste dieser Art nicht sehen darf – dort lässt auch der Browser
den Verweis weg. Der Name steht trotzdem da: Der Plan soll vollständig sein.

Alles stammt aus dokumentierten Daten, keine Verkabelung. Was sich nicht
einordnen ließ, fällt nicht weg, sondern steht in `einzeln`,
`uplinks_ohne_geraet` und `ohne_vlan` – so zeigt der Plan auch die Lücken in
der Dokumentation.

Braucht `network_viewAny`, sonst 403.

## Checklisten

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/checklists` | `{offen: [Offen], fertig: [Kurz], darf_abhaken: bool, darf_freigeben: bool}` |
| GET | `customers/{slug}/checklists/{id}` | `Checkliste` |
| POST | `customers/{slug}/checklists/{id}/items/{punkt}/toggle` | `{erledigt: bool, fertig: bool}` / 409 |
| POST | `customers/{slug}/checklists/{id}/items/{punkt}/approve` | `{freigegeben: true}` |
| GET | `customers/{slug}/checklists/form` | Auswahl für den Antrag |
| GET | `customers/{slug}/checklists/{id}/ad-script` | das PowerShell-Skript (Datei) |
| POST | `customers/{slug}/checklists/onboarding` | `{checkliste: Kurz}` |
| POST | `customers/{slug}/checklists/offboarding` | `{checkliste: Kurz}` |

```
Kurz  = { id, name, art: "onboarding"|"offboarding", position|null,
          stichtag (ISO 8601), fertig_am (ISO 8601)|null }
Offen = Kurz + { punkte: int, erledigt: int, wartet_auf_freigabe: int }

Checkliste = Kurz + {
  notiz|null, benutzername|null, mail|null,
  abschnitte: [{ titel, punkte: [Punkt] }],
  darf_abhaken: bool, darf_freigeben: bool
}

Punkt = { id, titel, hinweis|null, zustaendig, erledigt: bool,
          erledigt_am (ISO 8601)|null, erledigt_von|null,
          braucht_freigabe: bool, freigegeben: bool, freigegeben_von|null }
```

Drei Rechte wie im Browser: `checklist_manage` (die IT arbeitet die Listen
ab), `checklist_request` (der Kunde meldet einen neuen Mitarbeiter) und
`checklist_approve` (die Leitung gibt heikle Laufwerke frei). Jedes davon
darf die Listen **sehen**; ohne alle drei ist es 403. Abhaken braucht
`checklist_manage`, Freigeben `checklist_approve` – `darf_abhaken` und
`darf_freigeben` sagen es, damit die App die Knöpfe gar nicht erst anbietet.

Ein Punkt mit `braucht_freigabe` und ohne `freigegeben` lässt sich **nicht**
abhaken: **409** mit dem Grund im `message`. Das ist kein Fehler der App,
sondern eine Reihenfolge – erst die Leitung, dann die IT.

Ist der letzte Punkt abgehakt, gilt die Liste als erledigt (`fertig: true`);
wird ein Haken wieder weggenommen, ist sie wieder offen.

Eine Liste eines anderen Kunden – und ein Punkt einer anderen Liste – ist ein
404, nicht nur verborgen (scoped bindings).

### Beantragen

```
Formular = {
  laufwerke: [{ id, name, buchstabe|null,
                stufen: [{ stufe: "r"|"rw", titel }] }],
  programme: [{ id, name, braucht_freigabe: bool }],
  ad_benutzer: [{ id, name, benutzername }],
  darf_offboarding: bool
}
```

`GET .../form` liefert, was dieser Kunde im Antrag anbietet. Welche `stufen`
ein Laufwerk hat, hängt an den hinterlegten AD-Gruppen: ohne Schreibgruppe
gibt es kein „lesen und schreiben".

`POST .../onboarding` nimmt `first_name`, `last_name`, `reference_date`,
`note`, `drives` (Laufwerk-Id → Stufe) und `options` (Programm-Ids).
Benutzername und E-Mail rechnet der Server aus dem Namen – dasselbe Schema
wie die AD-Benutzer der Dokumentation. Melden darf `checklist_request` oder
`checklist_manage`.

`POST .../offboarding` nimmt `ad_user_id`, `reference_date` und `note` und
braucht **`checklist_manage`**: Ein Offboarding anzustoßen ist Sache der IT,
nicht des Melders. Deshalb ist `ad_benutzer` ohne dieses Recht leer. Ein
AD-Benutzer eines anderen Kunden ist 404.

### AD-Skript

`GET .../{id}/ad-script` liefert das PowerShell-Skript für den AD-Teil als
Datei. Laufen muss es auf einem Domänencontroller, nicht auf dem Telefon –
die App holt es und gibt es weiter.

Braucht `checklist_manage`. Ohne hinterlegten Benutzernamen ist es **422**:
Dann hat das Skript nichts anzulegen. **Jeder Abruf gibt einen neuen
Schlüssel aus** – das vorher geholte Skript kann danach nicht mehr
zurückmelden –, und jeder Abruf steht im Protokoll
(`checklist_script_download`).

## Auto-Dokumentation (Agenten)

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/agents?suche=` | `{agenten: [Agent], weitere: bool, token: [Token], takte: [{minuten, titel}]}` |
| PUT | `customers/{slug}/agents/installations/{id}` | `{agent: Agent}` |
| POST | `customers/{slug}/agents/installations/{id}/run` | `{agent: Agent}` |
| POST | `customers/{slug}/agents/tokens/{id}/lock` | `{gesperrt: true}` |
| POST | `customers/{slug}/agents/tokens/{id}/unlock` | `{gesperrt: false}` |

```
Agent = { id, hostname, domaene|null, art, version|null,
          zuletzt_gemeldet (ISO 8601)|null, stumm: bool,
          takt_minuten: int, laufen_angefordert: bool, token|null,
          aufgaben: [{ rolle, titel, an: bool, erkannt: bool,
                       fehlgeschlagen: bool, meldung|null }] }

Token = { id, name, standort|null, gesperrt: bool,
          laeuft_ab (ISO 8601)|null, abgelaufen: bool }
```

Alles braucht `agent_manage`, auch das Lesen – sonst 403.

`PUT .../installations/{id}` nimmt `roles` (eine Teilmenge der `rolle`-Werte
aus `aufgaben`) und `interval_minutes` (einer der `takte`). Alles andere ist
**422**. Jede Aufgabe der Art lässt sich einschalten, `erkannt` oder nicht:
Die Erkennung kann danebenliegen, und das Agentenprotokoll sagt dann, woran
es scheiterte. Gültig wird es ab dem nächsten Lauf.

`run` ist „jetzt melden“ – der Agent holt es sich beim nächsten Checkin ab,
innerhalb von fünf Minuten.

Höchstens 200 Installationen; `weitere` sagt, dass es mehr sind. `?suche=`
greift auf Hostname und Domäne, wie der Filter im Browser.

**Nicht in der App:** Token anlegen und erneuern, die Installationsdateien
(.exe, .sh) und das Löschen von Token und Einträgen. Ein Geheimnis, das man
nur einmal sieht, gehört nicht auf ein Telefon, das man danach weglegt.

## PDF-Export

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/pdf` | `{auftrag: PdfAuftrag\|null}` |
| POST | `customers/{slug}/pdf` | `{auftrag: PdfAuftrag}` |
| GET | `customers/{slug}/pdf/{id}` | die Datei (application/pdf) |

```
PdfAuftrag = { id, status: "offen"|"laeuft"|"fertig"|"fehler",
               laeuft_noch: bool, haengt: bool, fertig: bool,
               groesse|null, angelegt (ISO 8601),
               fertig_am (ISO 8601)|null, meldung|null }
```

Braucht `create_pdf`, sonst 403.

Bestellt, nicht sofort gerendert: `POST` legt einen Auftrag an, den der
Scheduler abarbeitet; die App fragt mit `GET` den Stand ab. 40 Server, 90 VMs
und 160 Computer brauchen 370 MB und 15 Sekunden – das überlebt kein Request.
Solange einer läuft, gibt `POST` denselben Auftrag zurück, statt einen
zweiten anzulegen.

`haengt` heißt: Niemand arbeitet die Warteschlange ab. Dann hat weiteres
Nachfragen keinen Zweck, und die App soll es sagen statt endlos zu drehen.

Das PDF trägt **jedes Kennwort** des Kunden. Deshalb gehört es dem, der es
bestellt hat: Der Auftrag eines anderen Nutzers ist 403, auch für den, der
denselben Kunden sehen darf. Jeder Abruf steht im Protokoll
(`pdf_heruntergeladen`), wie ein einzeln angesehenes Kennwort. Eine
aufgeräumte Datei ist **410**, nicht 404 – sie war da.

## Profil, Sprache, Changelog

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `profile` | `Profil` |
| PUT/PATCH | `profile` | `Profil` |
| PUT | `profile/password` | `{message, neu_anmelden: true}` |
| POST | `profile/two-factor/begin` | `{geheimnis, adresse, braucht_mail_code, vorgang}` |
| POST | `profile/two-factor/confirm` | `{codes: [...]}` |
| POST | `profile/two-factor/recovery-codes` | `{codes: [...]}` |
| DELETE | `profile/two-factor` | `{zweite_stufe: false}` |
| DELETE | `profile` | `{message, neu_anmelden: true}` |
| GET | `changelog` | `{version, text, gekuerzt: bool}` |

```
Profil = { name, username, mail|null, sprache|null,
           sprachen: [{code, titel}],
           gesperrt: bool, zweite_stufe: bool }
```

`PUT profile` nimmt `name`, `email`, `locale` und `current_password`.
`locale` ist einer der `sprachen`-Codes oder **leer** – leer heißt „der
Sprache des Geräts folgen“, nicht Leerstring. Eine andere Sprache ist 422.

Eine **neue E-Mail-Adresse braucht `current_password`**: Dorthin gehen die
Kennwort-Zurücksetzungen, und eine geliehene Sitzung wäre damit das Konto.
Die alte Adresse erfährt von der Änderung. Name und Sprache gehen ohne.

`gesperrt` ist der Demo-Zugang: Er ist geteilt, was einer ändert, träfe alle.
Dann sind Profil und Kennwort 422 mit der Begründung.

`PUT profile/password` nimmt `current_password`, `password` und
`password_confirmation`. **Danach ist die App abgemeldet**: `User::booted()`
entzieht bei jedem Kennwortwechsel alle App-Token – an einer Stelle für jeden
Weg (Zurücksetzen, Profil, Admin-Bereich, Einladung). `neu_anmelden: true`
sagt es, damit die App nicht erst in den nächsten 401 läuft.

`changelog` liefert rohen Markdown-Text und die Version, wie sie im Browser
unter der Seitenleiste hängt – aber nur die **jüngsten Einträge**: Die Datei
ist über 300 KB groß, und ein Textfeld mit dreihunderttausend Zeichen zeigt
gar nichts mehr. Geschnitten wird an einer Überschrift, nicht mitten im Satz;
`gekuerzt` sagt, dass es weitergeht – vollständig steht es im Browser.

### Zweite Stufe einrichten

Zwei Schritte, weil die Mobil-Schnittstelle keine Sitzung hat, in der das
begonnene Geheimnis liegen könnte:

`POST profile/two-factor/begin` erzeugt es und gibt zurück: `geheimnis` (zum
Abtippen), `adresse` (`otpauth://…`, daraus malt die App den QR-Code),
`braucht_mail_code` und `vorgang` – das Geheimnis **verschlüsselt**, 15
Minuten gültig, an diesen Nutzer gebunden. Dasselbe Verfahren wie bei der
Anmeldung mit zweiter Stufe.

`POST profile/two-factor/confirm` nimmt `vorgang`, `code` (aus der
Authenticator-App) und, wo nötig, `mail_code`. Hat der Zugang eine
E-Mail-Adresse, geht beim Beginnen ein sechsstelliger Code dorthin und wird
hier verlangt: Das Kennwort allein ließe jeden, der es kennt, seine eigene
App einrichten und den Inhaber aussperren. Die Antwort enthält die
**Wiederherstellungscodes – einmal und nie wieder**.

`POST profile/two-factor/recovery-codes` und `DELETE profile/two-factor`
brauchen beide `password`. Eine vom Administrator **verlangte** zweite Stufe
lässt sich nicht abschalten (422) – sonst wäre die Pflicht ein Knopf, den man
einmal drückt. Ein Geheimtext von anderswo geht als `vorgang` nicht durch
(der Zweck steht mit darin).

### Konto löschen

`DELETE profile` mit `password`. Nicht rückgängig zu machen; die Token dieses
Nutzers fallen mit ihm weg, `neu_anmelden: true` sagt es der App. Ein
Demo-Zugang lässt sich nicht löschen (422).

## Erstaufnahme-Assistent

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/wizard` | `Erstaufnahme` |
| POST | `customers/{slug}/wizard/start` | `Erstaufnahme` |
| POST | `customers/{slug}/wizard/next` | `Erstaufnahme` |
| POST | `customers/{slug}/wizard/goto` | `Erstaufnahme` |
| POST | `customers/{slug}/wizard/complete` | `Erstaufnahme` |

```
Erstaufnahme = {
  laeuft: bool, aktuell: <schluessel>|null, fertig (ISO 8601)|null,
  schritte: [{ schluessel, gruppe, titel, frage, typ|null,
               erledigt: bool, uebersprungen: bool, angelegt: int }]
}
```

Der Ablauf kommt aus `App\Support\Erstaufnahme`, derselben Klasse, die die
Livewire-Komponente im Browser führt.

**Ausgefüllt wird mit den Formularen**, die der Server ohnehin beschreibt:
`typ` ist der Schlüssel für `customers/{slug}/forms/{typ}` und
`POST customers/{slug}/{typ}`. Der Assistent sagt nur, welcher Typ gerade
dran ist. `typ` ist **null**, wo die App die Art nicht über ein Formular
anlegt – VLANs etwa haben ihren eigenen Weg (`customers/{slug}/networks`).

`next` nimmt `wie`: `erledigt` (Weiter nach dem Eintragen), `uebersprungen`
(Weiter ohne) oder `gruppe` (die ganze Gruppe, „keine Telefonie"). Wurde in
diesem Durchlauf trotzdem etwas angelegt, zählt der Schritt als erfasst – nur
ein wirklich leer gelassener gilt als übersprungen. Alles andere ist 422.

`goto` ohne `schritt` geht einen zurück; mit `schritt` direkt dorthin.
Ein Schritt, den der Nutzer nicht anlegen darf, ist für ihn kein Schritt:
**404**, und der Durchlauf bleibt stehen. Springen ist kein Bearbeiten – der
verlassene Schritt wird weder erledigt noch übersprungen.

Es gibt nur **Schritte, für die der Nutzer das `{typ}_create`-Recht hat**.
Ohne ein einziges ist der ganze Assistent 403. Hinter dem letzten Schritt ist
der Durchlauf fertig (`aktuell: null`, `fertig` gesetzt).

## Netzwerkschränke

Alle unter `customers/{slug}/racks`; fremde Racks/Einbauten → 404 (scoped bindings).
Die Liste kommt über `customers/{slug}/rack`.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| POST | `/` | `RackKoerper` | 201 `Schrank` |
| GET | `/{rack}` | – | `Schrank` |
| PUT | `/{rack}` | `RackKoerper` (vollständig, `null` explizit!) | `Schrank` / 422 wenn Einbau über neue Höhe ragt |
| DELETE | `/{rack}` | – | 204 |
| GET | `/{rack}/palette` | – | `Palette` |
| POST | `/{rack}/items` | `{seite, position?, typ+id \| katalog_id}` | 201 `Einbau` |
| PATCH | `/{rack}/items/{item}` | `{position?, hoehe? (1–8)}` | `Einbau` |
| DELETE | `/{rack}/items/{item}` | – | 204 |

```
RackKoerper = { site_id, name, height_units (1–60), location|null, note|null }

Schrank = { id, name, hoehe, ort|null, notiz|null, standort: {id,name}|null, kunde,
            darf_bearbeiten, darf_loeschen, seiten: [{seite, titel}], einbauten: [Einbau] }
Einbau  = { id, name, seite, position, hoehe, oberste_he, volle_tiefe, darstellung|null,
            geraet: { typ|null, id } | null }
Palette = { geraete: [{ typ, titel, eintraege: [{id, name, hoehe}] }],
            katalog: [{ id, name, hoehe, volle_tiefe, darstellung|null }] }
```

`position` ist die unterste belegte HE, `oberste_he` rechnet der Server.
Ohne `position` beim Einbauen → unterste freie HE.

## Patchfelder

Alle unter `customers/{slug}/patchpanels`; fremde Patchfelder → 404 (scoped bindings).
Die Liste kommt über `customers/{slug}/patchpanel`, die Ports entstehen mit dem Patchfeld.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| POST | `/` | `PatchfeldKoerper` | 201 `Patchfeld` (mit allen Ports) |
| GET | `/{patchpanel}` | – | `Patchfeld` |
| PUT | `/{patchpanel}` | `PatchfeldKoerper` (vollständig) | `Patchfeld` / 422 `port_count`, wenn oberhalb noch Ports dokumentiert sind |
| DELETE | `/{patchpanel}` | – | 204 (Papierkorb) |
| PUT | `/{patchpanel}/ports` | `{ ports: [{ id, outlet?, label?, network_switch_id?, switch_port?, note? }] }` | `Patchfeld` |

```
PatchfeldKoerper = { site_id, name, port_count (1–96), height_units (1–10),
                     manufacturer|null, model|null, note|null }

Patchfeld = { id, name, ports_anzahl, hoehe, hersteller|null, modell|null, notiz|null,
              standort: {id,name}|null, kunde, einbauort: string|null,
              darf_bearbeiten, darf_loeschen,
              ports: [{ id, nummer, dose|null, raum|null, switch: {id,name}|null,
                        switch_port|null, notiz|null }],
              switches: [{ id, name }] }        // Auswahl für network_switch_id
```

Ports: Nur gesendete Ports und Spalten werden geschrieben, `""` leert. Eine
Port-Id eines anderen Patchfelds wird übergangen. `network_switch_id` nur
Switches desselben Kunden (sonst 422). „Durchnummerieren“ macht die App selbst
und schickt das Ergebnis.

## Am Gerät: Zugänge, IP-Adressen, Scan-Ziele

Ändert das Gerät, braucht also immer `{typ}_update` – auch die GETs, die die
Auswahl liefern. Ein Typ ohne den Block → 404 (z. B. Domain hat keine IP-Adressen,
nur der Scanner hat Scan-Ziele). Die Antwort auf POST ist die neue Liste.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| GET | `objects/{typ}/{id}/credentials` | – | `{ eintraege: [Zugang], verfuegbar: [{id, name, benutzer\|null, art}], darf_neu: bool }` |
| POST | `objects/{typ}/{id}/credentials` | `{login_id, note?}` **oder** `{name, username?, password?, note?}` | 201 `[Zugang]` |
| DELETE | `objects/{typ}/{id}/credentials/{link_id}` | – | 204 (nur die Verknüpfung; der Zugang bleibt) |
| GET | `objects/{typ}/{id}/ip-addresses` | – | `{ eintraege: [IP], netze: [{id, name, naechste_freie\|null}] }` |
| POST | `objects/{typ}/{id}/ip-addresses` | `{address, network_id?, label?}` **oder** `{dhcp: true, network_id, label?}` | 201 `[IP]` |
| DELETE | `objects/{typ}/{id}/ip-addresses/{ip_id}` | – | 204 |
| GET | `objects/scanner/{id}/scan-targets` | – | `{ eintraege: [ScanZiel], verfuegbar: [ScanZiel], arten: [{wert, label}], logins: [{id, name}], darf_neu, darf_verknuepfen }` |
| POST | `objects/scanner/{id}/scan-targets` | `{ziel_id}` **oder** `{kind, target, name?, login_general_id?}` | 201 `[ScanZiel]` |
| DELETE | `objects/scanner/{id}/scan-targets/{ziel_id}` | – | 204 (nur die Verknüpfung) |

Regeln:
- Zugänge: nur Arten, die der Nutzer sehen darf (Kennwort: `logingeneral_viewAny`,
  Schlüssel: `sshkey_viewAny`; keins von beiden → 403). Neu anlegen braucht
  `logingeneral_create`. Fremder Zugang → 422 `login_id`.
- IP: Eine Adresse gibt es je Kunde nur einmal → 422 `address`. Bei `dhcp: true`
  ist `network_id` Pflicht und `address` verboten. `naechste_freie` ist der
  Vorschlag, den auch der Browser bei Wahl des Netzes einträgt.
- Scan-Ziele: neu anlegen braucht `scantarget_create`, vorhandene verknüpfen
  `scantarget_viewAny` (sonst ist `verfuegbar` leer).

## IPAM: VLANs, IP-Plan, reservierte Bereiche

Im Browser zwei Seiten (VLAN-Liste und IP-Plan), in der App eine Liste mit
Auslastung je Netz und ein Detail mit dem Plan. Rechte: `network_viewAny`
(lesen), `network_create`, `network_update` (auch für Bereiche),
`network_delete`. Alles unter dem Kunden mit Scoped Bindings: fremdes Netz
oder Bereich eines anderen Netzes → 404.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| GET | `customers/{slug}/networks?standort={site_id}` | – | `{ eintraege: [Netz], summe: {belegt, adressen}, standorte: [{id, name}], darf_anlegen }` |
| POST | `customers/{slug}/networks` | `{site_id, description, network, subnetmask\|cidr, vlanId?, gateway?, dns1?, dns2?, dhcpStart?, dhcpEnd?}` | 201 `NetzDetail` |
| GET | `customers/{slug}/networks/{id}` | – | `NetzDetail` |
| PUT/PATCH | `customers/{slug}/networks/{id}` | nur die geänderten Felder | `NetzDetail` |
| DELETE | `customers/{slug}/networks/{id}` | – | 204 (Papierkorb) |
| POST | `customers/{slug}/networks/{id}/ip-ranges` | `{from_ip, to_ip, label, note?}` | 201 `NetzDetail` |
| DELETE | `customers/{slug}/networks/{id}/ip-ranges/{range_id}` | – | 204 |

`Netz`: `id, name` („Server · VLAN 10“), `bezeichnung, vlan` (Int|null),
`netz, subnetzmaske, cidr` (Int|null), `gateway, standort {id, name}|null`,
`auslastung {belegt, dhcp, reserviert, frei, gesamt}|null`, `fehler`
(Text, wenn Netz/Maske nicht auswertbar – dann `auslastung: null`).

`NetzDetail` = `Netz` plus:
- `dns1, dns2, dhcp_start, dhcp_ende, naechste_freie, kunde, darf_bearbeiten, darf_loeschen`
- `bereiche: [{id, von, bis, label, notiz, anzahl, farbe}]` – sortiert nach Anfangsadresse
- `zeilen: [{art, von, bis, label, gateway, reservierung, farbe, geraete}]` – der Plan
  von oben nach unten. `art`: `geraet` (eine belegte Adresse), `frei`, `dhcp`,
  `reserviert` (zusammengefasste Läufe). `reservierung`: an einer belegten Adresse
  der Name des Bereichs, in dem sie liegt.
- `geraete: [{name, typ, id, im_pool}]` – `typ`/`id` nur, wenn der Nutzer die Liste
  des Typs sehen darf (sonst `null`, dann nur Text). `im_pool: true` = feste Adresse
  mitten im DHCP-Bereich (möglicher Konflikt; steht an der DHCP-Zeile).
- `gekuerzt` – über 8192 Hosts endet der Plan dort.
- `dhcp_ohne_bereich: [Gerät]` – per DHCP versorgte Geräte eines Netzes ohne
  DHCP-Bereich.

`farbe` ist ein Farbname aus der Tailwind-Palette („amber“, „sky“, …) oder `null`;
gleiche Farbe = gleicher Bereich in Liste und Plan.

Regeln:
- Maske **oder** CIDR genügt, das andere wird ergänzt. Bei PATCH nur eines von
  beiden geschickt → das andere wird daraus neu berechnet.
- `site_id` muss ein Standort des Kunden sein → sonst 422 `site_id`.
- `""` leert ein optionales Feld (z. B. `gateway`).
- Bereich: beide Adressen im Netz (sonst 422 am betroffenen Feld), Ende nicht vor
  Anfang (422 `to_ip`), keine Überschneidung mit einem anderen Bereich (422 `from_ip`).

## Objekte anlegen / bearbeiten / löschen

Für jeden Typ aus `config/forms.php`. Die App baut ihr Formular aus der
Beschreibung des Servers – ein neues Feld braucht kein App-Release.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| GET | `customers/{slug}/forms/{typ}` | – | `Formular` (leer, mit Vorgaben) |
| POST | `customers/{slug}/forms/{typ}/generate` | Formularwerte | erzeugte Werte (z. B. SSH-Schlüsselpaar), nichts gespeichert |
| POST | `customers/{slug}/{typ}` | Formularwerte (+ `datei` multipart) | 201 `ObjektDetail` |
| GET | `objects/{typ}/{id}/form` | – | `Formular` + `{id, darf_loeschen}` |
| PUT/PATCH/POST | `objects/{typ}/{id}` | **nur geänderte** Felder (+ `datei` → POST multipart) | `ObjektDetail` |
| DELETE | `objects/{typ}/{id}` | – | 204 |

```
Formular = {
  typ, einzahl, kunde: Kunde,
  felder: [FormFeld],
  werte: { feldname: string | bool | [string] },   // gemischt! eigener JSON-Werttyp in Swift
  geheim_gesetzt: { feldname: bool },
  hat_datei: bool, hat_erzeuger: bool
}

FormFeld = {
  name, label, gruppe,
  art: "text"|"mehrzeilig"|"date"|"number"|"email"|"schalter"|"einheit"|"optionen"
      |"auswahl"|"standort"|"host"|"verknuepfung"|"dienste"|"datei",   // unbekannt → Textfeld
  pflicht: bool, geheim: bool, mehrfach: bool,       // immer da (Swift trotzdem tolerant: fehlt → false)
  einheit?, zeilen?, platzhalter?,
  sichtbar_wenn?: { anderes_feld: "wert" | {enthaelt: "…"} | {leer: true} },   // alle Bedingungen zusammen
  optionen?: [{ wert: string, label }]
}
```

Regeln:
- **Werte:** `schalter` → `bool`; `verknuepfung` und mehrfache `host` → `[string]`
  (Ids bzw. `"server:5"`); alles andere → `string`, leer = `""`.
- **Update:** Nur gesendete Felder werden geschrieben, der Rest mit gespeichertem
  Wert validiert. Feld leeren = `""` senden, nicht weglassen – der Server
  speichert `""` als `null`. Ausnahmen:
  - Pflichtfelder: `""` → 422 auf dem Feld.
  - Spalten ohne `null` (z. B. `height_units`): `""` wird verworfen, der
    gespeicherte Wert bleibt (beim Anlegen greift der Standard der Datenbank).
  - `verknuepfung` und mehrfache `host` leert man mit `[]`, einen einzelnen
    `host` mit `""`, einen `schalter` mit `false`.
- **422 auf ungesendeten Feldern** ist möglich: Weil der Rest mit dem
  gespeicherten Wert geprüft wird, meldet ein Altbestand, der heutige Regeln
  verletzt, seinen Fehler auch dann, wenn die App das Feld nicht angefasst hat.
  Die App sollte die Meldung dem Feld zuordnen, nicht nur der Eingabe.
- **Multipart** (nur mit `datei`): Schalter als `"1"`/`"0"`, Listen als
  `feld[]=…`; `Accept: application/json` mitschicken, sonst kommt bei 422 eine
  Weiterleitung statt JSON.
- **Geheimnisse:** kommen in `werte` als `null`; nur senden, wenn der Nutzer
  einen neuen Wert eingibt – sonst bleibt das gespeicherte unverändert.
- **422** beim `generate` (z. B. fehlender Algorithmus) kommt auf `key_type`.

## Änderungsprotokoll

| Datum | Änderung | Seite |
|---|---|---|
| 2026-10-20 | Vertrag angelegt; Stand: Login, Lesen, Racks, Formular-API (uncommitted) | beide |
| 2026-10-20 | Erledigt (iOS 9ec0ff6): `einbauort`, `support_ende`, `verweise`, `liste`, `fingerprint` übernommen | iOS |
| 2026-10-20 | Erledigt (iOS 9ec0ff6): Rack-PUT schickt `location`/`note` explizit als `null` | iOS |
| 2026-10-20 | Formular-API gegengelesen: `pflicht`/`geheim`/`mehrfach` kommen immer (auch `false`); Ausnahmen beim Leeren, 422 auf ungesendeten Feldern, Multipart ergänzt | Backend |
| 2026-10-20 | Dateien (`datei`) beim Lesen als bewusste Lücke vermerkt | beide |
| 2026-10-20 | Formular-API (`forms/{typ}`, `POST customers/{slug}/{typ}`, `objects/{typ}/{id}` PUT/PATCH/POST/DELETE, `…/form`, `…/generate`) committed und auf Produktion | Backend |
| 2026-10-20 | Liste `customers/{slug}/{typ}`: neues Feld `darf_anlegen` (additiv) | Backend |
| 2026-10-20 | Neu: Patchfelder (`customers/{slug}/patchpanels`, inkl. Ports), Zugänge/IP-Adressen am Gerät, Scan-Ziele am Scanner | Backend |
| 2026-10-20 | `ObjektDetail`: `ip_adressen[]` + `id`, `dhcp`; neues `scan_ziele` (additiv). `darf_anlegen` jetzt auch für `patchpanel` | Backend |
| 2026-10-20 | `POST customers/{slug}/{typ}` nimmt nur noch Formular-Typen an (unbekannter Typ weiterhin 404) | Backend |
| 2026-10-22 | Neu: IPAM – VLANs (`customers/{slug}/networks`, anlegen/ändern/löschen), IP-Plan je VLAN, reservierte Bereiche (`…/ip-ranges`) | Backend |
| 2026-10-22 | Feld `dienste`: neues `kacheln` mit `farbe`/`schrift` je Dienst (additiv, neben `liste`) | Backend |
