#!/bin/sh
#
# Startet DokuVault im Container: wartet auf die Datenbank, migriert, legt
# beim allerersten Start die Demo-Daten an und startet den Server.

set -e
cd /app

if [ ! -f .env ]; then
    cp .env.example .env
fi

# Die Werte aus der Umgebung in die .env schreiben, statt sich auf die
# Umgebung zu verlassen: "php artisan serve" setzt im Serverprozess jede
# Variable auf false, die nicht in ServeCommand::$passthroughVariables steht -
# und DB_* steht dort nicht. Migrationen liefen damit gegen die richtige
# Datenbank, die ausgelieferte Anwendung aber gegen die aus .env.example.
setze_env() {
    schluessel="$1"
    eval "wert=\${$schluessel-}"
    [ -z "$wert" ] && return 0
    grep -v "^${schluessel}=" .env > .env.neu 2>/dev/null || true
    printf '%s=%s\n' "$schluessel" "$wert" >> .env.neu
    mv .env.neu .env
}

for schluessel in APP_ENV APP_DEBUG APP_URL APP_KEY LOG_CHANNEL \
                  DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
    setze_env "$schluessel"
done

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
