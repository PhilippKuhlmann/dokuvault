#!/usr/bin/env bash
#
# Auto-Dokumentation fuer Firewalls (OPNsense)  ->  DokuVault
#
# Auf einem Mac oder Linux-Rechner ausfuehren, der die Firewall erreicht:
#   bash firewall-doku.sh --url https://opnsense.local --key "..."
#
# Das Secret kommt aus der Umgebungsvariable FIREWALL_SECRET oder wird
# abgefragt. Es als --secret mitzugeben ist moeglich, aber es steht dann in
# der Prozessliste und in der Shell-Historie.
#
# Key und Secret legt man in der OPNsense unter System -> Zugang -> Benutzer
# an (API-Schluessel). Ein eigener Benutzer genuegt; er braucht nur
# Leserechte auf die abgefragten Seiten (Dashboard, Schnittstellen, VPN,
# Firewall, Gateways).
#
# Meldet Version, Schnittstellen, VPNs (IPsec, OpenVPN, WireGuard),
# Portweiterleitungen und Gateways. Rein lesend - es wird nichts an der
# Firewall veraendert. Was eine aeltere Version nicht anbietet, fehlt einfach.
#
# Key und Secret bleiben hier und werden NICHT in DokuVault gespeichert.
#
set -euo pipefail

API_URL="${DOKU_API_URL:-__API_URL__}"
TOKEN="__AGENT_TOKEN__"

FIREWALL=""
KEY=""
SECRET="${FIREWALL_SECRET:-}"
TYP="opnsense"
UNSICHER=""

hilfe() {
  sed -n '2,21p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --url|--firewall) FIREWALL="$2"; shift 2 ;;
    --key) KEY="$2"; shift 2 ;;
    --secret) SECRET="$2"; shift 2 ;;
    --typ|--type) TYP="$2"; shift 2 ;;
    --api-url) API_URL="$2"; shift 2 ;;
    # Eine Firewall bringt ab Werk ein selbst signiertes Zertifikat mit.
    --unsicher|--zertifikat-ignorieren|-k) UNSICHER="-k"; shift ;;
    -h|--help|--hilfe) hilfe 0 ;;
    *) echo "Unbekannte Option: $1" >&2; hilfe 1 ;;
  esac
done

command -v jq >/dev/null 2>&1 || {
  echo "Fehler: jq wird gebraucht (macOS: brew install jq, Debian/Ubuntu: apt install jq)." >&2
  exit 1
}

[ "$TYP" = "opnsense" ] || { echo "Fehler: --typ $TYP wird (noch) nicht unterstuetzt, nur opnsense." >&2; exit 1; }
[ -n "$FIREWALL" ] || { echo "Fehler: --url fehlt." >&2; hilfe 1; }
[ -n "$KEY" ] || { echo "Fehler: --key fehlt." >&2; hilfe 1; }

if [ -z "$SECRET" ]; then
  read -rsp "Secret zum API-Key: " SECRET
  echo
fi

FIREWALL="${FIREWALL%/}"
case "$FIREWALL" in
  http://*|https://*) ;;
  *) FIREWALL="https://$FIREWALL" ;;
esac

ARBEIT="$(mktemp -d)"
trap 'rm -rf "$ARBEIT"' EXIT

# Ein GET gegen die OPNsense-API. Gibt die Antwort aus oder "{}", wenn es
# den Endpunkt in dieser Version nicht gibt - die Doku soll an einer
# fehlenden Kleinigkeit nicht scheitern. Abmeldung und Netzfehler dagegen
# brechen ab: dann stimmt etwas Grundsaetzliches nicht.
api() {
  local pfad="$1" datei="$ARBEIT/antwort" code
  code="$(curl -sS $UNSICHER -u "$KEY:$SECRET" -o "$datei" -w '%{http_code}' \
    -H "Accept: application/json" "$FIREWALL/api/$pfad" 2>"$ARBEIT/fehler" || true)"
  [ -n "$code" ] || code=000

  case "$code" in
    200) jq -c '.' "$datei" 2>/dev/null || echo '{}' ;;
    000) {
           echo "Fehler: $FIREWALL nicht erreichbar:"
           sed 's/^/  /' "$ARBEIT/fehler"
           echo "  Adresse pruefen. Selbstsigniertes Zertifikat: --unsicher"
         } >&2
         exit 1 ;;
    401) echo "Fehler: die Firewall lehnt Key und Secret ab (HTTP 401)." >&2
         exit 1 ;;
    *)   echo '{}' ;;
  esac
}

echo "Frage $FIREWALL ab ..."

api "core/firmware/info"                     > "$ARBEIT/firmware.json"
api "core/firmware/status"                   > "$ARBEIT/status.json"
api "diagnostics/system/system_information"  > "$ARBEIT/system.json"
api "interfaces/overview/interfaces_info"    > "$ARBEIT/schnittstellen.json"
api "ipsec/connections/search_connection"    > "$ARBEIT/ipsec.json"
api "ipsec/tunnel/search_phase1"             > "$ARBEIT/ipsec_alt.json"
api "openvpn/instances/search"               > "$ARBEIT/openvpn.json"
api "wireguard/client/search_client"         > "$ARBEIT/wireguard.json"
api "firewall/d_nat/search_rule"             > "$ARBEIT/portweiterleitungen.json"
api "routes/gateway/status"                  > "$ARBEIT/gateways.json"

NUTZLAST_DATEI="$ARBEIT/nutzlast.json"

jq -n \
  --arg url "$FIREWALL" \
  --slurpfile fw "$ARBEIT/firmware.json" \
  --slurpfile st "$ARBEIT/status.json" \
  --slurpfile sys "$ARBEIT/system.json" \
  --slurpfile ifs "$ARBEIT/schnittstellen.json" \
  --slurpfile ipsec "$ARBEIT/ipsec.json" \
  --slurpfile ipsecalt "$ARBEIT/ipsec_alt.json" \
  --slurpfile ovpn "$ARBEIT/openvpn.json" \
  --slurpfile wg "$ARBEIT/wireguard.json" \
  --slurpfile nat "$ARBEIT/portweiterleitungen.json" \
  --slurpfile gw "$ARBEIT/gateways.json" '
  def leer: if . == null or . == "" then null else . end;
  def zeilen: (.rows // []) | if type == "array" then . else [] end;
  ($fw[0]) as $fw | ($st[0]) as $st | ($sys[0]) as $sys |
  ($ifs[0] | zeilen) as $schnittstellen |
  # Die MAC der ersten Schnittstelle bleibt, auch wenn jemand Namen oder
  # Adressen aendert - darueber findet der naechste Lauf denselben Eintrag.
  ([ $schnittstellen[] | .macaddr // empty | select(. != "" and . != "00:00:00:00:00:00") ] | first) as $mac |
  ($url | sub("^https?://"; "") | sub("[:/].*$"; "")) as $host |
  {
    identifier:   ("opnsense:" + ($mac // $host)),
    name:         (($sys.name // "") | leer // $host),
    manufacturer: "OPNsense",
    model:        (($fw.product.product_name // $fw.product_name // "") | leer),
    firmware:     (($fw.product.product_version // $fw.product_version // $st.product_version // $st.product.product_version // "") | leer),
    management_url: $url,
    interfaces: [ $schnittstellen[]
      | select((.enabled // true) != false)
      | { name:   ((.description // .identifier // .device // "?") | tostring),
          device: (.device // null),
          ip:     ((.addr4 // ((.ipv4 // [])[0].ipaddr) // "") | leer),
          vlan:   ((.vlan_tag // null) | if . == null or . == "" then null else (tonumber? // null) end) } ],
    vpns: (
      [ $ipsec[0] | zeilen | .[] | {type: "IPsec", name: ((.description // .id // "IPsec") | tostring), remote: ((.remote_addrs // "") | leer)} ]
      + [ $ipsecalt[0] | zeilen | .[] | {type: "IPsec", name: ((.description // .remote_gateway // "IPsec") | tostring), remote: ((.remote_gateway // "") | leer)} ]
      + [ $ovpn[0] | zeilen | .[] | {type: "OpenVPN", name: ((.description // .role // "OpenVPN") | tostring), remote: ((.remote // "") | leer)} ]
      + [ $wg[0] | zeilen | .[] | {type: "WireGuard", name: ((.name // "WireGuard") | tostring), remote: ((.serveraddress // "") | leer)} ]
    ),
    port_forwards: [ $nat[0] | zeilen | .[]
      | select((.disabled // "0") != "1")
      | { description: ((.descr // .description // "") | leer),
          interface:   ((.interface // "") | tostring | leer),
          protocol:    ((.protocol // "") | tostring | leer),
          port:        ((.["destination.port"] // .destination_port // .dstport // "") | tostring | leer),
          target:      ((.target // "") | tostring | leer),
          target_port: ((.local_port // .["local-port"] // "") | tostring | leer) } ],
    gateways: [ ($gw[0].items // [])[] | {name: (.name // "?"), address: ((.address // "") | leer)} ]
  }' > "$NUTZLAST_DATEI"

echo "Sende Dokumentation an $API_URL ..."
antwort="$(curl -sS -X POST "$API_URL" -w $'\n%{http_code}' \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --data-binary @"$NUTZLAST_DATEI")"
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
echo "Fertig. $(jq -r '"\(.interfaces|length) Schnittstellen, \(.vpns|length) VPNs, \(.port_forwards|length) Portweiterleitungen gemeldet."' "$NUTZLAST_DATEI")"
