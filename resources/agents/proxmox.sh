#!/usr/bin/env bash
#
# Auto-Dokumentation fuer Proxmox VE  ->  DokuVault
# Als root auf dem Proxmox-Host ausfuehren:
#   bash proxmox-doku.sh
#
set -euo pipefail

# Ziel-URL: 1. Argument  >  Umgebungsvariable DOKU_API_URL  >  eingebettete URL.
# So kann man die URL ueberschreiben, ohne einen neuen Token zu erzeugen:
#   ./proxmox-doku.sh https://doku.example/api/agent/proxmox
API_URL="${1:-${DOKU_API_URL:-__API_URL__}}"
TOKEN="__AGENT_TOKEN__"

# --- JSON-String sauber escapen (reines Bash) ---
json_str() {
  local s="${1:-}"
  s="${s//\\/\\\\}"
  s="${s//\"/\\\"}"
  s="${s//$'\n'/\\n}"
  s="${s//$'\t'/\\t}"
  printf '"%s"' "$s"
}

num() { local n="${1:-}"; [[ "$n" =~ ^[0-9]+$ ]] && printf '%s' "$n" || printf 'null'; }

# --- Host-Infos sammeln ---
IDENTIFIER="$(cat /etc/machine-id 2>/dev/null || hostname)"
HOSTNAME="$(hostname -f 2>/dev/null || hostname)"
MANUFACTURER="$(dmidecode -s system-manufacturer 2>/dev/null | head -n1 || true)"
MODEL="$(dmidecode -s system-product-name 2>/dev/null | head -n1 || true)"
SERIAL="$(dmidecode -s system-serial-number 2>/dev/null | head -n1 || true)"
IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
PVE_VERSION="$(pveversion 2>/dev/null | sed -n 's#.*pve-manager/\([0-9.]*\).*#\1#p' | head -n1)"
KERNEL="$(uname -r 2>/dev/null)"
CPU_MODEL="$(lscpu 2>/dev/null | sed -n 's/^Model name:[[:space:]]*//p' | head -n1)"
CORES="$(nproc 2>/dev/null || echo '')"
CPU="${CPU_MODEL:-unbekannt} (${CORES:-?} Kerne)"
MEM_GB="$(free -g 2>/dev/null | awk '/^Mem:/{print $2}')"

# --- Storage (pvesm status) ---
STORAGES_JSON=""
if command -v pvesm >/dev/null 2>&1; then
  while read -r sname stype _ total used _; do
    [ -z "${sname:-}" ] && continue
    tot_gb=$(( ${total:-0} / 1024 / 1024 ))
    use_gb=$(( ${used:-0} / 1024 / 1024 ))
    obj="{\"name\":$(json_str "$sname"),\"type\":$(json_str "$stype"),\"total_gb\":$(num "$tot_gb"),\"used_gb\":$(num "$use_gb")}"
    STORAGES_JSON="${STORAGES_JSON:+$STORAGES_JSON,}$obj"
  done < <(pvesm status 2>/dev/null | awk 'NR>1{print $1" "$2" "$3" "$4" "$5" "$6}')
fi

# --- Gaeste (VMs + LXC) sammeln ---
GUESTS_JSON=""
add_guest() { GUESTS_JSON="${GUESTS_JSON:+$GUESTS_JSON,}$1"; }

if command -v qm >/dev/null 2>&1; then
  while read -r vmid name status; do
    [ -z "${vmid:-}" ] && continue
    ostype="$(qm config "$vmid" 2>/dev/null | sed -n 's/^ostype:[[:space:]]*//p' | head -n1)"
    cores="$(qm config "$vmid" 2>/dev/null | sed -n 's/^cores:[[:space:]]*//p' | head -n1)"
    memmb="$(qm config "$vmid" 2>/dev/null | sed -n 's/^memory:[[:space:]]*//p' | head -n1)"
    memgb=$(( ${memmb:-0} / 1024 ))
    ip="$(qm agent "$vmid" network-get-interfaces 2>/dev/null | grep -oE '"ip-address"[[:space:]]*:[[:space:]]*"[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+"' | grep -oE '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | grep -vE '^127\.' | head -n1 || true)"
    add_guest "{\"identifier\":$(json_str "${HOSTNAME}/qemu/${vmid}"),\"vmid\":$(num "$vmid"),\"name\":$(json_str "$name"),\"type\":\"qemu\",\"ostype\":$(json_str "$ostype"),\"ip\":$(json_str "$ip"),\"status\":$(json_str "$status"),\"cores\":$(num "$cores"),\"memory_gb\":$(num "$memgb")}"
  done < <(qm list 2>/dev/null | awk 'NR>1{print $1" "$2" "$3}')
fi

if command -v pct >/dev/null 2>&1; then
  while read -r vmid; do
    [ -z "${vmid:-}" ] && continue
    name="$(pct config "$vmid" 2>/dev/null | sed -n 's/^hostname:[[:space:]]*//p' | head -n1)"
    status="$(pct status "$vmid" 2>/dev/null | awk '{print $2}')"
    ostype="$(pct config "$vmid" 2>/dev/null | sed -n 's/^ostype:[[:space:]]*//p' | head -n1)"
    cores="$(pct config "$vmid" 2>/dev/null | sed -n 's/^cores:[[:space:]]*//p' | head -n1)"
    memmb="$(pct config "$vmid" 2>/dev/null | sed -n 's/^memory:[[:space:]]*//p' | head -n1)"
    memgb=$(( ${memmb:-0} / 1024 ))
    ip="$(pct config "$vmid" 2>/dev/null | grep -oE 'ip=[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | head -n1 | cut -d= -f2 || true)"
    if [ -z "${ip:-}" ]; then
      ip="$(pct exec "$vmid" -- hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi
    add_guest "{\"identifier\":$(json_str "${HOSTNAME}/lxc/${vmid}"),\"vmid\":$(num "$vmid"),\"name\":$(json_str "$name"),\"type\":\"lxc\",\"ostype\":$(json_str "$ostype"),\"ip\":$(json_str "$ip"),\"status\":$(json_str "$status"),\"cores\":$(num "$cores"),\"memory_gb\":$(num "$memgb")}"
  done < <(pct list 2>/dev/null | awk 'NR>1{print $1}')
fi

# --- Payload zusammenbauen ---
HOST_JSON="{\"identifier\":$(json_str "$IDENTIFIER"),\"hostname\":$(json_str "$HOSTNAME"),\"manufacturer\":$(json_str "$MANUFACTURER"),\"model\":$(json_str "$MODEL"),\"serial\":$(json_str "$SERIAL"),\"ip\":$(json_str "$IP"),\"pve_version\":$(json_str "$PVE_VERSION"),\"kernel\":$(json_str "$KERNEL"),\"cpu\":$(json_str "$CPU"),\"memory_gb\":$(num "$MEM_GB"),\"storages\":[$STORAGES_JSON]}"

PAYLOAD="{\"host\":$HOST_JSON,\"guests\":[$GUESTS_JSON]}"

echo "Sende Dokumentation an $API_URL ..."
curl -fsS -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"
echo ""
echo "Fertig."
