#!/usr/bin/env bash
#
# DokuVault-Agent fuer Proxmox VE
#
# Richtet einen systemd-Timer ein, der stuendlich das aktuelle Proxmox-Script
# von DokuVault holt und ausfuehrt - nur lesend, wie das Script von Hand.
# Als root auf dem Proxmox-Host ausfuehren:
#
#   bash dokuvault-agent-proxmox.sh                 einrichten (stuendlich)
#   INTERVAL=15 bash dokuvault-agent-proxmox.sh     anderes Intervall (Minuten, mind. 5)
#   bash dokuvault-agent-proxmox.sh --uninstall     wieder entfernen
#
# Danach:
#   systemctl list-timers dokuvault-agent.timer     naechster Lauf
#   systemctl start dokuvault-agent.service         sofort melden
#   journalctl -u dokuvault-agent.service           Protokoll
#
# Adresse und Token sind beim Download eingesetzt worden. Die Datei ist so
# vertraulich wie der Token.
#
set -euo pipefail

BASE_URL="__BASE_URL__"
TOKEN="__AGENT_TOKEN__"
INTERVAL="${INTERVAL:-60}"

CONF_DIR=/etc/dokuvault
LIB_DIR=/usr/local/lib/dokuvault
STATE_DIR=/var/lib/dokuvault
UNIT=dokuvault-agent

if [ "$(id -u)" -ne 0 ]; then
  echo "Bitte als root ausfuehren." >&2
  exit 1
fi

if [ "${1:-}" = "--uninstall" ]; then
  systemctl disable --now "$UNIT.timer" 2>/dev/null || true
  rm -f "/etc/systemd/system/$UNIT.service" "/etc/systemd/system/$UNIT.timer"
  systemctl daemon-reload
  rm -rf "$CONF_DIR" "$LIB_DIR" "$STATE_DIR"
  echo "DokuVault-Agent entfernt."
  exit 0
fi

if ! [[ "$INTERVAL" =~ ^[0-9]+$ ]] || [ "$INTERVAL" -lt 5 ]; then
  echo "INTERVAL muss eine Zahl ab 5 (Minuten) sein." >&2
  exit 1
fi
command -v curl >/dev/null || { echo "curl fehlt (apt install curl)." >&2; exit 1; }
command -v pveversion >/dev/null || echo "Hinweis: pveversion nicht gefunden - ist das ein Proxmox-Host?" >&2

install -d -m 700 "$CONF_DIR" "$LIB_DIR" "$STATE_DIR"

# The token lives here, readable by root only.
umask 077
cat > "$CONF_DIR/agent.conf" <<CONF
URL=$BASE_URL
TOKEN=$TOKEN
CONF

# Fetches the current script before every run: improvements in DokuVault
# arrive without reinstalling. Quoted heredoc - nothing expanded here.
cat > "$LIB_DIR/run.sh" <<'RUN'
#!/usr/bin/env bash
set -euo pipefail
. /etc/dokuvault/agent.conf

ziel=/var/lib/dokuvault/proxmox.sh
status="$(curl -sS -o "$ziel.neu" -w '%{http_code}' \
  -H "Authorization: Bearer $TOKEN" \
  -H "User-Agent: DokuVault-Agent-Proxmox" \
  "$URL/api/agent/script/proxmox?shell=bash")" || {
  echo "DokuVault nicht erreichbar ($URL)." >&2
  exit 1
}

case "$status" in
  200) mv "$ziel.neu" "$ziel" ;;
  401) echo "Token abgelehnt (abgelaufen oder widerrufen) - in DokuVault einen neuen Token erzeugen und den Agenten neu einrichten." >&2; exit 1 ;;
  *)   echo "Unerwartete Antwort $status von $URL." >&2; exit 1 ;;
esac

exec bash "$ziel"
RUN
chmod 700 "$LIB_DIR/run.sh"

cat > "/etc/systemd/system/$UNIT.service" <<SERVICE
[Unit]
Description=DokuVault Agent (Proxmox) - meldet Host, VMs und Container
Wants=network-online.target
After=network-online.target

[Service]
Type=oneshot
ExecStart=$LIB_DIR/run.sh
TimeoutStartSec=10min
SERVICE

cat > "/etc/systemd/system/$UNIT.timer" <<TIMER
[Unit]
Description=DokuVault Agent (Proxmox) alle $INTERVAL Minuten

[Timer]
OnBootSec=2min
OnUnitActiveSec=${INTERVAL}min
RandomizedDelaySec=60

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload
systemctl enable --now "$UNIT.timer" >/dev/null

echo "DokuVault-Agent eingerichtet: meldet alle $INTERVAL Minuten an $BASE_URL."
echo "Erster Lauf ..."
if systemctl start "$UNIT.service"; then
  echo "Gemeldet. Protokoll: journalctl -u $UNIT.service"
else
  echo "Erster Lauf fehlgeschlagen - Details: journalctl -u $UNIT.service" >&2
  exit 1
fi
