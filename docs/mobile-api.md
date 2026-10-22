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
Nutzer = { name, username, kunde: Kunde|null, rechte: { kennwoerter: bool, schluessel: bool } }
Kunde  = { slug, name }
```

`vorgang` ist verschlüsselt, zeitlich begrenzt und gibt genau **ein** Token.

## Lesen

| Methode | Pfad | Antwort |
|---|---|---|
| GET | `customers?suche=` | `[Kunde]` (max. 500; Kundennutzer: nur der eigene) |
| GET | `customers/{slug}` | `{slug, name, typen: [{typ, titel, anzahl}]}` (nur Typen mit `anzahl > 0`) |
| GET | `customers/{slug}/{typ}` | `{typ, titel, eintraege: [ObjektKurz], weitere: bool, darf_anlegen: bool}` |
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
}

Zugang = { id (= link_id), name, benutzer|null, art: "kennwort"|"schluessel",
           notiz|null, hat_kennwort: bool, fingerprint: string|null }
```

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
