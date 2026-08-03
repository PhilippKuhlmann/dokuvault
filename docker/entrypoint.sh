#!/bin/sh
#
# Startet DokuVault im Container: wartet auf die Datenbank, migriert, legt
# beim allerersten Start die Demo-Daten an und startet den Server.

set -e
cd /app

if [ ! -f .env ]; then
    cp .env.example .env
fi

# Der Schluessel steht nicht im Image - sonst haetten alle denselben. Die
# Umgebungsvariable aus dem Compose-Datei hat Vorrang; ohne sie wird einmal
# einer erzeugt.
if [ -z "${APP_KEY}" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

# Beim Bauen uebersprungen, weil dafuer eine .env noetig ist: Das Paketverzeichnis
# entsteht jetzt, wo eine da ist.
php artisan package:discover --no-interaction >/dev/null

echo "==> Warte auf die Datenbank"
i=0
until php -r '
    $h = getenv("DB_HOST") ?: "127.0.0.1";
    $p = getenv("DB_PORT") ?: "3306";
    new PDO("mysql:host=$h;port=$p", getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
' >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "!!! Datenbank nach 60 Versuchen nicht erreichbar" >&2
        exit 1
    fi
    sleep 2
done

# Ist die Datenbank noch leer? Dann - und nur dann - werden die Demo-Daten
# angelegt. Bei jedem Start zu saeen wuerde sie bei jedem Neustart verdoppeln.
if php -r '
    $h = getenv("DB_HOST") ?: "127.0.0.1";
    $p = getenv("DB_PORT") ?: "3306";
    $d = getenv("DB_DATABASE");
    $v = new PDO("mysql:host=$h;port=$p;dbname=$d", getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    exit($v->query("SHOW TABLES LIKE \"users\"")->fetch() ? 1 : 0);
'; then
    FRISCH=1
else
    FRISCH=0
fi

echo "==> Migrationen"
php artisan migrate --force --no-interaction

if [ "$FRISCH" = "1" ]; then
    echo "==> Erststart: Demo-Daten anlegen"
    php artisan db:seed --force --no-interaction
fi

php artisan storage:link --no-interaction 2>/dev/null || true

echo "==> DokuVault laeuft auf http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
