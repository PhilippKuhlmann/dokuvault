#!/usr/bin/env bash
#
# Auto-Dokumentation fuer Microsoft 365 / Entra ID  ->  DokuVault
#
# Auf einem Mac oder Linux-Rechner mit Internetzugang ausfuehren:
#   bash microsoft365-doku.sh --tenant-id "..." --client-id "..."
#
# Das Secret kommt aus der Umgebungsvariable M365_CLIENT_SECRET oder wird
# abgefragt. Es als --client-secret mitzugeben ist moeglich, aber es steht dann
# in der Prozessliste und in der Shell-Historie.
#
# Meldet Postfaecher, verifizierte Domains und gebuchte Lizenzen. Rein lesend -
# im Tenant wird nichts veraendert.
#
# Voraussetzung ist eine einmalig angelegte App-Registrierung im Entra-Portal
# mit ausschliesslich lesenden Anwendungsberechtigungen:
#   User.Read.All, Domain.Read.All, Organization.Read.All
# (jeweils "Application permission", danach einmal Administratorzustimmung).
#
# Tenant-Id, Client-Id und Secret bleiben hier und werden NICHT in DokuVault
# gespeichert.
#
set -euo pipefail

API_URL="${DOKU_API_URL:-__API_URL__}"
TOKEN="__AGENT_TOKEN__"

TENANT=""
CLIENT=""
SECRET="${M365_CLIENT_SECRET:-}"

hilfe() {
  sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --tenant-id|--tenant) TENANT="$2"; shift 2 ;;
    --client-id|--client) CLIENT="$2"; shift 2 ;;
    --client-secret|--secret) SECRET="$2"; shift 2 ;;
    --api-url) API_URL="$2"; shift 2 ;;
    -h|--help|--hilfe) hilfe 0 ;;
    *) echo "Unbekannte Option: $1" >&2; hilfe 1 ;;
  esac
done

command -v jq >/dev/null 2>&1 || {
  echo "Fehler: jq wird gebraucht (macOS: brew install jq, Debian/Ubuntu: apt install jq)." >&2
  exit 1
}

[ -n "$TENANT" ] || { echo "Fehler: --tenant-id fehlt." >&2; hilfe 1; }
[ -n "$CLIENT" ] || { echo "Fehler: --client-id fehlt." >&2; hilfe 1; }

if [ -z "$SECRET" ]; then
  read -rsp "Client-Secret der App-Registrierung: " SECRET
  echo
fi

ZUGANG="$(curl -fsS -X POST "https://login.microsoftonline.com/$TENANT/oauth2/v2.0/token" \
  --data-urlencode "client_id=$CLIENT" \
  --data-urlencode "client_secret=$SECRET" \
  --data-urlencode "scope=https://graph.microsoft.com/.default" \
  --data-urlencode "grant_type=client_credentials" | jq -r '.access_token')"

[ -n "$ZUGANG" ] && [ "$ZUGANG" != "null" ] || {
  echo "Fehler: kein Zugangstoken erhalten. Tenant-Id, Client-Id und Secret pruefen." >&2
  exit 1
}

# Graph liefert hoechstens 100 Objekte je Seite und verweist mit
# @odata.nextLink auf die naechste. Ohne das Nachladen waere ab dem 101.
# Postfach die Doku still unvollstaendig - der schlimmste Fehler, den eine
# Dokumentation machen kann.
graph_alle() {
  local url="$1" alle='[]' antwort
  while [ -n "$url" ]; do
    antwort="$(curl -fsS -H "Authorization: Bearer $ZUGANG" "$url")"
    alle="$(jq -n --argjson a "$alle" --argjson b "$(jq '.value' <<<"$antwort")" '$a + $b')"
    url="$(jq -r '."@odata.nextLink" // empty' <<<"$antwort")"
  done
  printf '%s' "$alle"
}

BENUTZER="$(graph_alle 'https://graph.microsoft.com/v1.0/users?$select=id,displayName,mail,userPrincipalName,accountEnabled&$top=100')"
DOMAINS="$(graph_alle 'https://graph.microsoft.com/v1.0/domains')"
LIZENZEN="$(graph_alle 'https://graph.microsoft.com/v1.0/subscribedSkus')"

# Nur Konten mit Postfach: Raeume, Dienstkonten ohne Mail und Gastbenutzer
# ohne Adresse gehoeren nicht in die Postfachliste.
#
# skuPartNumber ist eine Kennung wie O365_BUSINESS_PREMIUM. Graph kennt keinen
# Anzeigenamen dazu - aus den Unterstrichen werden Leerzeichen, mehr laesst
# sich ehrlich nicht daraus machen.
PAYLOAD="$(jq -n --arg tenant "$TENANT" \
  --argjson u "$BENUTZER" --argjson d "$DOMAINS" --argjson l "$LIZENZEN" '
  {
    tenant: $tenant,
    mailboxes: [ $u[] | select(.mail != null) | {
      identifier: .id, name: .displayName, mail: .mail, username: .userPrincipalName
    } ],
    domains: [ $d[] | select(.isVerified == true) | {identifier: .id, name: .id} ],
    licences: [ $l[] | {
      identifier: .skuId,
      name:       (.skuPartNumber | gsub("_"; " ")),
      gebucht:    .prepaidUnits.enabled,
      belegt:     .consumedUnits
    } ]
  }')"

echo "Sende Dokumentation an $API_URL ..."
curl -fsS -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$PAYLOAD"
echo ""
echo "Fertig. $(jq -r '"\(.mailboxes|length) Postfaecher, \(.domains|length) Domains, \(.licences|length) Lizenzen"' <<<"$PAYLOAD") gemeldet."
