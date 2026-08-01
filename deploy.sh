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

BRANCH="${DEPLOY_BRANCH:-main}"

# Demo-Instanz? Dann werden Dev-Abhaengigkeiten gebraucht (Faker fuer die
# Demo-Daten) und die Datenbank wird nach dem Deploy zurueckgesetzt.
if grep -qE '^DEMO_MODE=true' .env 2>/dev/null; then
    IS_DEMO=1
else
    IS_DEMO=0
fi

echo "==> Wartungsmodus an"
php artisan down --retry=15 || true
# Auch bei einem Fehler zwischendurch die Seite wieder freigeben.
trap 'php artisan up || true' EXIT

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

echo "==> Migrationen"
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
