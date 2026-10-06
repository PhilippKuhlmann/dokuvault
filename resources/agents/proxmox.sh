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
# Desktop and whitebox boards leave placeholders in the system fields
# ("System manufacturer", "To Be Filled By O.E.M."). Then the baseboard
# (mainboard) usually has the real maker, model and serial.
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
MANUFACTURER="$(dmi manufacturer manufacturer)"
MODEL="$(dmi product-name product-name)"
SERIAL="$(dmi serial-number serial-number)"
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

# Betriebssystem des Gastes: ID und VERSION_ID aus /etc/os-release.
#
# Nicht der ostype aus der Proxmox-Konfiguration: der sagt "l26" fuer jedes
# Linux der letzten fuenfzehn Jahre. Debian 12 und Debian 13 haben
# verschiedene Support-Enden - wer das nicht unterscheiden kann, meldet
# lieber nichts.
OS_ID=""
OS_VERSION=""

os_reset() { OS_ID=""; OS_VERSION=""; }

# Proxmox tags ("linux;docker") as a JSON list - DokuVault takes those that
# name a service of its catalog.
tags_json() {
  local out="" t
  for t in $(printf '%s' "${1:-}" | tr ';, ' '   '); do
    out="${out:+$out,}$(json_str "$t")"
  done
  printf '[%s]' "$out"
}

# Wert eines JSON-Feldes aus der Antwort des QEMU-Gastagenten.
json_feld() { printf '%s' "$2" | grep -oE "\"$1\"[[:space:]]*:[[:space:]]*\"[^\"]*\"" | head -n1 | sed 's/.*:[[:space:]]*"//; s/"$//'; }

if command -v qm >/dev/null 2>&1; then
  while read -r vmid name status; do
    [ -z "${vmid:-}" ] && continue
    cores="$(qm config "$vmid" 2>/dev/null | sed -n 's/^cores:[[:space:]]*//p' | head -n1)"
    memmb="$(qm config "$vmid" 2>/dev/null | sed -n 's/^memory:[[:space:]]*//p' | head -n1)"
    memgb=$(( ${memmb:-0} / 1024 ))
    ip="$(qm agent "$vmid" network-get-interfaces 2>/dev/null | grep -oE '"ip-address"[[:space:]]*:[[:space:]]*"[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+"' | grep -oE '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | grep -vE '^127\.' | head -n1 || true)"
    # Braucht den QEMU-Gastagenten. Fehlt er, bleibt das Betriebssystem leer.
    os_reset
    osinfo="$(qm agent "$vmid" get-osinfo 2>/dev/null || true)"
    if [ -n "${osinfo:-}" ]; then
      OS_ID="$(json_feld id "$osinfo")"
      OS_VERSION="$(json_feld version-id "$osinfo")"
    fi
    tags="$(qm config "$vmid" 2>/dev/null | sed -n 's/^tags:[[:space:]]*//p' | head -n1)"
    add_guest "{\"identifier\":$(json_str "${HOSTNAME}/qemu/${vmid}"),\"tags\":$(tags_json "$tags"),\"vmid\":$(num "$vmid"),\"name\":$(json_str "$name"),\"type\":\"qemu\",\"os_id\":$(json_str "$OS_ID"),\"os_version\":$(json_str "$OS_VERSION"),\"ip\":$(json_str "$ip"),\"status\":$(json_str "$status"),\"cores\":$(num "$cores"),\"memory_gb\":$(num "$memgb")}"
  done < <(qm list 2>/dev/null | awk 'NR>1{print $1" "$2" "$3}')
fi

if command -v pct >/dev/null 2>&1; then
  while read -r vmid; do
    [ -z "${vmid:-}" ] && continue
    name="$(pct config "$vmid" 2>/dev/null | sed -n 's/^hostname:[[:space:]]*//p' | head -n1)"
    status="$(pct status "$vmid" 2>/dev/null | awk '{print $2}')"
    cores="$(pct config "$vmid" 2>/dev/null | sed -n 's/^cores:[[:space:]]*//p' | head -n1)"
    memmb="$(pct config "$vmid" 2>/dev/null | sed -n 's/^memory:[[:space:]]*//p' | head -n1)"
    memgb=$(( ${memmb:-0} / 1024 ))
    ip="$(pct config "$vmid" 2>/dev/null | grep -oE 'ip=[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | head -n1 | cut -d= -f2 || true)"
    if [ -z "${ip:-}" ]; then
      ip="$(pct exec "$vmid" -- hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi
    # pct exec setzt einen laufenden Container voraus; ein gestoppter meldet
    # kein Betriebssystem.
    os_reset
    osrelease="$(pct exec "$vmid" -- cat /etc/os-release 2>/dev/null || true)"
    if [ -n "${osrelease:-}" ]; then
      OS_ID="$(printf '%s\n' "$osrelease" | sed -n 's/^ID=//p' | tr -d '"' | head -n1)"
      OS_VERSION="$(printf '%s\n' "$osrelease" | sed -n 's/^VERSION_ID=//p' | tr -d '"' | head -n1)"
    fi
    tags="$(pct config "$vmid" 2>/dev/null | sed -n 's/^tags:[[:space:]]*//p' | head -n1)"
    add_guest "{\"identifier\":$(json_str "${HOSTNAME}/lxc/${vmid}"),\"tags\":$(tags_json "$tags"),\"vmid\":$(num "$vmid"),\"name\":$(json_str "$name"),\"type\":\"lxc\",\"os_id\":$(json_str "$OS_ID"),\"os_version\":$(json_str "$OS_VERSION"),\"ip\":$(json_str "$ip"),\"status\":$(json_str "$status"),\"cores\":$(num "$cores"),\"memory_gb\":$(num "$memgb")}"
  done < <(pct list 2>/dev/null | awk 'NR>1{print $1}')
fi

# --- Backup-Jobs (vzdump, auch mit Proxmox Backup Server als Ziel) ---
# Aus /etc/pve/jobs.cfg (Proxmox VE 7.2 und neuer). Die Jobs gelten fuer den
# ganzen Cluster - die Kennung traegt deshalb den Clusternamen, damit jeder
# Knoten denselben Eintrag aktualisiert. Ergebnis und Zeit aus den
# vzdump-Laeufen dieses Knotens, die zum Job passen.
BACKUPS_JSON=""
json_zahl() { printf '%s' "$2" | grep -oE "\"$1\"[[:space:]]*:[[:space:]]*[0-9]+" | head -n1 | grep -oE '[0-9]+$' || true; }
iso_zeit() { if [ -n "${1:-}" ]; then date -d "@$1" -Iseconds 2>/dev/null || true; fi; }

# Last run of a job among the recent vzdump tasks (newest first, one line
# "id|status|endtime"). A task backing up several guests has no id; one of a
# single guest carries its VMID. Not every task belongs to the job: a manual
# backup of another VM must not mark the job as failed.
job_lauf() {
  local alle="$1" vmids=",$2," pool="$3" tid tstatus tende status zeit anzahl=0
  LETZTER_STATUS=""; LETZTER_LAUF=""; LETZTER_ERFOLG=""; LAEUFE_JSON=""
  while IFS='|' read -r tid tstatus tende; do
    [ -z "${tende:-}" ] && continue
    if [ -n "$tid" ] && [ "$alle" != "1" ] && [ -z "$pool" ] && [[ "$vmids" != *",$tid,"* ]]; then
      continue
    fi
    case "$tstatus" in
      OK) status="ok" ;;
      WARNINGS*) status="warning" ;;
      *) status="failed" ;;
    esac
    zeit="$(iso_zeit "$tende")"
    if [ -z "$LETZTER_LAUF" ]; then
      LETZTER_LAUF="$zeit"
      LETZTER_STATUS="$status"
    fi
    if [ -z "$LETZTER_ERFOLG" ] && [ "$status" = "ok" ]; then
      LETZTER_ERFOLG="$zeit"
    fi
    # The last 20 runs - the history on the backup overview in DokuVault.
    if [ "$anzahl" -lt 20 ] && [ -n "$zeit" ]; then
      LAEUFE_JSON="${LAEUFE_JSON:+$LAEUFE_JSON,}{\"status\":\"$status\",\"finished_at\":\"$zeit\"}"
      anzahl=$((anzahl + 1))
    fi
  done <<< "$TASKS"
}

if [ -f /etc/pve/jobs.cfg ]; then
  # A single node has no corosync.conf - then the host name. "|| true":
  # under set -e a failing sed would end the whole report.
  CLUSTER="$(sed -n 's/^[[:space:]]*cluster_name:[[:space:]]*//p' /etc/pve/corosync.conf 2>/dev/null | head -n1 || true)"
  CLUSTER="${CLUSTER:-$HOSTNAME}"

  TASKS="$(pvesh get "/nodes/$(hostname)/tasks" --typefilter vzdump --limit 200 --output-format json 2>/dev/null \
    | sed 's/},{/}\n{/g' \
    | while read -r t; do
        printf '%s|%s|%s\n' "$(json_feld id "$t" || true)" "$(json_feld status "$t" || true)" "$(json_zahl endtime "$t")"
      done || true)"

  # One line per job: id|schedule|storage|all|vmids|pool|prune|comment|enabled
  # (vmids: the excluded ones when "all" is set, else the backed up ones).
  while IFS='|' read -r jid zeitplan storage alle vmids pool prune kommentar aktiv; do
    [ -z "${jid:-}" ] && continue
    [ "${aktiv:-1}" = "0" ] && continue
    # ", " between the IDs: a comma list without spaces cannot wrap and ran
    # across the card.
    vmids="${vmids//,/, }"
    if [ "${alle:-0}" = "1" ]; then quelle="alle Gaeste${vmids:+ ausser $vmids}"
    elif [ -n "${pool:-}" ]; then quelle="Pool $pool"
    else quelle="${vmids:+VMs $vmids}"; fi
    job_lauf "${alle:-0}" "${vmids// /}" "${pool:-}"
    eintrag="{\"identifier\":$(json_str "proxmox/$CLUSTER/$jid"),\"name\":$(json_str "${kommentar:-vzdump nach $storage${zeitplan:+ ($zeitplan)}}"),\"software\":\"Proxmox vzdump\",\"source\":$(json_str "$quelle"),\"destination\":$(json_str "$storage"),\"schedule\":$(json_str "$zeitplan"),\"retention\":$(json_str "$prune"),\"last_status\":$(json_str "$LETZTER_STATUS"),\"last_run_at\":$(json_str "$LETZTER_LAUF"),\"last_success\":$(json_str "$LETZTER_ERFOLG"),\"runs\":[$LAEUFE_JSON]}"
    BACKUPS_JSON="${BACKUPS_JSON:+$BACKUPS_JSON,}$eintrag"
  done < <(awk '
    function aus() {
      if (id == "") return
      print id "|" f["schedule"] "|" f["storage"] "|" f["all"] "|" (f["all"] == "1" ? f["exclude"] : f["vmid"]) "|" f["pool"] "|" f["prune-backups"] "|" f["comment"] "|" f["enabled"]
    }
    /^[^[:space:]]/ { aus(); id = ($1 == "vzdump:") ? $2 : ""; split("", f); next }
    id != "" && NF >= 2 { k = $1; $1 = ""; sub(/^ /, ""); f[k] = $0 }
    END { aus() }
  ' /etc/pve/jobs.cfg)
fi

# --- Payload zusammenbauen ---
HOST_JSON="{\"identifier\":$(json_str "$IDENTIFIER"),\"hostname\":$(json_str "$HOSTNAME"),\"manufacturer\":$(json_str "$MANUFACTURER"),\"model\":$(json_str "$MODEL"),\"serial\":$(json_str "$SERIAL"),\"ip\":$(json_str "$IP"),\"pve_version\":$(json_str "$PVE_VERSION"),\"kernel\":$(json_str "$KERNEL"),\"cpu\":$(json_str "$CPU"),\"memory_gb\":$(num "$MEM_GB"),\"storages\":[$STORAGES_JSON]}"

PAYLOAD="{\"host\":$HOST_JSON,\"guests\":[$GUESTS_JSON],\"backups\":[$BACKUPS_JSON]}"

echo "Sende Dokumentation an $API_URL ..."
curl -fsS -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"
echo ""
echo "Fertig."
