#!/usr/bin/env bash
#
# Auto-Dokumentation fuer Linux-Server (Debian/Ubuntu)  ->  DokuVault
# Meldet Hardware, Betriebssystem, IP-Adresse und laufende Dienste. Nur lesend.
# Als root auf dem Server ausfuehren:
#   bash linux-server-doku.sh
#
set -euo pipefail

# Ziel-URL: 1. Argument  >  Umgebungsvariable DOKU_API_URL  >  eingebettete URL.
#   ./linux-server-doku.sh https://doku.example/api/agent/linux-server
API_URL="${1:-${DOKU_API_URL:-__API_URL__}}"
TOKEN="__AGENT_TOKEN__"

json_str() {
  local s="${1:-}"
  s="${s//\\/\\\\}"
  s="${s//\"/\\\"}"
  s="${s//$'\n'/\\n}"
  s="${s//$'\t'/\\t}"
  printf '"%s"' "$s"
}
num() { local n="${1:-}"; [[ "$n" =~ ^[0-9]+$ ]] && printf '%s' "$n" || printf 'null'; }

# Placeholders of desktop and whitebox boards ("System manufacturer") are no
# information - then the baseboard usually has the real values.
platzhalter() {
  local v
  v="$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  case "$v" in
    ""|"system manufacturer"|"system product name"|"system serial number"|"system version"|\
    "to be filled by o.e.m."|"to be filled by oem"|"default string"|"default"|"not specified"|\
    "not applicable"|"none"|"n/a"|"oem"|"o.e.m."|"unknown"|"0123456789"|"123456789"|"1234567890"|\
    "base board serial number"|"all series") return 0 ;;
  esac
  [[ "$v" =~ ^[0x[:space:].-]+$ ]]
}
dmi() {
  local wert
  wert="$(dmidecode -s "system-$1" 2>/dev/null | head -n1 || true)"
  if platzhalter "$wert"; then
    wert="$(dmidecode -s "baseboard-$2" 2>/dev/null | head -n1 || true)"
  fi
  platzhalter "$wert" && wert=""
  printf '%s' "$wert"
}

IDENTIFIER="linux/$(cat /etc/machine-id 2>/dev/null || hostname)"
HOSTNAME="$(hostname -f 2>/dev/null || hostname)"
MANUFACTURER="$(dmi manufacturer manufacturer)"
MODEL="$(dmi product-name product-name)"
SERIAL="$(dmi serial-number serial-number)"
IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
OS_ID="$(sed -n 's/^ID=//p' /etc/os-release 2>/dev/null | tr -d '"' | head -n1)"
OS_VERSION="$(sed -n 's/^VERSION_ID=//p' /etc/os-release 2>/dev/null | tr -d '"' | head -n1)"
CPU_MODEL="$(lscpu 2>/dev/null | sed -n 's/^Model name:[[:space:]]*//p' | head -n1)"
CORES="$(nproc 2>/dev/null || echo '')"
CPU="${CPU_MODEL:-unbekannt} (${CORES:-?} Kerne)"
MEM_GB="$(free -g 2>/dev/null | awk '/^Mem:/{print $2}')"

# A VM is usually documented already by its hypervisor - DokuVault then
# completes that entry instead of adding a server.
VIRTUAL=false
if command -v systemd-detect-virt >/dev/null 2>&1 && systemd-detect-virt --quiet --vm 2>/dev/null; then
  VIRTUAL=true
fi

# Running services DokuVault knows (apache2, nginx, docker, mariadb, ...).
SERVICES_JSON=""
for unit in apache2 httpd nginx docker mariadb mysql postgresql named bind9 smbd nfs-server cups isc-dhcp-server kea-dhcp4-server; do
  if systemctl is-active --quiet "$unit" 2>/dev/null; then
    SERVICES_JSON="${SERVICES_JSON:+$SERVICES_JSON,}$(json_str "$unit")"
  fi
done

PAYLOAD="{\"server\":{\"identifier\":$(json_str "$IDENTIFIER"),\"hostname\":$(json_str "$HOSTNAME"),\"manufacturer\":$(json_str "$MANUFACTURER"),\"model\":$(json_str "$MODEL"),\"serial\":$(json_str "$SERIAL"),\"ip\":$(json_str "$IP"),\"os_id\":$(json_str "$OS_ID"),\"os_version\":$(json_str "$OS_VERSION"),\"virtual\":$VIRTUAL,\"cpu\":$(json_str "$CPU"),\"memory_gb\":$(num "$MEM_GB"),\"services\":[$SERVICES_JSON]}}"

echo "Sende Dokumentation an $API_URL ..."
curl -fsS -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"
echo ""
echo "Fertig."
