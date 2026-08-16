#!/usr/bin/env bash
#
# Deployment auf dem Server. Wird von .github/workflows/deploy.yml per SSH
# aufgerufen, laesst sich aber genauso von Hand ausfuehren:
#
#   cd /pfad/zur/app && ./deploy.sh
#
# Voraussetzung: Das Verzeichnis ist ein Git-Klon des Repos und enthaelt eine
# .env. Achtung: Der Deploy setzt den Arbeitsstand hart auf origin/main -
# Aenderungen direkt auf dem Server gehen dabei verloren.

set -euo pipefail

cd "$(dirname "$0")"

# Nur ein Deploy zur Zeit, und nie gleichzeitig mit dem stuendlichen
# demo:reset (der Cronjob nimmt dieselbe Sperre, siehe DEPLOYMENT.de.md).
# Beide fassen die Datenbank an; ueberlappen sie, laeuft migrate gegen eine
# Datenbank, die gerade geleert wird.
LOCK="$(pwd)/storage/deploy.lock"
if [ -z "${DEPLOY_LOCKED:-}" ]; then
    if command -v flock >/dev/null 2>&1; then
        export DEPLOY_LOCKED=1
        exec flock --wait 900 "$LOCK" "$0" "$@"
    fi
    echo "!!! flock nicht gefunden - Deploy laeuft ohne Sperre" >&2
fi

BRANCH="${DEPLOY_BRANCH:-main}"

# Demo-Instanz? Dann werden Dev-Abhaengigkeiten gebraucht (Faker fuer die
# Demo-Daten) und die Datenbank wird nach dem Deploy zurueckgesetzt.
if grep -qE '^DEMO_MODE=true' .env 2>/dev/null; then
    IS_DEMO=1
else
    IS_DEMO=0
fi

echo "==> Stand von origin/${BRANCH} holen"
git fetch --prune origin
git reset --hard "origin/${BRANCH}"

echo "==> PHP-Abhaengigkeiten"
if [ "$IS_DEMO" -eq 1 ]; then
    # ohne --no-dev: die Demo-Daten brauchen fakerphp/faker
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

echo "==> Frontend bauen"
npm ci
npm run build

# Ab hier wird die Seite abgeschaltet - aber erst ab hier. Composer und der
# Frontend-Build dauern ein bis zwei Minuten und brauchen keine Auszeit;
# frueher lag die Demo genau so lange auf 503. Was jetzt noch kommt, sind
# Sekunden: Migrationen und das Zuruecksetzen fassen die Datenbank an, und
# waehrend die Caches neu geschrieben werden, sind sie kurz unvollstaendig.
echo "==> Wartungsmodus an"
php artisan down --retry=15 || true
# Auch bei einem Fehler zwischendurch die Seite wieder freigeben.
trap 'php artisan up || true' EXIT

echo "==> Migrationen"
# DomPDF legt seine Schriftenliste hier ab und braucht Schreibrechte -
# fehlte der Ordner, endete "PDF erstellen" in einer Fehlerseite.
mkdir -p storage/fonts

php artisan migrate --force

echo "==> Caches neu aufbauen"
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "$IS_DEMO" -eq 1 ]; then
    echo "==> Demo-Datenbank zuruecksetzen"
    php artisan demo:reset
fi

echo "==> Fertig"
