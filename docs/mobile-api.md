# Mobile-API (`/api/mobile`) – Vertrag zwischen WebApp und iOS-App

Die eine Quelle der Wahrheit für die Schnittstelle zwischen
`dokumentation-os` (Laravel) und `dokuvault-ios` (SwiftUI).

**Regel:** Wer an `/api/mobile` etwas ändert (Route, Feld, Fehlerfall), trägt es
hier ein – im selben Commit. Neue Felder sind erlaubt (die App ignoriert
Unbekanntes), Umbenennen oder Entfernen nur nach Absprache, weil ältere
App-Versionen sonst „Die Antwort des Servers ist unlesbar“ zeigen.

Quellen: `routes/api.php` (Block `prefix('mobile')`),
`app/Http/Controllers/API/Mobile/*`, `app/Support/MobileObjects.php`,
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

### Benennung

- **Antworten** verwenden englische Schlüssel in `snake_case`
  (`height`, `location`, `mounts`, `may_update`).
- **Anfragen** an Rack und Formulare verwenden die **Spaltennamen** der Datenbank
  (`site_id`, `height_units`, `location`, `note`) bzw. die Feldnamen aus
  `config/forms.php`. Ausnahme: Einbauten (`side`, `position`, `height`, `type`,
  `id`, `catalog_id`).
- Zwei Stellen bleiben deutsch, weil sie nicht der App gehören: die **Feldarten
  aus `config/forms.php`** (`mehrzeilig`, `auswahl`, `optionen`, `schalter`,
  `einheit`, `standort`, `verknuepfung`, `datei`) – sie steuern auch das Formular
  im Browser – und die Schlüssel **innerhalb** der verschlüsselten `challenge`,
  die niemand außer dem Server liest.

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
| 422 | Validierung | `{message, errors: {field: [..]}}` | `.pruefung(erste Feldmeldung)` |
| 429 | Bremse (Login, Kennwortabruf) | `{message}` | `.gebremst(message)` |
| 5xx | Serverfehler | – | `.server(status)` |

## Anmeldung

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| POST | `login` | `{username, password, device}` | `Token` oder `{two_factor: true, challenge}` |
| POST | `login/two-factor` | `{challenge, code}` | `Token` |
| POST | `logout` | – | 204 (nur dieses Token) |
| GET | `me` | – | `Nutzer` |

```
Token  = { token, expires_at (ISO 8601), user: Nutzer }
Nutzer = { name, username, customer: Kunde|null,
           rights: { passwords: bool, ssh_keys: bool, trash: bool,
                     checklists: bool, agents: bool, pdf: bool,
                     wizard: bool } }
Kunde  = { slug, name }
```

`challenge` ist verschlüsselt, zeitlich begrenzt und gibt genau **ein** Token.

## Lesen

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers?search=` | `[Kunde]` (max. 500; Kundennutzer: nur der eigene) |
| GET | `customers/{slug}` | `{slug, name, types: [{type, title, count}]}` (nur Typen mit `count > 0`) |
| GET | `customers/{slug}/dashboard` | `Dashboard` (siehe unten) |
| GET | `customers/{slug}/trash` | `Papierkorb` (siehe unten) |
| GET | `customers/{slug}/network-map?site=` | `Netzplan` (siehe unten) |
| GET | `customers/{slug}/checklists` | `{open: [...], completed: [...], may_check, may_approve}` |
| GET | `customers/{slug}/agents?search=` | `{agents: [...], more, token: [...], intervals: [...]}` |
| GET | `customers/{slug}/pdf` | `{job: PdfAuftrag\|null}` |
| GET | `customers/{slug}/wizard` | `Erstaufnahme` (siehe unten) |
| GET | `profile` | `Profil` (siehe unten) |
| GET | `changelog` | `{version, text, truncated}` (roher Markdown) |
| GET | `customers/{slug}/{type}?site=` | `{type, title, entries: [ObjektKurz], more: bool, may_create: bool, sites: [{id, name}]\|null}` |
| GET | `search?q=` | `[{type, title, more: int, hits: [ObjektKurz]}]` |
| GET | `objects/{type}/{id}` | `ObjektDetail` |
| POST | `objects/{type}/{id}/secret/{field}` | `{value}` / 404 „Kein Kennwort hinterlegt.“ |
| POST | `credentials/{link_id}/password` | `{value}` / 404 |

```
ObjektKurz   = { type, id, name, subtitle|null, customer: Kunde|null }

ObjektDetail = ObjektKurz + {
  singular,
  groups:      [{ title, fields: [Feld] }],
  ip_addresses:  [{ id, dhcp: bool, address, label|null, network|null }],
  credentials: [Zugang],
  mounting:    { text, rack_id, customer: Kunde } | null,
  end_of_support: { operating_system, date, state: "expired"|"soon"|"ok" } | null,
  scan_targets:   [ScanZiel]                                 // nur beim Scanner gefüllt, sonst []
}

ScanZiel = { id, name|null, kind: "smb"|"email"|"ftp"|"cloud"|"other", kind_label,
             target, login: { id, name } | null }

Feld = {
  name, label,
  kind: "text"|"multiline"|"date"|"link"|"ip"|"secret",   // unbekannte Art → als "text" zeigen
  value: string|null,                                       // bei "secret" immer null
  links?: [{ type, id, name }],                          // Host-Felder: Sprung zu objects/{type}/{id}
  list?:    [string]                                      // Verknüpfungen, Dienste: aufklappbare Liste
  tiles?:  [{name, color: "#rrggbb"|null, text_color: "#rrggbb"|null}]  // nur Dienste: Farbe aus dem Dienste-Katalog, Schrift vom Server berechnet (Helligkeit > 0,6 → #111827, sonst #ffffff); ohne Katalogfarbe beide null = grau
}

Zugang = { id (= link_id), name, user|null, kind: "password"|"ssh_key",
           note|null, has_password: bool, fingerprint: string|null }
```

`?site=<id>` engt die Liste auf einen Standort ein – derselbe Filter, den
die Seitenleiste im Browser in der Sitzung hält. `sites` ist die Auswahl
dafür und **null** bei einer Art, die an keinem Standort hängt; dann gibt es
nichts zu filtern. Ein Standort eines anderen Kunden zählt als kein Filter,
nicht als Fehler.

Die Werte in `rights` sagen der App, welche festen Wege sie überhaupt
anbietet (Papierkorb, Checklisten, Auto-Dokumentation, PDF-Export,
Erstaufnahme-Assistent) – ein Weg, der nur in
eine 403 führt, ist keiner. Ein älterer Server schickt sie nicht; dann bleibt
der jeweilige Weg verborgen.

Leere Felder werden **nicht** ausgeliefert.

`may_create` ist `true`, wenn der Nutzer das Recht `{type}_create` hat **und**
es in der App einen Weg zum Anlegen gibt: Formular-Typen (`customers/{slug}/forms/{type}`)
`rack` (`POST customers/{slug}/racks`) und `patchpanel`
(`POST customers/{slug}/patchpanels`). Für Netzwerkdosen (`patchport`) usw.
immer `false` – eine Dose entsteht mit ihrem Patchfeld.

**Bewusste Lücke – Dateien:** Felder der Art `datei` fehlen in `groups`
(`MobileObjects::OHNE`). Solange nicht geklärt ist, wie die App eine geschützte
Datei abruft (eigener Endpunkt mit Recht und Protokoll), zeigt sie keine.
Hochladen geht dagegen schon (`datei` beim Anlegen/Bearbeiten, multipart).

## Dashboard

`GET customers/{slug}/dashboard` liefert, was im Browser auf dem
Kunden-Dashboard steht. Gerechnet wird es in `App\Support\CustomerDashboard` –
dieselbe Klasse bedient die Blade-Seite. Zwei Rechnungen für dieselbe Frist
wären zwei Wahrheiten.

```
Dashboard = {
  slug, name,
  deadlines: {
    licenses:     [Frist],   // Software-Lizenzen, Frist aus Admin -> Fristen (Verträge)
    certificates:  [Frist],   // dieselbe Frist
    warranties:    [Frist],   // Hardware aller Arten, eigene Garantiefrist
    end_of_support: [Frist]    // Geräte (eol_date) und Betriebssysteme, EOL-Frist
  },
  sites:       [ObjektKurz],
  contacts: [ObjektKurz + { role|null, phone|null, email|null }],
  agents:         [{ name, kind, text, severe: bool }] | null,
  changes:     [{ name, kind, event, who|null, when (ISO 8601),
                      type|null, id|null }]
}

Frist = { name, kind, date (ISO 8601), days: int, type|null, id|null }
```

`days` ist das Vorzeichen, an dem gefärbt wird: negativ abgelaufen, `0` heute.
Der Browser nimmt 14 Tage als Schwelle für Gelb, beim Support-Ende 90 – dort
steht Fernes als Monat statt als Tagezahl.

`type` und `id` sind der Weg zum Objekt (`objects/{type}/{id}`). `type` ist
**null**, wenn die App die Art nicht öffnen kann: Die Fristen sammeln über
`config('custom.trashables')`, die App kennt `MobileObjects::typen()` – eine
Checkliste liegt im Papierkorb, hat in der App aber keine Ansicht. Bei
`changes` ist zusätzlich `id` null, wenn `event` `deleted` ist: Das
Objekt liegt im Papierkorb, es gibt nichts zu öffnen.

`agents` ist `null`, wenn der Angemeldete keine Agenten verwaltet
(`agent_manage`) oder der Kunde weder Agent noch Token hat – dann zeigt die
App keine Karte, keine leere. `severe` trennt Rot von Gelb.

Jede Kachel prüft die Rechte einzeln (`{type}_viewAny`): Ohne das Recht kommt
sie leer zurück, nicht gefüllt und versteckt.

## Papierkorb

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/trash` | `{entries: [PapierkorbZeile], truncated: [{title, count}]}` |
| POST | `customers/{slug}/trash/{type}/{id}/restore` | `{object: ObjektKurz|null}` |

```
PapierkorbZeile = { type, title, name, deleted_at (ISO 8601), id,
                    may_restore: bool }
```

Gerechnet wird in `App\Support\Trash`, derselben Klasse, die die Seite
im Browser fuellt.

Sehen braucht `see_hidden` (sonst 403) **und** das Recht auf die Art
(`{type}_viewAny`) – ohne das stuenden hier Namen von Objekten, von denen der
Nutzer nichts wissen soll. Zurueckholen braucht zusaetzlich `{type}_delete`:
Es macht ein Loeschen rueckgaengig, und das darf nicht jeder, der in den
Papierkorb sehen darf. `may_restore` sagt es je Zeile, damit die
App den Knopf gar nicht erst anbietet.

Je Art hoechstens 100 Eintraege, das Neueste zuerst. Was abgeschnitten wurde,
steht in `truncated` – ein stilles Abschneiden liest sich wie „mehr ist nicht
da“. `truncated` ist leer, wenn nichts gekuerzt wurde.

`type` ist ein Schluessel aus `config('custom.trashables')`, nie etwas
Beliebiges: ein anderer Wert ist 404. Ein Objekt eines anderen Kunden hinter
der eigenen Adresse ist ebenfalls 404.

`object` in der Antwort auf das Zurueckholen ist `null`, wenn die App die Art
nicht oeffnen kann (`MobileObjects::typen()`) – zurueckgeholt ist es trotzdem.

## Netzplan

`GET customers/{slug}/network-map` liefert den logischen Netzplan: Internet →
Gateway → VLAN → Geräte, je Standort. Gebaut wird er in
`App\Support\NetworkMap`, derselben Klasse, die die Seite im Browser
zeichnet. Der Browser stellt die Spalten nebeneinander, auf dem Telefon ist
dasselbe ein aufklappbarer Baum – die Daten sind dieselben.

```
Netzplan = { sites: [{
  id, name,
  columns: [{
    gateway: Knoten|null,        // null: das Gateway ist nicht dokumentiert
    uplinks: [Knoten],
    networks:   [{ network: {id, name, address|null, gateway|null},
                groups: [{ title, devices: [Knoten] }] }]
  }],
  standalone:              [Knoten],  // Firewall/Router ohne VLAN und Anschluss
  unlinked_uplinks:  [Knoten],  // Anschluss, der sich keinem Gerät zuordnen ließ
  unassigned:            [Knoten]   // Gerät mit Adresse außerhalb jedes VLANs
}] }

Knoten = { name, kind|null, detail|null, address|null, type|null, id|null }
```

`?site=<id>` filtert auf einen Standort – derselbe Filter, den die
Seitenleiste im Browser in der Sitzung hält. Ein Standort eines anderen
Kunden zählt als kein Filter, nicht als Fehler.

`type` und `id` sind der Weg zum Gerät (`objects/{type}/{id}`); `network.id` führt
ins IPAM (`customers/{slug}/networks/{id}`). `type` ist **null**, wo der
Nutzer die Liste dieser Art nicht sehen darf – dort lässt auch der Browser
den Verweis weg. Der Name steht trotzdem da: Der Plan soll vollständig sein.

Alles stammt aus dokumentierten Daten, keine Verkabelung. Was sich nicht
einordnen ließ, fällt nicht weg, sondern steht in `standalone`,
`unlinked_uplinks` und `unassigned` – so zeigt der Plan auch die Lücken in
der Dokumentation.

Braucht `network_viewAny`, sonst 403.

## Checklisten

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/checklists` | `{open: [Offen], completed: [Kurz], may_check: bool, may_approve: bool}` |
| GET | `customers/{slug}/checklists/{id}` | `Checkliste` |
| POST | `customers/{slug}/checklists/{id}/items/{item}/toggle` | `{done: bool, completed: bool}` / 409 |
| POST | `customers/{slug}/checklists/{id}/items/{item}/approve` | `{approved: true}` |
| GET | `customers/{slug}/checklists/form` | Auswahl für den Antrag |
| GET | `customers/{slug}/checklists/{id}/ad-script` | das PowerShell-Skript (Datei) |
| POST | `customers/{slug}/checklists/onboarding` | `{checklist: Kurz}` |
| POST | `customers/{slug}/checklists/offboarding` | `{checklist: Kurz}` |

```
Kurz  = { id, name, kind: "onboarding"|"offboarding", position|null,
          reference_date (ISO 8601), completed_at (ISO 8601)|null }
Offen = Kurz + { items: int, done: int, awaiting_approval: int }

Checkliste = Kurz + {
  note|null, username|null, email|null,
  sections: [{ title, items: [Punkt] }],
  may_check: bool, may_approve: bool
}

Punkt = { id, title, hint|null, assignee, done: bool,
          done_at (ISO 8601)|null, done_by|null,
          needs_approval: bool, approved: bool, approved_by|null }
```

Drei Rechte wie im Browser: `checklist_manage` (die IT arbeitet die Listen
ab), `checklist_request` (der Kunde meldet einen neuen Mitarbeiter) und
`checklist_approve` (die Leitung gibt heikle Laufwerke frei). Jedes davon
darf die Listen **sehen**; ohne alle drei ist es 403. Abhaken braucht
`checklist_manage`, Freigeben `checklist_approve` – `may_check` und
`may_approve` sagen es, damit die App die Knöpfe gar nicht erst anbietet.

Ein Punkt mit `needs_approval` und ohne `approved` lässt sich **nicht**
abhaken: **409** mit dem Grund im `message`. Das ist kein Fehler der App,
sondern eine Reihenfolge – erst die Leitung, dann die IT.

Ist der letzte Punkt abgehakt, gilt die Liste als erledigt (`completed: true`);
wird ein Haken wieder weggenommen, ist sie wieder offen.

Eine Liste eines anderen Kunden – und ein Punkt einer anderen Liste – ist ein
404, nicht nur verborgen (scoped bindings).

### Beantragen

```
Formular = {
  drives: [{ id, name, letter|null,
                levels: [{ level: "r"|"rw", title }] }],
  apps: [{ id, name, needs_approval: bool }],
  ad_users: [{ id, name, username }],
  may_offboard: bool
}
```

`GET .../form` liefert, was dieser Kunde im Antrag anbietet. Welche `levels`
ein Laufwerk hat, hängt an den hinterlegten AD-Gruppen: ohne Schreibgruppe
gibt es kein „lesen und schreiben".

`POST .../onboarding` nimmt `first_name`, `last_name`, `reference_date`,
`note`, `drives` (Laufwerk-Id → Stufe) und `options` (Programm-Ids).
Benutzername und E-Mail rechnet der Server aus dem Namen – dasselbe Schema
wie die AD-Benutzer der Dokumentation. Melden darf `checklist_request` oder
`checklist_manage`.

`POST .../offboarding` nimmt `ad_user_id`, `reference_date` und `note` und
braucht **`checklist_manage`**: Ein Offboarding anzustoßen ist Sache der IT,
nicht des Melders. Deshalb ist `ad_users` ohne dieses Recht leer. Ein
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
| GET | `customers/{slug}/agents?search=` | `{agents: [Agent], more: bool, token: [Token], intervals: [{minutes, title}]}` |
| PUT | `customers/{slug}/agents/installations/{id}` | `{agent: Agent}` |
| POST | `customers/{slug}/agents/installations/{id}/run` | `{agent: Agent}` |
| POST | `customers/{slug}/agents/tokens/{id}/lock` | `{locked: true}` |
| POST | `customers/{slug}/agents/tokens/{id}/unlock` | `{locked: false}` |

```
Agent = { id, hostname, domain|null, kind, version|null,
          last_seen_at (ISO 8601)|null, silent: bool,
          interval_minutes: int, run_requested: bool, token|null,
          tasks: [{ role, title, enabled: bool, detected: bool,
                    failed: bool, message|null }] }

Token = { id, name, site|null, locked: bool,
          expires_at (ISO 8601)|null, expired: bool }
```

Alles braucht `agent_manage`, auch das Lesen – sonst 403.

`PUT .../installations/{id}` nimmt `roles` (eine Teilmenge der `role`-Werte
aus `tasks`) und `interval_minutes` (einer der `intervals`). Alles andere ist
**422**. Jede Aufgabe der Art lässt sich einschalten, `detected` oder nicht:
Die Erkennung kann danebenliegen, und das Agentenprotokoll sagt dann, woran
es scheiterte. Gültig wird es ab dem nächsten Lauf.

`run` ist „jetzt melden“ – der Agent holt es sich beim nächsten Checkin ab,
innerhalb von fünf Minuten.

Höchstens 200 Installationen; `more` sagt, dass es mehr sind. `?search=`
greift auf Hostname und Domäne, wie der Filter im Browser.

**Nicht in der App:** Token anlegen und erneuern, die Installationsdateien
(.exe, .sh) und das Löschen von Token und Einträgen. Ein Geheimnis, das man
nur einmal sieht, gehört nicht auf ein Telefon, das man danach weglegt.

## PDF-Export

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers/{slug}/pdf` | `{job: PdfAuftrag\|null}` |
| POST | `customers/{slug}/pdf` | `{job: PdfAuftrag}` |
| GET | `customers/{slug}/pdf/{id}` | die Datei (application/pdf) |

```
PdfAuftrag = { id, status: "open"|"running"|"completed"|"error",
               running: bool, stalled: bool, ready: bool,
               size|null, created_at (ISO 8601),
               finished_at (ISO 8601)|null, message|null }
```

Braucht `create_pdf`, sonst 403.

Bestellt, nicht sofort gerendert: `POST` legt einen Auftrag an, den der
Scheduler abarbeitet; die App fragt mit `GET` den Stand ab. 40 Server, 90 VMs
und 160 Computer brauchen 370 MB und 15 Sekunden – das überlebt kein Request.
Solange einer läuft, gibt `POST` denselben Auftrag zurück, statt einen
zweiten anzulegen.

`stalled` heißt: Niemand arbeitet die Warteschlange ab. Dann hat weiteres
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
| PUT | `profile/password` | `{message, sign_in_again: true}` |
| POST | `profile/two-factor/begin` | `{secret, uri, needs_mail_code, challenge}` |
| POST | `profile/two-factor/confirm` | `{codes: [...]}` |
| POST | `profile/two-factor/recovery-codes` | `{codes: [...]}` |
| DELETE | `profile/two-factor` | `{two_factor: false}` |
| DELETE | `profile` | `{message, sign_in_again: true}` |
| GET | `changelog` | `{version, text, truncated: bool}` |

```
Profil = { name, username, email|null, locale|null,
           locales: [{code, title}],
           locked: bool, two_factor: bool }
```

`PUT profile` nimmt `name`, `email`, `locale` und `current_password`.
`locale` ist einer der `locales`-Codes oder **leer** – leer heißt „der
Sprache des Geräts folgen“, nicht Leerstring. Eine andere Sprache ist 422.

Eine **neue E-Mail-Adresse braucht `current_password`**: Dorthin gehen die
Kennwort-Zurücksetzungen, und eine geliehene Sitzung wäre damit das Konto.
Die alte Adresse erfährt von der Änderung. Name und Sprache gehen ohne.

`locked` ist der Demo-Zugang: Er ist geteilt, was einer ändert, träfe alle.
Dann sind Profil und Kennwort 422 mit der Begründung.

`PUT profile/password` nimmt `current_password`, `password` und
`password_confirmation`. **Danach ist die App abgemeldet**: `User::booted()`
entzieht bei jedem Kennwortwechsel alle App-Token – an einer Stelle für jeden
Weg (Zurücksetzen, Profil, Admin-Bereich, Einladung). `sign_in_again: true`
sagt es, damit die App nicht erst in den nächsten 401 läuft.

`changelog` liefert rohen Markdown-Text und die Version, wie sie im Browser
unter der Seitenleiste hängt – aber nur die **jüngsten Einträge**: Die Datei
ist über 300 KB groß, und ein Textfeld mit dreihunderttausend Zeichen zeigt
gar nichts mehr. Geschnitten wird an einer Überschrift, nicht mitten im Satz;
`truncated` sagt, dass es weitergeht – vollständig steht es im Browser.

### Zweite Stufe einrichten

Zwei Schritte, weil die Mobil-Schnittstelle keine Sitzung hat, in der das
begonnene Geheimnis liegen könnte:

`POST profile/two-factor/begin` erzeugt es und gibt zurück: `secret` (zum
Abtippen), `uri` (`otpauth://…`, daraus malt die App den QR-Code),
`needs_mail_code` und `challenge` – das Geheimnis **verschlüsselt**, 15
Minuten gültig, an diesen Nutzer gebunden. Dasselbe Verfahren wie bei der
Anmeldung mit zweiter Stufe.

`POST profile/two-factor/confirm` nimmt `challenge`, `code` (aus der
Authenticator-App) und, wo nötig, `mail_code`. Hat der Zugang eine
E-Mail-Adresse, geht beim Beginnen ein sechsstelliger Code dorthin und wird
hier verlangt: Das Kennwort allein ließe jeden, der es kennt, seine eigene
App einrichten und den Inhaber aussperren. Die Antwort enthält die
**Wiederherstellungscodes – einmal und nie wieder**.

`POST profile/two-factor/recovery-codes` und `DELETE profile/two-factor`
brauchen beide `password`. Eine vom Administrator **verlangte** zweite Stufe
lässt sich nicht abschalten (422) – sonst wäre die Pflicht ein Knopf, den man
einmal drückt. Ein Geheimtext von anderswo geht als `challenge` nicht durch
(der Zweck steht mit darin).

### Konto löschen

`DELETE profile` mit `password`. Nicht rückgängig zu machen; die Token dieses
Nutzers fallen mit ihm weg, `sign_in_again: true` sagt es der App. Ein
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
  running: bool, current: <key>|null, completed_at (ISO 8601)|null,
  steps: [{ key, group, title, question, type|null,
            done: bool, skipped: bool, created: int }]
}
```

Der Ablauf kommt aus `App\Support\WizardFlow`, derselben Klasse, die die
Livewire-Komponente im Browser führt.

**Ausgefüllt wird mit den Formularen**, die der Server ohnehin beschreibt:
`type` ist der Schlüssel für `customers/{slug}/forms/{type}` und
`POST customers/{slug}/{type}`. Der Assistent sagt nur, welcher Typ gerade
dran ist. `type` ist **null**, wo die App die Art nicht über ein Formular
anlegt – VLANs etwa haben ihren eigenen Weg (`customers/{slug}/networks`).

`next` nimmt `how`: `done` (Weiter nach dem Eintragen), `skipped`
(Weiter ohne) oder `group` (die ganze Gruppe, „keine Telefonie"). Wurde in
diesem Durchlauf trotzdem etwas angelegt, zählt der Schritt als erfasst – nur
ein wirklich leer gelassener gilt als übersprungen. Alles andere ist 422.

`goto` ohne `step` geht einen zurück; mit `step` direkt dorthin.
Ein Schritt, den der Nutzer nicht anlegen darf, ist für ihn kein Schritt:
**404**, und der Durchlauf bleibt stehen. Springen ist kein Bearbeiten – der
verlassene Schritt wird weder erledigt noch übersprungen.

Es gibt nur **Schritte, für die der Nutzer das `{type}_create`-Recht hat**.
Ohne ein einziges ist der ganze Assistent 403. Hinter dem letzten Schritt ist
der Durchlauf fertig (`current: null`, `completed_at` gesetzt).

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
| POST | `/{rack}/items` | `{side, position?, type+id \| catalog_id}` | 201 `Einbau` |
| PATCH | `/{rack}/items/{item}` | `{position?, height? (1–8)}` | `Einbau` |
| DELETE | `/{rack}/items/{item}` | – | 204 |

```
RackKoerper = { site_id, name, height_units (1–60), location|null, note|null }

Schrank = { id, name, height, location|null, note|null, site: {id,name}|null, customer,
            may_update, may_delete, sides: [{side, title}], mounts: [Einbau] }
Einbau  = { id, name, side, position, height, top_unit, full_depth, appearance|null,
            device: { type|null, id } | null }
Palette = { devices: [{ type, title, entries: [{id, name, height}] }],
            catalog: [{ id, name, height, full_depth, appearance|null }] }
```

`position` ist die unterste belegte HE, `top_unit` rechnet der Server.
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

Patchfeld = { id, name, port_count, height, manufacturer|null, model|null, note|null,
              site: {id,name}|null, customer, mounting: string|null,
              may_update, may_delete,
              ports: [{ id, number, outlet|null, room|null, switch: {id,name}|null,
                        switch_port|null, note|null }],
              switches: [{ id, name }] }        // Auswahl für network_switch_id
```

Ports: Nur gesendete Ports und Spalten werden geschrieben, `""` leert. Eine
Port-Id eines anderen Patchfelds wird übergangen. `network_switch_id` nur
Switches desselben Kunden (sonst 422). „Durchnummerieren“ macht die App selbst
und schickt das Ergebnis.

## Am Gerät: Zugänge, IP-Adressen, Scan-Ziele

Ändert das Gerät, braucht also immer `{type}_update` – auch die GETs, die die
Auswahl liefern. Ein Typ ohne den Block → 404 (z. B. Domain hat keine IP-Adressen,
nur der Scanner hat Scan-Ziele). Die Antwort auf POST ist die neue Liste.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| GET | `objects/{type}/{id}/credentials` | – | `{ entries: [Zugang], available: [{id, name, user\|null, kind}], may_create: bool }` |
| POST | `objects/{type}/{id}/credentials` | `{login_id, note?}` **oder** `{name, username?, password?, note?}` | 201 `[Zugang]` |
| DELETE | `objects/{type}/{id}/credentials/{link_id}` | – | 204 (nur die Verknüpfung; der Zugang bleibt) |
| GET | `objects/{type}/{id}/ip-addresses` | – | `{ entries: [IP], networks: [{id, name, next_free\|null}] }` |
| POST | `objects/{type}/{id}/ip-addresses` | `{address, network_id?, label?}` **oder** `{dhcp: true, network_id, label?}` | 201 `[IP]` |
| DELETE | `objects/{type}/{id}/ip-addresses/{ip_id}` | – | 204 |
| GET | `objects/scanner/{id}/scan-targets` | – | `{ entries: [ScanZiel], available: [ScanZiel], kinds: [{value, label}], logins: [{id, name}], may_create, may_link }` |
| POST | `objects/scanner/{id}/scan-targets` | `{target_id}` **oder** `{kind, target, name?, login_general_id?}` | 201 `[ScanZiel]` |
| DELETE | `objects/scanner/{id}/scan-targets/{target_id}` | – | 204 (nur die Verknüpfung) |

Regeln:
- Zugänge: nur Arten, die der Nutzer sehen darf (Kennwort: `logingeneral_viewAny`,
  Schlüssel: `sshkey_viewAny`; keins von beiden → 403). Neu anlegen braucht
  `logingeneral_create`. Fremder Zugang → 422 `login_id`.
- IP: Eine Adresse gibt es je Kunde nur einmal → 422 `address`. Bei `dhcp: true`
  ist `network_id` Pflicht und `address` verboten. `next_free` ist der
  Vorschlag, den auch der Browser bei Wahl des Netzes einträgt.
- Scan-Ziele: neu anlegen braucht `scantarget_create`, vorhandene verknüpfen
  `scantarget_viewAny` (sonst ist `available` leer).

## IPAM: VLANs, IP-Plan, reservierte Bereiche

Im Browser zwei Seiten (VLAN-Liste und IP-Plan), in der App eine Liste mit
Auslastung je Netz und ein Detail mit dem Plan. Rechte: `network_viewAny`
(lesen), `network_create`, `network_update` (auch für Bereiche),
`network_delete`. Alles unter dem Kunden mit Scoped Bindings: fremdes Netz
oder Bereich eines anderen Netzes → 404.

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| GET | `customers/{slug}/networks?site={site_id}` | – | `{ entries: [Netz], totals: {used, addresses}, sites: [{id, name}], may_create }` |
| POST | `customers/{slug}/networks` | `{site_id, description, network, subnetmask\|cidr, vlanId?, gateway?, dns1?, dns2?, dhcpStart?, dhcpEnd?}` | 201 `NetzDetail` |
| GET | `customers/{slug}/networks/{id}` | – | `NetzDetail` |
| PUT/PATCH | `customers/{slug}/networks/{id}` | nur die geänderten Felder | `NetzDetail` |
| DELETE | `customers/{slug}/networks/{id}` | – | 204 (Papierkorb) |
| POST | `customers/{slug}/networks/{id}/ip-ranges` | `{from_ip, to_ip, label, note?}` | 201 `NetzDetail` |
| DELETE | `customers/{slug}/networks/{id}/ip-ranges/{range_id}` | – | 204 |

`Netz`: `id, name` („Server · VLAN 10“), `description, vlan` (Int|null),
`network, subnet_mask, cidr` (Int|null), `gateway, site {id, name}|null`,
`usage {used, dhcp, reserved, free, total}|null`, `error`
(Text, wenn Netz/Maske nicht auswertbar – dann `usage: null`).

`NetzDetail` = `Netz` plus:
- `dns1, dns2, dhcp_start, dhcp_end, next_free, customer, may_update, may_delete`
- `ranges: [{id, from, to, label, note, count, color}]` – sortiert nach Anfangsadresse
- `rows: [{kind, from, to, label, gateway, reservation, color, devices}]` – der Plan
  von oben nach unten. `kind`: `device` (eine belegte Adresse), `free`, `dhcp`,
  `reserved` (zusammengefasste Läufe). `reservation`: an einer belegten Adresse
  der Name des Bereichs, in dem sie liegt.
- `devices: [{name, type, id, in_pool}]` – `type`/`id` nur, wenn der Nutzer die Liste
  des Typs sehen darf (sonst `null`, dann nur Text). `in_pool: true` = feste Adresse
  mitten im DHCP-Bereich (möglicher Konflikt; steht an der DHCP-Zeile).
- `truncated` – über 8192 Hosts endet der Plan dort.
- `dhcp_outside_range: [Gerät]` – per DHCP versorgte Geräte eines Netzes ohne
  DHCP-Bereich.

`color` ist ein Farbname aus der Tailwind-Palette („amber“, „sky“, …) oder `null`;
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
| GET | `customers/{slug}/forms/{type}` | – | `Formular` (leer, mit Vorgaben) |
| POST | `customers/{slug}/forms/{type}/generate` | Formularwerte | erzeugte Werte (z. B. SSH-Schlüsselpaar), nichts gespeichert |
| POST | `customers/{slug}/{type}` | Formularwerte (+ `datei` multipart) | 201 `ObjektDetail` |
| GET | `objects/{type}/{id}/form` | – | `Formular` + `{id, may_delete}` |
| PUT/PATCH/POST | `objects/{type}/{id}` | **nur geänderte** Felder (+ `datei` → POST multipart) | `ObjektDetail` |
| DELETE | `objects/{type}/{id}` | – | 204 |

```
Formular = {
  type, singular, customer: Kunde,
  fields: [FormFeld],
  values: { feldname: string | bool | [string] },   // gemischt! eigener JSON-Werttyp in Swift
  secret_set: { feldname: bool },
  has_file: bool, has_generator: bool
}

FormFeld = {
  name, label, group,
  kind: "text"|"date"|"number"|"email"|"mehrzeilig"|"schalter"|"einheit"|"optionen"
      |"auswahl"|"standort"|"host"|"verknuepfung"|"dienste"|"datei",   // unbekannt → Textfeld
  required: bool, secret: bool, multiple: bool,       // immer da (Swift trotzdem tolerant: fehlt → false)
  unit?, rows?, placeholder?,
  visible_if?: { anderes_feld: "value" | {enthaelt: "…"} | {leer: true} },   // alle Bedingungen zusammen
  options?: [{ value: string, label }]
}
```

Regeln:
- **Feldarten und Bedingungen bleiben deutsch**: `kind` ist der Typ aus
  `config/forms.php`, `visible_if` sind dessen Bedingungen (`enthaelt`, `leer`).
  Dieselbe Beschreibung baut auch das Formular im Browser.
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
  `field[]=…`; `Accept: application/json` mitschicken, sonst kommt bei 422 eine
  Weiterleitung statt JSON.
- **Geheimnisse:** kommen in `values` als `null`; nur senden, wenn der Nutzer
  einen neuen Wert eingibt – sonst bleibt das gespeicherte unverändert.
- **422** beim `generate` (z. B. fehlender Algorithmus) kommt auf `key_type`.

## Änderungsprotokoll

| Datum | Änderung | Seite |
|---|---|---|
| 2026-10-20 | Vertrag angelegt; Stand: Login, Lesen, Racks, Formular-API (uncommitted) | beide |
| 2026-10-20 | Erledigt (iOS 9ec0ff6): `mounting`, `end_of_support`, `links`, `list`, `fingerprint` übernommen | iOS |
| 2026-10-20 | Erledigt (iOS 9ec0ff6): Rack-PUT schickt `location`/`note` explizit als `null` | iOS |
| 2026-10-20 | Formular-API gegengelesen: `required`/`secret`/`multiple` kommen immer (auch `false`); Ausnahmen beim Leeren, 422 auf ungesendeten Feldern, Multipart ergänzt | Backend |
| 2026-10-20 | Dateien (`datei`) beim Lesen als bewusste Lücke vermerkt | beide |
| 2026-10-20 | Formular-API (`forms/{type}`, `POST customers/{slug}/{type}`, `objects/{type}/{id}` PUT/PATCH/POST/DELETE, `…/form`, `…/generate`) committed und auf Produktion | Backend |
| 2026-10-20 | Liste `customers/{slug}/{type}`: neues Feld `may_create` (additiv) | Backend |
| 2026-10-20 | Neu: Patchfelder (`customers/{slug}/patchpanels`, inkl. Ports), Zugänge/IP-Adressen am Gerät, Scan-Ziele am Scanner | Backend |
| 2026-10-20 | `ObjektDetail`: `ip_addresses[]` + `id`, `dhcp`; neues `scan_targets` (additiv). `may_create` jetzt auch für `patchpanel` | Backend |
| 2026-10-20 | `POST customers/{slug}/{type}` nimmt nur noch Formular-Typen an (unbekannter Typ weiterhin 404) | Backend |
| 2026-10-22 | Neu: IPAM – VLANs (`customers/{slug}/networks`, anlegen/ändern/löschen), IP-Plan je VLAN, reservierte Bereiche (`…/ip-ranges`) | Backend |
| 2026-10-22 | Feld `dienste`: neues `tiles` mit `color`/`text_color` je Dienst (additiv, neben `list`) | Backend |
| 2026-10-31 | Neu: Dashboard, Papierkorb, Netzplan, Checklisten, Auto-Dokumentation, PDF-Export, Profil/Sprache/Changelog, Erstaufnahme-Assistent | Backend |
| 2026-11-01 | **Alle Schlüssel englisch**: Antworten, Anfragen und Enum-Werte von `/api/mobile` tragen jetzt englische `snake_case`-Namen (`typ→type`, `titel→title`, `darf_*→may_*`, `art→kind`, `wert→value`, …). Deutsch bleiben nur die Feldarten aus `config/forms.php` und die Schlüssel in der verschlüsselten `challenge`. **Bricht alte App-Versionen** | beide |
