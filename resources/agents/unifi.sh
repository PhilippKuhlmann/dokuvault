#!/usr/bin/env bash
#
# Auto-Dokumentation fuer UniFi  ->  DokuVault
#
# Auf einem Mac oder Linux-Rechner ausfuehren, der den UniFi-Controller
# erreicht:
#   bash unifi-doku.sh --controller https://unifi.local --user doku
#
# Das Kennwort kommt aus der Umgebungsvariable UNIFI_PASSWORD oder wird
# abgefragt. Es als --password mitzugeben ist moeglich, aber es steht dann in
# der Prozessliste und in der Shell-Historie.
#
# Meldet Switches, Accesspoints und WLANs. Rein lesend - es wird nichts am
# Controller veraendert. Ein Konto mit reinen Leserechten genuegt.
#
# Die Zugangsdaten des Controllers bleiben hier und werden NICHT in DokuVault
# gespeichert. Das WLAN-Kennwort wird bewusst nicht ausgelesen - wie beim
# AD-Agenten bleiben Kennwoerter manuell gepflegt.
#
set -euo pipefail

API_URL="${DOKU_API_URL:-__API_URL__}"
TOKEN="__AGENT_TOKEN__"

CONTROLLER=""
BENUTZER=""
KENNWORT="${UNIFI_PASSWORD:-}"
SITE="default"
UNSICHER=""

hilfe() {
  sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --controller) CONTROLLER="$2"; shift 2 ;;
    --user|--benutzer) BENUTZER="$2"; shift 2 ;;
    --password|--kennwort) KENNWORT="$2"; shift 2 ;;
    --site|--standort) SITE="$2"; shift 2 ;;
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
trap 'rm -f "$KEKSE"' EXIT

anmeldung="$(jq -n --arg u "$BENUTZER" --arg p "$KENNWORT" '{username: $u, password: $p}')"

# Zwei Bauarten von Controller, zwei Anmeldewege:
# - UniFi OS (UDM, UDM-Pro, Cloud Key Gen2+): /api/auth/login, die
#   Netzwerk-Endpunkte liegen danach unter /proxy/network.
# - Klassischer Controller (Software auf Linux/Windows): /api/login, die
#   Endpunkte liegen direkt unter der Wurzel.
# Erst UniFi OS versuchen, sonst klassisch - so laeuft dasselbe Script auf
# beiden, ohne dass man die Bauart kennen muss.
if curl -fsS $UNSICHER -c "$KEKSE" -X POST "$CONTROLLER/api/auth/login" \
     -H "Content-Type: application/json" -d "$anmeldung" >/dev/null 2>&1; then
  BASIS="$CONTROLLER/proxy/network"
  echo "Angemeldet (UniFi OS)."
else
  curl -fsS $UNSICHER -c "$KEKSE" -X POST "$CONTROLLER/api/login" \
    -H "Content-Type: application/json" -d "$anmeldung" >/dev/null
  BASIS="$CONTROLLER"
  echo "Angemeldet (klassischer Controller)."
fi

hole() { curl -fsS $UNSICHER -b "$KEKSE" "$BASIS$1"; }

GERAETE="$(hole "/api/s/$SITE/stat/device")"
WLANCONF="$(hole "/api/s/$SITE/rest/wlanconf")"

# Die MAC-Adresse als Kennung: sie bleibt, auch wenn das Geraet umbenannt oder
# in einen anderen Standort umgehaengt wird. Ein frisch adoptiertes Geraet hat
# noch keinen Namen - dann steht die MAC da, damit der Eintrag ueberhaupt eine
# Beschriftung bekommt.
PAYLOAD="$(jq -n --arg site "$SITE" --argjson g "$GERAETE" --argjson w "$WLANCONF" '
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
    wifis:        [ $w.data[] | {identifier: ._id, ssid: .name, encryption: verschluesselung} ]
  }')"

echo "Sende Dokumentation an $API_URL ..."
curl -fsS -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$PAYLOAD"
echo ""
echo "Fertig. $(jq -r '"\(.switches|length) Switches, \(.accesspoints|length) Accesspoints, \(.wifis|length) WLANs"' <<<"$PAYLOAD") gemeldet."
