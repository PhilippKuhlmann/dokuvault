#!/usr/bin/env bash
#
# Auto-Dokumentation fuer Firewalls: Securepoint UTM  ->  DokuVault
#
# Auf einem Mac oder Linux-Rechner ausfuehren, der die UTM per SSH erreicht:
#   bash securepoint-doku.sh --host 192.168.175.1
#
# Die UTM hat keine REST-API. Das Script meldet sich deshalb per SSH an und
# liest alles mit "spcli --json" - in EINER Verbindung, das Kennwort wird
# also nur einmal abgefragt (oder ein SSH-Schluessel genommen, --schluessel).
# Gebraucht wird ein Konto, das spcli aufrufen darf (root).
#
# Meldet Version, Seriennummer, Lizenzlaufzeit, Schnittstellen und ihre
# Netze samt DHCP-Bereich, VPNs (IPsec, OpenVPN, WireGuard),
# Portweiterleitungen und Gateways. Rein lesend - es wird nichts an der UTM
# veraendert.
#
# Pre-Shared Keys und andere Geheimnisse liefert spcli mit aus. Das Script
# nimmt davon nichts mit: gesendet werden nur Name und Gegenstelle eines VPN.
# Das SSH-Kennwort bleibt hier und wird NICHT in DokuVault gespeichert.
#
# Nur fuer Securepoint. OPNsense hat ein eigenes Script; beide melden an
# denselben Endpunkt.
#
set -euo pipefail

API_URL="${DOKU_API_URL:-__API_URL__}"
TOKEN="__AGENT_TOKEN__"

UTM=""
BENUTZER="root"
PORT="22"
SCHLUESSEL=""

hilfe() {
  sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --host|--utm) UTM="$2"; shift 2 ;;
    --user|--benutzer) BENUTZER="$2"; shift 2 ;;
    --port) PORT="$2"; shift 2 ;;
    --schluessel|--key|-i) SCHLUESSEL="$2"; shift 2 ;;
    --api-url) API_URL="$2"; shift 2 ;;
    -h|--help|--hilfe) hilfe 0 ;;
    *) echo "Unbekannte Option: $1" >&2; hilfe 1 ;;
  esac
done

command -v jq >/dev/null 2>&1 || {
  echo "Fehler: jq wird gebraucht (macOS: brew install jq, Debian/Ubuntu: apt install jq)." >&2
  exit 1
}

[ -n "$UTM" ] || { echo "Fehler: --host fehlt." >&2; hilfe 1; }

ARBEIT="$(mktemp -d)"
trap 'rm -rf "$ARBEIT"' EXIT

# Alles in einer Sitzung. Jede Antwort steht hinter einer eigenen Marke,
# damit sie sich hier wieder auseinandernehmen laesst. Die DHCP-Pools stehen
# nicht in spcli, sondern in der erzeugten Konfiguration: ab UTM 14 Dnsmasq,
# davor ISC dhcpd.
FERN='
for c in "system info" "interface get" "interface address get" "interface zone get" \
         "ipsec get" "openvpn get" "wireguard get" \
         "rule get" "node get" "service get" "route get"; do
  echo "### $c"
  spcli --json $c 2>/dev/null || echo "{}"
done
echo "### dhcpd"
cat /etc/dhcp/dnsmasq-dhcpd.conf /var/service/dhcpd4/dhcpd.conf 2>/dev/null || true
'

SSH_OPTIONEN=(-p "$PORT" -o ConnectTimeout=15 -o LogLevel=ERROR)
[ -n "$SCHLUESSEL" ] && SSH_OPTIONEN+=(-i "$SCHLUESSEL")

echo "Frage $UTM per SSH ab ..."
if ! ssh "${SSH_OPTIONEN[@]}" "$BENUTZER@$UTM" "$FERN" > "$ARBEIT/roh.txt" 2>"$ARBEIT/fehler"; then
  {
    echo "Fehler: SSH-Anmeldung an $BENUTZER@$UTM:$PORT fehlgeschlagen:"
    sed 's/^/  /' "$ARBEIT/fehler"
    echo "  SSH muss auf der UTM fuer diese Adresse freigegeben sein (Netzwerk -> Servereinstellungen)."
  } >&2
  exit 1
fi

# Auseinandernehmen: "### rule get" -> rule_get.json
awk -v ziel="$ARBEIT" '
  /^### / { name = substr($0, 5); gsub(/ /, "_", name); datei = ziel "/" name ".json"; next }
  datei { print > datei }
' "$ARBEIT/roh.txt"

for f in system_info interface_get interface_address_get interface_zone_get ipsec_get openvpn_get \
         wireguard_get rule_get node_get service_get route_get; do
  jq -e '.result.content' "$ARBEIT/$f.json" >/dev/null 2>&1 || echo '{"result":{"content":[]}}' > "$ARBEIT/$f.json"
done
touch "$ARBEIT/dhcpd.json"

if ! jq -e '.result.content | length > 0' "$ARBEIT/system_info.json" >/dev/null; then
  echo "Fehler: spcli lieferte keine Systeminformationen - ist $UTM eine Securepoint UTM, und darf $BENUTZER spcli aufrufen?" >&2
  exit 1
fi

# DHCP-Bereiche samt DNS-Servern. Zwei Formate:
#  - Dnsmasq (UTM 14): "dhcp-range=set:3,10.0.33.10,10.0.33.100" und
#    "dhcp-option=tag:3,option:domain-name-servers,10.0.33.1" - Bereich und
#    DNS haengen ueber das Tag zusammen.
#  - ISC dhcpd (aeltere UTM): je "subnet"-Block "range a b;" und
#    "option domain-name-servers ...;".
awk '
  /^dhcp-range=/ {
    n = split(substr($0, 12), f, ","); i = 1; tag = ""
    if (f[1] ~ /^(set|tag):/) { tag = substr(f[1], index(f[1], ":") + 1); i = 2 }
    if (f[i] ~ /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/) { anz++; s[anz] = f[i]; e[anz] = f[i + 1]; t[anz] = tag }
  }
  /^dhcp-option=/ && /(domain-name-servers|^dhcp-option=(tag:[^,]*,)?6,)/ {
    n = split(substr($0, 13), f, ","); tag = ""; liste = ""
    for (j = 1; j <= n; j++) {
      if (f[j] ~ /^tag:/) tag = substr(f[j], 5)
      else if (f[j] ~ /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/) liste = liste (liste == "" ? "" : ",") f[j]
    }
    dnsfuer[tag] = liste
  }
  /^[ \t]*subnet[ \t]/ { start = ""; ende = ""; dns = "" }
  /^[ \t]*range[ \t]/ { gsub(/;/, ""); start = $2; ende = $3 }
  /option[ \t]+domain-name-servers/ { sub(/.*domain-name-servers[ \t]+/, ""); gsub(/[; \t]/, ""); dns = $0 }
  /^[ \t]*}/ && start != "" { print start "\t" ende "\t" dns; start = "" }
  END { for (k = 1; k <= anz; k++) print s[k] "\t" e[k] "\t" ((t[k] in dnsfuer) ? dnsfuer[t[k]] : dnsfuer[""]) }
' "$ARBEIT/dhcpd.json" | jq -R -s '
  split("\n") | map(select(length > 0) | split("\t")
    | {start: .[0], end: .[1], dns: ((.[2] // "") | split(",") | map(select(test("^[0-9]+(\\.[0-9]+){3}$"))))})
' > "$ARBEIT/dhcp.json"

NUTZLAST_DATEI="$ARBEIT/nutzlast.json"
HEUTE="$(date +%s)"

jq -n \
  --arg host "$UTM" \
  --argjson heute "$HEUTE" \
  --slurpfile sys "$ARBEIT/system_info.json" \
  --slurpfile ifs "$ARBEIT/interface_get.json" \
  --slurpfile adr "$ARBEIT/interface_address_get.json" \
  --slurpfile zonen "$ARBEIT/interface_zone_get.json" \
  --slurpfile ipsec "$ARBEIT/ipsec_get.json" \
  --slurpfile ovpn "$ARBEIT/openvpn_get.json" \
  --slurpfile wg "$ARBEIT/wireguard_get.json" \
  --slurpfile regeln "$ARBEIT/rule_get.json" \
  --slurpfile knoten "$ARBEIT/node_get.json" \
  --slurpfile dienste "$ARBEIT/service_get.json" \
  --slurpfile routen "$ARBEIT/route_get.json" \
  --slurpfile dhcp "$ARBEIT/dhcp.json" '
  def inhalt: (.result.content // []) | if type == "array" then . else [] end;
  def leer: if . == null or . == "" or . == "unknown" or . == "none" then null else . end;
  ($sys[0] | inhalt | map({key: .attribute, value: .value}) | from_entries) as $s |
  # Seriennummer: die des Geraets, bei einer virtuellen UTM die der Lizenz-Hardware.
  (($s.serialnumber | leer) // ($s["hardware serialnumber"] | leer)) as $serie |
  # WAN: alles in der Zone "external".
  [ $zonen[0] | inhalt | .[] | select(.name == "external") | .interface ] as $wan |
  # Der Zonenname ("internal", "dmz1", "gaeste") sagt mehr als "LAN3.33" -
  # er wird in DokuVault die Bezeichnung des Netzes.
  ([ $zonen[0] | inhalt | .[] | select(.interface != null and ((.flags // []) | length == 0)) | {key: .interface, value: .name} ] | from_entries) as $zone |
  ($knoten[0] | inhalt | map({key: .name, value: .address}) | from_entries) as $adressen |
  ($dienste[0] | inhalt | map({key: .name, value: {proto: .proto, ports: (.["dst-ports"] // [])}}) | from_entries) as $ports |
  def vlan($if): ($if.options // []) | map(select(startswith("vlan_id="))) | first | (. // "") | sub("vlan_id="; "") | (tonumber? // null);
  {
    identifier:   ("securepoint:" + ($serie // $host)),
    name:         (($s.hostname | leer) // $host),
    manufacturer: "Securepoint",
    model:        ($s.devicetype | leer),
    serial:       $serie,
    firmware:     ($s.version | leer),
    # Die Lizenz laeuft in "daysleft" Tagen ab - als Datum in die Ablauf-Mail.
    subscription_until: (if ($s.daysleft | tostring | test("^[0-9]+$"))
                         then ($heute + ($s.daysleft | tonumber) * 86400 | strftime("%Y-%m-%d")) else null end),
    # Die Verwaltungsoberflaeche der UTM laeuft auf Port 11115.
    management_url: ("https://" + $host + ":11115"),
    # VPN-Tunnel (tun, WireGuard, PPP) sind keine Netze der Doku - die VPNs
    # stehen unten eigens.
    interfaces: [ $ifs[0] | inhalt | .[] | select((.type // "") | test("^(TUN|TAP|PPP|PPPOE|PPTP|WIREGUARD|WG)$"; "i") | not) | . as $if
      | ([ $adr[0] | inhalt | .[] | select(.device == $if.name) | .address | select(test("^[0-9.]+/")) ]) as $ips
      | (if ($ips | length) > 0 then $ips[] else null end) as $ip
      | { name: ($zone[$if.name] // $if.name), device: $if.name, ip: $ip, vlan: vlan($if),
          wan: ([$wan[] | select(. == $if.name)] | length > 0) } ],
    vpns: (
      [ $ipsec[0] | inhalt | .[] | {type: "IPsec", name: (.name // "IPsec"), remote: (.remote | leer)} ]
      # Ein OpenVPN-Server (SSL-VPN) hat keine Gegenstelle - dort sagen
      # Tunnelnetz und Port, was er ist.
      + [ $ovpn[0] | inhalt | .[] | {type: "OpenVPN", name: (.name // "OpenVPN"),
            remote: (if (.mode // "") == "SERVER"
                     then ([(.pool // "" | leer), ([(.proto // "" | leer), ((.local_port // "") | tostring | leer)] | map(select(. != null)) | join(" ") | leer)] | map(select(. != null)) | join(" · ") | leer)
                     else ((.remote // "") | tostring | leer) end)} ]
      # Je Peer: sein Endpunkt, bei einem Roadwarrior ohne Endpunkt sein Netz.
      + [ $wg[0] | inhalt | .[] | (.content // [])[] | {type: "WireGuard", name: (.peer_name // "WireGuard"),
            remote: (((.peer_endpoint // "") | tostring | leer) // ((.peer_address_list // []) | join(", ") | leer))} ]
    ),
    # Portweiterleitungen sind Regeln mit DESTNAT: Ziel-Host und -Dienst
    # stehen als Namen darin und werden hier zu Adresse und Port aufgeloest.
    port_forwards: [ $regeln[0] | inhalt | .[]
      | select((.flags // []) | index("DESTNAT"))
      | select((.flags // []) | index("DISABLED") | not)
      | { description: ((.comment // "") | leer),
          interface:   (.dst // null),
          protocol:    ($ports[.service].proto // null | if . then ascii_upcase else null end),
          port:        (($ports[.service].ports // []) | join(",") | leer),
          target:      (($adressen[.nat_node] // .nat_node // "") | sub("/32$"; "") | leer),
          target_port: (($ports[.nat_service // .service].ports // []) | join(",") | leer) } ],
    gateways: ([ $routen[0] | inhalt | .[] | select(.dst == "0.0.0.0/0") | {name: (.via_device // .router // "?"),
                   # Bei DHCP am WAN steht hier nur die Schnittstelle, keine Adresse.
                   address: (.router // "" | if test("^[0-9]+(\\.[0-9]+){3}$") then . else null end)} ] | unique),
    dhcp_ranges: $dhcp[0]
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
