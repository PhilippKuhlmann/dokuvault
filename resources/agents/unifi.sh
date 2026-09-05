#!/usr/bin/env bash
#
# Auto-Dokumentation fuer UniFi  ->  DokuVault
#
# Auf einem Mac oder Linux-Rechner ausfuehren, der den UniFi-Controller
# erreicht:
#   bash unifi-doku.sh --controller https://unifi.local --user doku --site "Kunde A"
#
# --site nimmt den internen Namen oder den Anzeigenamen der Site. Welche es
# gibt, zeigt:
#   bash unifi-doku.sh --controller https://unifi.local --user doku --sites
#
# Bei mehreren Sites ist --site Pflicht. Ein Token gehoert zu genau einem
# Kunden - griffe das Script von sich aus die falsche Site ab, landeten dessen
# Geraete in der Dokumentation eines anderen.
#
# Das Kennwort kommt aus der Umgebungsvariable UNIFI_PASSWORD oder wird
# abgefragt. Es als --password mitzugeben ist moeglich, aber es steht dann in
# der Prozessliste und in der Shell-Historie.
#
# Meldet Switches, Accesspoints und WLANs. Rein lesend - es wird nichts am
# Controller veraendert. Ein Konto mit reinen Leserechten genuegt.
#
# Die Zugangsdaten des Controllers bleiben hier und werden NICHT in DokuVault
# gespeichert.
#
# Die WLAN-Passphrase dagegen schon: sie steht im Klartext in der
# Controller-Konfiguration, DokuVault hat dafuer eine verschluesselte Spalte,
# und in einer Dokumentation ist genau sie das, was man nachschlaegt. Wer das
# nicht will, gibt --ohne-kennwoerter mit.
#
set -euo pipefail

API_URL="${DOKU_API_URL:-__API_URL__}"
TOKEN="__AGENT_TOKEN__"

CONTROLLER=""
BENUTZER=""
KENNWORT="${UNIFI_PASSWORD:-}"
SITE=""
NUR_SITES=""
OHNE_KENNWOERTER=""
UNSICHER=""

hilfe() {
  sed -n '2,28p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --controller) CONTROLLER="$2"; shift 2 ;;
    --user|--benutzer) BENUTZER="$2"; shift 2 ;;
    --password|--kennwort) KENNWORT="$2"; shift 2 ;;
    --site|--standort) SITE="$2"; shift 2 ;;
    --sites|--standorte) NUR_SITES="1"; shift ;;
    --ohne-kennwoerter|--ohne-passwoerter) OHNE_KENNWOERTER="1"; shift ;;
    --api-url) API_URL="$2"; shift 2 ;;
    # Ein UniFi-Controller bringt ab Werk ein selbst signiertes Zertifikat mit.
    --unsicher|--zertifikat-ignorieren|-k) UNSICHER="-k"; shift ;;
    -h|--help|--hilfe) hilfe 0 ;;
    *) echo "Unbekannte Option: $1" >&2; hilfe 1 ;;
  esac
done

command -v jq >/dev/null 2>&1 || {
  echo "Fehler: jq wird gebraucht (macOS: brew install jq, Debian/Ubuntu: apt install jq)." >&2
  exit 1
}

[ -n "$CONTROLLER" ] || { echo "Fehler: --controller fehlt." >&2; hilfe 1; }
[ -n "$BENUTZER" ] || { echo "Fehler: --user fehlt." >&2; hilfe 1; }

if [ -z "$KENNWORT" ]; then
  read -rsp "Kennwort fuer $BENUTZER am Controller: " KENNWORT
  echo
fi

CONTROLLER="${CONTROLLER%/}"

# Der Keksbehaelter traegt die Sitzung ueber die folgenden Abfragen. Er liegt
# nur so lange, wie das Script laeuft.
KEKSE="$(mktemp)"
KOPFZEILEN="$(mktemp)"
trap 'rm -f "$KEKSE" "$KOPFZEILEN"' EXIT

anmeldung="$(jq -n --arg u "$BENUTZER" --arg p "$KENNWORT" '{username: $u, password: $p}')"

CSRF=""

# Zwei Bauarten von Controller, zwei Anmeldewege:
# - UniFi OS (UDM, UDM-Pro, Cloud Key Gen2+): /api/auth/login, die
#   Netzwerk-Endpunkte liegen danach unter /proxy/network.
# - Klassischer Controller (Software auf Linux/Windows): /api/login, die
#   Endpunkte liegen direkt unter der Wurzel.
# Erst UniFi OS versuchen, sonst klassisch - so laeuft dasselbe Script auf
# beiden, ohne dass man die Bauart kennen muss.
if curl -fsS $UNSICHER -c "$KEKSE" -D "$KOPFZEILEN" -X POST "$CONTROLLER/api/auth/login" \
     -H "Content-Type: application/json" -d "$anmeldung" >/dev/null 2>&1; then
  BASIS="$CONTROLLER/proxy/network"

  # UniFi OS laesst den Sitzungskeks allein nicht genuegen: jede Anfrage unter
  # /proxy/network braucht zusaetzlich den CSRF-Token. Ohne ihn antwortet der
  # Controller mit 403, obwohl die Anmeldung geklappt hat.
  #
  # Er steht in der Kopfzeile der Anmeldeantwort. Aeltere Firmware schickt ihn
  # dort nicht mit - dann steckt er in der Nutzlast des TOKEN-Kekses, der ein
  # JWT ist. tolower(): die Schreibweise der Kopfzeile wechselt je nach Version.
  CSRF="$(tr -d '\r' < "$KOPFZEILEN" | awk 'tolower($1) == "x-csrf-token:" {print $2}' | tail -n1)"

  if [ -z "$CSRF" ]; then
    jwt="$(awk '$6 == "TOKEN" {print $7}' "$KEKSE" | tail -n1)"
    if [ -n "${jwt:-}" ]; then
      CSRF="$(printf '%s' "$jwt" | cut -d. -f2 | tr '_-' '/+' \
        | jq -Rr '@base64d' 2>/dev/null | jq -r '.csrfToken // empty' 2>/dev/null || true)"
    fi
  fi

  echo "Angemeldet (UniFi OS)."
else
  curl -fsS $UNSICHER -c "$KEKSE" -X POST "$CONTROLLER/api/login" \
    -H "Content-Type: application/json" -d "$anmeldung" >/dev/null
  BASIS="$CONTROLLER"
  echo "Angemeldet (klassischer Controller)."
fi

# Nicht "curl -f": das verschluckt die Antwort und laesst nur "error: 403"
# uebrig. Der Controller schreibt aber hinein, was ihm fehlt - und ohne das
# raet man.
hole() {
  local pfad="$1" roh code antwort
  roh="$(curl -sS $UNSICHER -b "$KEKSE" -w $'\n%{http_code}' \
    ${CSRF:+-H "X-Csrf-Token: $CSRF"} "$BASIS$pfad")"
  code="$(printf '%s' "$roh" | tail -n1)"
  antwort="$(printf '%s' "$roh" | sed '$d')"

  if [ "$code" != "200" ]; then
    {
      echo "Fehler: $BASIS$pfad antwortete mit HTTP $code."
      [ -n "$antwort" ] && echo "  Antwort: $(printf '%s' "$antwort" | head -c 400)"
      case "$code" in
        401) echo "  401: die Sitzung gilt nicht (mehr). Benutzername und Kennwort pruefen." ;;
        403) echo "  403 bei UniFi OS heisst meist eines von beiden:"
             echo "      - Das Konto ist ein Ubiquiti-Cloud-Konto. Die Schnittstelle braucht"
             echo "        einen lokalen Administrator (UniFi OS: Einstellungen -> Admins,"
             echo "        Zugriff 'Nur lokal')."
             echo "      - Dem Konto fehlen die Leserechte fuer diese Site." ;;
        404) case "$pfad" in
               */api/s/*) echo "  404: die Site '$SITE' gibt es nicht. Mit --sites die vorhandenen anzeigen." ;;
               *) echo "  404: diesen Endpunkt gibt es nicht. Zeigt die Controller-URL wirklich auf UniFi?" ;;
             esac ;;
      esac
    } >&2
    exit 1
  fi

  printf '%s' "$antwort"
}

# Welche Sites das Konto sehen darf. 'name' ist der interne Schluessel, der in
# der URL steht (oft eine Zufallsfolge wie 'a1b2c3d4'), 'desc' der Anzeigename
# aus der Oberflaeche. Waehlen soll man mit dem, was man vor sich sieht -
# deshalb trifft --site beides.
SITES="$(hole "/api/self/sites")"

sites_zeigen() {
  jq -r '.data[] | "  \(.name)   \(.desc // "")"' <<<"$SITES"
}

if [ -n "$NUR_SITES" ]; then
  echo "Sites auf diesem Controller:"
  sites_zeigen
  exit 0
fi

ANZAHL="$(jq '.data | length' <<<"$SITES")"

if [ -n "$SITE" ]; then
  TREFFER="$(jq -r --arg s "$SITE" '
    [ .data[] | select((.name | ascii_downcase) == ($s | ascii_downcase)
                    or (((.desc // "") | ascii_downcase) == ($s | ascii_downcase))) ]
    | .[0].name // empty' <<<"$SITES")"

  if [ -z "$TREFFER" ]; then
    {
      echo "Fehler: keine Site '$SITE' auf diesem Controller."
      echo "Vorhanden sind:"
      sites_zeigen
    } >&2
    exit 1
  fi
  SITE="$TREFFER"
elif [ "$ANZAHL" = "1" ]; then
  SITE="$(jq -r '.data[0].name' <<<"$SITES")"
else
  # Nicht raten: auf einem Controller mit mehreren Sites steckt oft je Kunde
  # eine. Der Agent-Token gehoert zu genau einem Kunden - die falsche Site
  # abzugreifen hiesse, fremde Geraete in dessen Dokumentation zu schreiben.
  {
    echo "Fehler: dieser Controller hat $ANZAHL Sites. Bitte mit --site waehlen:"
    sites_zeigen
  } >&2
  exit 1
fi

echo "Site: $SITE ($(jq -r --arg n "$SITE" '.data[] | select(.name == $n) | .desc // "ohne Namen"' <<<"$SITES"))"

GERAETE="$(hole "/api/s/$SITE/stat/device")"
WLANCONF="$(hole "/api/s/$SITE/rest/wlanconf")"

# Die MAC-Adresse als Kennung: sie bleibt, auch wenn das Geraet umbenannt oder
# in einen anderen Standort umgehaengt wird. Ein frisch adoptiertes Geraet hat
# noch keinen Namen - dann steht die MAC da, damit der Eintrag ueberhaupt eine
# Beschriftung bekommt.
MIT_KENNWORT="true"
[ -n "$OHNE_KENNWOERTER" ] && MIT_KENNWORT="false"

PAYLOAD="$(jq -n --arg site "$SITE" --argjson g "$GERAETE" --argjson w "$WLANCONF" \
  --argjson mitkennwort "$MIT_KENNWORT" '
  def geraet: {
    identifier:   .mac,
    name:         (if (.name // "") == "" then .mac else .name end),
    manufacturer: "Ubiquiti",
    model:        .model,
    serial:       .serial,
    ip:           .ip
  };
  def verschluesselung:
    if .security == "open" then "Offen"
    elif .security == "wpaeap" then "WPA2-Enterprise"
    elif .security == "wpapsk" then
      (if (.wpa3_support == true or .wpa_mode == "wpa3") then "WPA3-PSK"
       elif .wpa_mode == "wpa2" then "WPA2-PSK"
       else "WPA-PSK" end)
    else .security end;
  {
    site:         $site,
    switches:     [ $g.data[] | select(.type == "usw") | geraet ],
    accesspoints: [ $g.data[] | select(.type == "uap") | geraet ],
    wifis:        [ $w.data[]
                    # x_passphrase gibt es nur bei WPA-PSK. Ein
                    # Enterprise-WLAN hat keine - dann faellt das Feld weg,
                    # statt leer uebertragen zu werden.
                    | {identifier: ._id, ssid: .name, encryption: verschluesselung}
                      + (if $mitkennwort and ((.x_passphrase // "") != "")
                         then {password: .x_passphrase} else {} end) ]
  }')"

echo "Sende Dokumentation an $API_URL ..."
antwort="$(curl -sS -X POST "$API_URL" -w $'\n%{http_code}' \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$PAYLOAD")"
code="$(printf '%s' "$antwort" | tail -n1)"

if [ "$code" != "200" ]; then
  {
    echo "Fehler: $API_URL antwortete mit HTTP $code."
    echo "  Antwort: $(printf '%s' "$antwort" | sed '$d' | head -c 400)"
    case "$code" in
      401) echo "  401: den Token kennt DokuVault nicht (mehr). Auf der Seite"
           echo "      Auto-Dokumentation einen neuen erzeugen und das Script neu laden -"
           echo "      der Token steckt darin." ;;
      422) echo "  422: DokuVault hat die Daten abgelehnt. Die Antwort oben nennt das Feld." ;;
    esac
  } >&2
  exit 1
fi

printf '%s\n' "$(printf '%s' "$antwort" | sed '$d')"
echo "Fertig. $(jq -r '"\(.switches|length) Switches, \(.accesspoints|length) Accesspoints, \(.wifis|length) WLANs"' <<<"$PAYLOAD") gemeldet."
