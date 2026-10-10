#!/usr/bin/env bash
#
# Auto-Dokumentation fuer Firewalls: OPNsense  ->  DokuVault
#
# Auf einem Mac oder Linux-Rechner ausfuehren, der die Firewall erreicht:
#   bash opnsense-doku.sh --url https://opnsense.local --key "..."
#
# Das Secret kommt aus der Umgebungsvariable FIREWALL_SECRET oder wird
# abgefragt. Es als --secret mitzugeben ist moeglich, aber es steht dann in
# der Prozessliste und in der Shell-Historie.
#
# Key und Secret legt man in der OPNsense unter System -> Zugang -> Benutzer
# an (API-Schluessel). Ein eigener Benutzer mit diesen Rechten genuegt:
#   Lobby: Dashboard, System: Firmware, Status: Interfaces,
#   Interfaces: VLAN, VPN: IPsec: Connections, VPN: OpenVPN: Instances,
#   VPN: WireGuard: Configuration, Firewall: NAT: Destination NAT,
#   System: Gateways
#   und fuer DHCP: Services: Dnsmasq DNS/DHCP: Settings oder
#   Services: DHCP: Kea(v4) - je nachdem, welcher laeuft
# Reine Leserechte kennt die OPNsense nicht - das Script liest nur, der
# Schluessel koennte mehr. Ihn deshalb nicht weitergeben.
#
# Meldet Version, Schnittstellen und ihre Netze samt DHCP-Bereich, VPNs
# (IPsec, OpenVPN, WireGuard), Portweiterleitungen und Gateways. Rein
# lesend - es wird nichts an der Firewall veraendert. Was eine aeltere Version nicht anbietet, fehlt einfach.
#
# Key und Secret bleiben hier und werden NICHT in DokuVault gespeichert.
#
# Nur fuer OPNsense. Securepoint hat ein eigenes Script; beide melden an
# denselben Endpunkt.
#
set -euo pipefail

API_URL="${DOKU_API_URL:-__API_URL__}"
TOKEN="__AGENT_TOKEN__"

FIREWALL=""
KEY=""
SECRET="${FIREWALL_SECRET:-}"
UNSICHER=""

hilfe() {
  sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --url|--firewall) FIREWALL="$2"; shift 2 ;;
    --key) KEY="$2"; shift 2 ;;
    --secret) SECRET="$2"; shift 2 ;;
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
  local pfad="$1" optional="${2:-}" datei="$ARBEIT/antwort" code
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
    # Key gilt, aber dem Benutzer fehlt das Recht auf diese Seite. Nicht
    # stillschweigend uebergehen - sonst kommt eine fast leere Firewall an.
    403) if [ -n "$optional" ]; then echo "$pfad" >> "$ARBEIT/ohne_recht_optional"
         else echo "$pfad" >> "$ARBEIT/ohne_recht"; fi
         echo '{}' ;;
    *)   echo '{}' ;;
  esac
}

echo "Frage $FIREWALL ab ..."

api "core/firmware/info"                     > "$ARBEIT/firmware.json"
api "core/firmware/status"                   > "$ARBEIT/status.json"
api "diagnostics/system/system_information"  > "$ARBEIT/system.json"
api "interfaces/overview/interfaces_info"    > "$ARBEIT/schnittstellen.json"
api "interfaces/vlan_settings/search_item"   > "$ARBEIT/vlans.json"
api "ipsec/connections/search_connection"    > "$ARBEIT/ipsec.json"
# Nur Versionen vor 23.1 - neuere haben dafuer kein Recht mehr.
api "ipsec/tunnel/search_phase1" optional    > "$ARBEIT/ipsec_alt.json"
api "openvpn/instances/search"               > "$ARBEIT/openvpn.json"
api "wireguard/client/search_client"         > "$ARBEIT/wireguard.json"
api "firewall/d_nat/search_rule"             > "$ARBEIT/portweiterleitungen.json"
api "routes/gateway/status"                  > "$ARBEIT/gateways.json"
# DHCP laeuft ueber Dnsmasq (Standard seit 25.1) oder Kea - meist nur einer,
# und nur fuer den braucht der Benutzer das Recht.
api "dnsmasq/settings/search_range" optional > "$ARBEIT/dnsmasq.json"
api "dnsmasq/settings/search_option" optional > "$ARBEIT/dnsmasq_optionen.json"
api "kea/dhcpv4/search_subnet" optional      > "$ARBEIT/kea.json"
if grep -q dnsmasq "$ARBEIT/ohne_recht_optional" 2>/dev/null && grep -q kea "$ARBEIT/ohne_recht_optional"; then
  echo "dnsmasq/settings/search_range oder kea/dhcpv4/search_subnet (DHCP-Bereiche)" >> "$ARBEIT/ohne_recht"
fi

if [ -s "$ARBEIT/ohne_recht" ]; then
  {
    echo "Hinweis: dem API-Benutzer fehlen Rechte (HTTP 403) fuer:"
    sed 's/^/  \/api\//' "$ARBEIT/ohne_recht"
    echo "  In der OPNsense unter System -> Zugang -> Benutzer -> Rechte ergaenzen."
  } >&2
  # Gar nichts lesbar: nichts melden, statt eine leere Firewall anzulegen.
  if [ ! -s "$ARBEIT/schnittstellen.json" ] || [ "$(cat "$ARBEIT/schnittstellen.json")" = "{}" ]; then
    echo "Fehler: ohne Zugriff auf die Schnittstellen wird nichts gemeldet." >&2
    exit 1
  fi
fi

NUTZLAST_DATEI="$ARBEIT/nutzlast.json"

jq -n \
  --arg url "$FIREWALL" \
  --slurpfile fw "$ARBEIT/firmware.json" \
  --slurpfile st "$ARBEIT/status.json" \
  --slurpfile sys "$ARBEIT/system.json" \
  --slurpfile ifs "$ARBEIT/schnittstellen.json" \
  --slurpfile vlans "$ARBEIT/vlans.json" \
  --slurpfile ipsec "$ARBEIT/ipsec.json" \
  --slurpfile ipsecalt "$ARBEIT/ipsec_alt.json" \
  --slurpfile ovpn "$ARBEIT/openvpn.json" \
  --slurpfile wg "$ARBEIT/wireguard.json" \
  --slurpfile nat "$ARBEIT/portweiterleitungen.json" \
  --slurpfile gw "$ARBEIT/gateways.json" \
  --slurpfile dnsmasq "$ARBEIT/dnsmasq.json" \
  --slurpfile dnsopt "$ARBEIT/dnsmasq_optionen.json" \
  --slurpfile kea "$ARBEIT/kea.json" '
  # "~" schreibt die OPNsense, wo ein Wert fehlt (Gateway ohne Adresse).
  def leer: if . == null or . == "" or . == "~" then null else . end;
  def zeilen: (.rows // []) | if type == "array" then . else [] end;
  # Abgeschaltete VPNs sind keine Verbindung, die jemand nachschlagen muss.
  def aktiv: select((.enabled // "1") | tostring | . != "0");
  # "8.8.8.8, 1.1.1.1" -> ["8.8.8.8","1.1.1.1"]; nur IPv4.
  def adressen: (. // "") | tostring | split(",") | map(gsub("\\s"; "")) | map(select(test("^[0-9]+(\\.[0-9]+){3}$")));
  # DHCP-Option 6 (DNS-Server) - je Schnittstelle oder fuer alle.
  [ $dnsopt[0] | zeilen | .[] | select((.option | tostring) == "6" and (.type // "set") == "set") ] as $dnsopts |
  # interfaces_info nennt den VLAN-Tag nicht immer - die VLAN-Liste kennt
  # ihn zum Geraetenamen (vlan01).
  ([ $vlans[0] | zeilen | .[] | {key: (.vlanif // ""), value: .tag} ] | from_entries) as $vlantags |
  ($fw[0]) as $fw | ($st[0]) as $st | ($sys[0]) as $sys |
  # Nur zugewiesene Schnittstellen: Loopback und die unbelegten (enc0,
  # pflog0) haben keine Kennung und gehoeren nicht in die Doku.
  # VPN-Tunnel (WireGuard, OpenVPN, IPsec) koennen zugewiesen sein, sind
  # aber keine Netze der Doku - die VPNs stehen unten eigens.
  [ ($ifs[0] | zeilen)[] | select((.identifier // "") != "" and .device != "lo0")
    | select((.device // "") | test("^(wg|ovpn|tun|tap|ipsec|enc|gif|gre|ppp)") | not) ] as $schnittstellen |
  # Die MAC der LAN-Schnittstelle (sonst der ersten) bleibt, auch wenn
  # jemand Namen oder Adressen aendert - darueber findet der naechste Lauf
  # denselben Eintrag.
  ([ ($schnittstellen[] | select(.identifier == "lan")), $schnittstellen[] | .macaddr // empty
     | select(. != "" and . != "00:00:00:00:00:00") ] | first) as $mac |
  ($url | sub("^https?://"; "") | sub("[:/].*$"; "")) as $host |
  {
    identifier:   ("opnsense:" + ($mac // $host)),
    name:         (($sys.name // "") | leer // $host),
    manufacturer: "OPNsense",
    # Das Produkt heisst immer "OPNsense" - als Modell sagte das nichts.
    model:        null,
    firmware:     (($fw.product.product_version // $fw.product_version // $st.product_version // $st.product.product_version // "") | leer),
    management_url: $url,
    interfaces: [ $schnittstellen[]
      | select((.enabled // true) != false)
      | { name:   ((.description // .identifier // .device // "?") | tostring),
          device: (.device // null),
          # WAN wird kein VLAN in der Doku - das Netz gehoert dem Provider.
          wan:    ((.identifier // "") | test("^wan")),
          ip:     ((.addr4 // ((.ipv4 // [])[0].ipaddr) // "") | leer),
          vlan:   ((.vlan_tag // $vlantags[.device // ""] // null) | if . == null or . == "" then null else (tonumber? // null) end) } ],
    vpns: (
      [ $ipsec[0] | zeilen | .[] | aktiv | {type: "IPsec", name: ((.description // .id // "IPsec") | tostring), remote: ((.remote_addrs // "") | leer)} ]
      + [ $ipsecalt[0] | zeilen | .[] | aktiv | {type: "IPsec", name: ((.description // .remote_gateway // "IPsec") | tostring), remote: ((.remote_gateway // "") | leer)} ]
      # Ein OpenVPN-Server hat keine Gegenstelle - dort sagen Tunnelnetz
      # und Port, was er ist.
      + [ $ovpn[0] | zeilen | .[] | aktiv | {type: "OpenVPN", name: ((.description // .role // "OpenVPN") | tostring),
            remote: (if .role == "server"
                     then ([(.server // "" | leer), ([(.["%proto"] // .proto // "" | leer), (.port // "" | leer)] | map(select(. != null)) | join(" ") | leer)] | map(select(. != null)) | join(" · ") | leer)
                     else ((.remote // "") | leer) end)} ]
      + [ $wg[0] | zeilen | .[] | aktiv | {type: "WireGuard", name: ((.name // "WireGuard") | tostring), remote: ((.serveraddress // "") | leer)} ]
    ),
    port_forwards: [ $nat[0] | zeilen | .[]
      | select((.disabled // "0") != "1")
      # Die Anti-Lockout-Regeln legt die OPNsense selbst an; sie leiten
      # nichts weiter (nordr) und sind keine Doku wert.
      | select((.is_automatic // false) != true and (.nordr // "0") != "1")
      | { description: ((.descr // .description // "") | leer),
          interface:   ((.["%interface"] // .interface // "") | tostring | leer),
          protocol:    ((.["%protocol"] // .protocol // "") | tostring | leer),
          port:        ((.["destination.port"] // .destination_port // .dstport // "") | tostring | leer),
          target:      ((.target // "") | tostring | leer),
          target_port: ((.local_port // .["local-port"] // "") | tostring | leer) } ],
    gateways: [ ($gw[0].items // [])[] | {name: (.name // "?"), address: ((.address // "") | leer)} ],
    # Nur die Bereiche - welches Netz dazugehoert, rechnet DokuVault aus.
    dhcp_ranges: (
      [ $dnsmasq[0] | zeilen | .[]
        | select((.start_addr // "") | test("^[0-9]+\\.")) | select((.end_addr // "") != "")
        | .interface as $if
        # Ohne eigene Option verteilt die OPNsense sich selbst - dann bleibt
        # dns leer und DokuVault traegt die Firewall ein.
        | {start: .start_addr, end: .end_addr,
           dns: ((([ $dnsopts[] | select(.interface == $if) ] + [ $dnsopts[] | select((.interface // "") == "") ]) | first | .value) | adressen)} ]
      + [ $kea[0] | zeilen | .[]
          | ((.["option_data.domain_name_servers"] // .option_data.domain_name_servers // "") | adressen) as $dns
          | (.pools // "") | split("[\\n, ]+"; null)[]
          | select(test("^[0-9.]+-[0-9.]+$")) | split("-") | {start: .[0], end: .[1], dns: $dns} ]
    )
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
