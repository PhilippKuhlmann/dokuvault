#!/usr/bin/env bash
#
# DokuVault-Agent fuer Linux: Proxmox VE oder Linux-Server (Debian/Ubuntu)
#
# Richtet einen systemd-Timer ein, der alle 5 Minuten bei DokuVault fragt, ob
# ein Lauf faellig ist (Intervall und "Jetzt melden" auf der Agent-Seite), und
# dann das aktuelle Script von DokuVault holt und ausfuehrt - nur lesend, wie
# das Script von Hand. Welches Script, ist beim Download eingesetzt worden
# (KIND unten). Als root ausfuehren:
#
#   bash dokuvault-agent-<art>.sh                   einrichten (meldet stuendlich)
#   INTERVAL=15 bash dokuvault-agent-<art>.sh       Intervall vorschlagen (Minuten, mind. 5; danach in DokuVault)
#   bash dokuvault-agent-<art>.sh --uninstall       wieder entfernen
#
# Danach:
#   dokuvault-agent-uninstall                       entfernen, ohne diese Datei
#   systemctl list-timers dokuvault-agent.timer     naechste Anfrage
#   systemctl start dokuvault-agent.service         sofort anfragen
#   journalctl -u dokuvault-agent.service           Protokoll
#
# Adresse und Token sind beim Download eingesetzt worden. Die Datei ist so
# vertraulich wie der Token.
#
set -euo pipefail

BASE_URL="__BASE_URL__"
TOKEN="__AGENT_TOKEN__"
# Set by DokuVault from the content of this file - run.sh compares it before
# every run and applies a newer installer with --update.
AGENT_VERSION="__AGENT_VERSION__"
# proxmox | linux - which agent this is (config custom.dienste). The role is
# the script it runs: proxmox.sh or linux-server.sh.
KIND="__AGENT_KIND__"
case "$KIND" in
  proxmox) ROLE=proxmox ;;
  linux)   ROLE=linux-server ;;
  *) echo "Unbekannte Agent-Art $KIND." >&2; exit 1 ;;
esac

CONF_DIR=/etc/dokuvault
LIB_DIR=/usr/local/lib/dokuvault
STATE_DIR=/var/lib/dokuvault
UNIT=dokuvault-agent

# --update: called by run.sh inside the running service. Keeps the interval
# and does not start the service again (it is the one running this).
UPDATE=""
if [ "${1:-}" = "--update" ]; then
  UPDATE=1
fi
if [ -z "${INTERVAL:-}" ] && [ -f "$CONF_DIR/agent.conf" ]; then
  INTERVAL="$(sed -n 's/^INTERVAL=//p' "$CONF_DIR/agent.conf")"
fi
INTERVAL="${INTERVAL:-60}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Bitte als root ausfuehren." >&2
  exit 1
fi

if [ "${1:-}" = "--uninstall" ]; then
  systemctl disable --now "$UNIT.timer" 2>/dev/null || true
  rm -f "/etc/systemd/system/$UNIT.service" "/etc/systemd/system/$UNIT.timer"
  systemctl daemon-reload
  rm -rf "$CONF_DIR" "$LIB_DIR" "$STATE_DIR"
  rm -f /usr/local/sbin/dokuvault-agent-uninstall
  echo "DokuVault-Agent entfernt."
  exit 0
fi

if ! [[ "$INTERVAL" =~ ^[0-9]+$ ]] || [ "$INTERVAL" -lt 5 ]; then
  echo "INTERVAL muss eine Zahl ab 5 (Minuten) sein." >&2
  exit 1
fi
command -v curl >/dev/null || { echo "curl fehlt (apt install curl)." >&2; exit 1; }
if [ "$KIND" = proxmox ]; then
  command -v pveversion >/dev/null || echo "Hinweis: pveversion nicht gefunden - ist das ein Proxmox-Host?" >&2
elif [ -z "$UPDATE" ] && command -v pveversion >/dev/null; then
  # Both would share /etc/dokuvault and the timer; the Proxmox agent
  # reports the host as server anyway, with its guests.
  echo "Das ist ein Proxmox-Host - bitte den Agenten fuer Proxmox verwenden." >&2
  exit 1
fi

install -d -m 700 "$CONF_DIR" "$LIB_DIR" "$STATE_DIR"

# The token lives here, readable by root only.
umask 077
cat > "$CONF_DIR/agent.conf.neu" <<CONF
URL=$BASE_URL
TOKEN=$TOKEN
INTERVAL=$INTERVAL
KIND=$KIND
ROLE=$ROLE
CONF
mv "$CONF_DIR/agent.conf.neu" "$CONF_DIR/agent.conf"
printf '%s\n' "$AGENT_VERSION" > "$LIB_DIR/version"

# Before every run: first a newer agent (this installer, applied with
# --update), then the current script - improvements in DokuVault arrive
# without anyone reinstalling. Written to a new file and moved into place:
# the running run.sh keeps reading its old copy undisturbed.
# Quoted heredoc - nothing expanded here.
cat > "$LIB_DIR/run.sh.neu" <<'RUN'
#!/usr/bin/env bash
set -euo pipefail
. /etc/dokuvault/agent.conf
# Installed before KIND existed: those were all Proxmox agents.
KIND="${KIND:-proxmox}"
ROLE="${ROLE:-proxmox}"

if [ -z "${DOKUVAULT_UPDATED:-}" ]; then
  installer=/var/lib/dokuvault/installer.neu
  status="$(curl -sS -o "$installer" -w '%{http_code}' \
    -H "Authorization: Bearer $TOKEN" \
    -H "User-Agent: DokuVault-Agent-$KIND" \
    "$URL/api/agent/update/$KIND?version=$(cat /usr/local/lib/dokuvault/version 2>/dev/null)")" || status=000

  case "$status" in
    200)
      echo "Neue Agent-Version - wird eingespielt."
      INTERVAL="$INTERVAL" bash "$installer" --update
      rm -f "$installer"
      # Run again with the new run.sh, without asking a second time.
      DOKUVAULT_UPDATED=1 exec /usr/local/lib/dokuvault/run.sh
      ;;
    204) ;;
    401) echo "Token abgelehnt (abgelaufen oder widerrufen) - in DokuVault einen neuen Token erzeugen und den Agenten neu einrichten." >&2; exit 1 ;;
    *)   echo "Update-Pruefung nicht moeglich ($status) - weiter mit der installierten Version." >&2 ;;
  esac
  rm -f "$installer"
fi

# Report in and ask whether to run - the agent can be switched off on the
# agent page in DokuVault. An older DokuVault without checkin: just run.
antwort=/var/lib/dokuvault/checkin.json
status="$(curl -sS -o "$antwort" -w '%{http_code}' -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "User-Agent: DokuVault-Agent-$KIND" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"kind\":\"$KIND\",\"machine_id\":\"$(cat /etc/machine-id 2>/dev/null)\",\"hostname\":\"$(hostname)\",\"version\":\"$(cat /usr/local/lib/dokuvault/version 2>/dev/null)\",\"detected\":[\"$ROLE\"],\"interval\":${INTERVAL:-60}}" \
  "$URL/api/agent/checkin")" || status=000

# The timer fires every five minutes; whether to run now is DokuVault's
# call (interval and "Jetzt melden" on the agent page). Without an answer
# (unreachable, older DokuVault) the local INTERVAL decides.
letzter=/var/lib/dokuvault/last-run
lokal_faellig() {
  [ ! -f "$letzter" ] || [ $(( $(date +%s) - $(cat "$letzter" 2>/dev/null || echo 0) )) -ge $(( ${INTERVAL:-60} * 60 )) ]
}
case "$status" in
  200)
    if ! grep -q "\"$ROLE\"" "$antwort"; then
      echo "Keine Aufgaben zugewiesen (in DokuVault unter Agenten anhaken)."
      rm -f "$antwort"
      exit 0
    fi
    if grep -q '"run":false' "$antwort"; then
      rm -f "$antwort"
      exit 0
    fi
    if ! grep -q '"run":true' "$antwort" && ! lokal_faellig; then
      rm -f "$antwort"
      exit 0
    fi
    ;;
  401) echo "Token abgelehnt (abgelaufen oder widerrufen) - in DokuVault einen neuen Token erzeugen und den Agenten neu einrichten." >&2; exit 1 ;;
  *)
    if ! lokal_faellig; then
      rm -f "$antwort"
      exit 0
    fi
    ;;
esac
rm -f "$antwort"
date +%s > "$letzter"

ziel="/var/lib/dokuvault/$ROLE.sh"
status="$(curl -sS -o "$ziel.neu" -w '%{http_code}' \
  -H "Authorization: Bearer $TOKEN" \
  -H "User-Agent: DokuVault-Agent-$KIND" \
  "$URL/api/agent/script/$ROLE?shell=bash")" || {
  echo "DokuVault nicht erreichbar ($URL)." >&2
  exit 1
}

case "$status" in
  200) mv "$ziel.neu" "$ziel" ;;
  401) echo "Token abgelehnt (abgelaufen oder widerrufen) - in DokuVault einen neuen Token erzeugen und den Agenten neu einrichten." >&2; exit 1 ;;
  *)   echo "Unerwartete Antwort $status von $URL." >&2; exit 1 ;;
esac

# Run, then tell DokuVault how it went (agent page, dashboard). The output
# is still printed for journalctl.
if ausgabe="$(bash "$ziel" 2>&1)"; then ok=true; rc=0; else rc=$?; ok=false; fi
printf '%s\n' "$ausgabe"

# Last 4000 characters as a JSON string: backslash and quotes escaped,
# control characters dropped, line breaks as \n.
nachricht="$(printf '%s' "$ausgabe" | tail -c 4000 | tr -d '\000-\010\013\014\016-\037\r' \
  | sed 's/\\/\\\\/g; s/"/\\"/g; s/\t/ /g' | awk 'NR > 1 { printf "\\n" } { printf "%s", $0 }')"
curl -sS -o /dev/null -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "User-Agent: DokuVault-Agent-$KIND" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"machine_id\":\"$(cat /etc/machine-id 2>/dev/null)\",\"results\":[{\"role\":\"$ROLE\",\"ok\":$ok,\"message\":\"$nachricht\"}]}" \
  "$URL/api/agent/report" || echo "Ergebnis nicht gemeldet." >&2

exit "$rc"
RUN
chmod 700 "$LIB_DIR/run.sh.neu"
mv "$LIB_DIR/run.sh.neu" "$LIB_DIR/run.sh"

# Uninstall without keeping this download around (it holds the token):
# dokuvault-agent-uninstall removes everything, itself included.
cat > /usr/local/sbin/dokuvault-agent-uninstall <<'UNINSTALL'
#!/usr/bin/env bash
set -euo pipefail
if [ "$(id -u)" -ne 0 ]; then
  echo "Bitte als root ausfuehren." >&2
  exit 1
fi
systemctl disable --now dokuvault-agent.timer 2>/dev/null || true
rm -f /etc/systemd/system/dokuvault-agent.service /etc/systemd/system/dokuvault-agent.timer
systemctl daemon-reload
rm -rf /etc/dokuvault /usr/local/lib/dokuvault /var/lib/dokuvault
rm -f /usr/local/sbin/dokuvault-agent-uninstall
echo "DokuVault-Agent entfernt."
UNINSTALL
chmod 700 /usr/local/sbin/dokuvault-agent-uninstall

cat > "/etc/systemd/system/$UNIT.service" <<SERVICE
[Unit]
Description=DokuVault Agent ($KIND) - meldet an DokuVault
Wants=network-online.target
After=network-online.target

[Service]
Type=oneshot
ExecStart=$LIB_DIR/run.sh
TimeoutStartSec=10min
SERVICE

cat > "/etc/systemd/system/$UNIT.timer" <<TIMER
[Unit]
Description=DokuVault Agent ($KIND) - fragt alle 5 Minuten, ob ein Lauf faellig ist

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
RandomizedDelaySec=60

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload

if [ -n "$UPDATE" ]; then
  # Running inside the service: no start here, run.sh continues itself.
  systemctl enable "$UNIT.timer" >/dev/null
  echo "DokuVault-Agent aktualisiert auf $AGENT_VERSION."
  exit 0
fi

systemctl enable --now "$UNIT.timer" >/dev/null

echo "DokuVault-Agent eingerichtet: meldet an $BASE_URL - Intervall und \"Jetzt melden\" in DokuVault unter Agenten."
echo "Erster Lauf ..."
if systemctl start "$UNIT.service"; then
  echo "Gemeldet. Protokoll: journalctl -u $UNIT.service"
  echo "Entfernen: dokuvault-agent-uninstall"
else
  echo "Erster Lauf fehlgeschlagen - Details: journalctl -u $UNIT.service" >&2
  exit 1
fi
