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
| GET | `customers/{slug}/{typ}` | `{typ, titel, eintraege: [ObjektKurz], weitere: bool}` |
| GET | `search?q=` | `[{typ, titel, weitere: int, treffer: [ObjektKurz]}]` |
| GET | `objects/{typ}/{id}` | `ObjektDetail` |
| POST | `objects/{typ}/{id}/secret/{feld}` | `{wert}` / 404 „Kein Kennwort hinterlegt.“ |
| POST | `credentials/{link_id}/password` | `{wert}` / 404 |

```
ObjektKurz   = { typ, id, name, untertitel|null, kunde: Kunde|null }

ObjektDetail = ObjektKurz + {
  einzahl,
  gruppen:      [{ titel, felder: [Feld] }],
  ip_adressen:  [{ adresse, bezeichnung|null, netz|null }],
  zugangsdaten: [Zugang],
  einbauort:    { text, rack_id, kunde: Kunde } | null,
  support_ende: { betriebssystem, datum, zustand: "abgelaufen"|"bald"|"ok" } | null
}

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
