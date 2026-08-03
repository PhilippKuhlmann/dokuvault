# Deployment

Bei jedem Push auf `main` läuft zuerst die Test-Workflow. Nur wenn sie grün ist,
startet `deploy` und führt auf dem Server [`deploy.sh`](deploy.sh) aus. Ein roter
Build erreicht den Server nicht.

## Einmalige Einrichtung

### 1. Auf dem Server

```bash
# Repo klonen (ins Verzeichnis, das der Webserver ausliefert)
git clone https://github.com/PhilippKuhlmann/dokuvault.git /var/www/dokuvault
cd /var/www/dokuvault
cp .env.example .env
```

`.env` anpassen: `APP_URL`, DB-Zugang, `APP_ENV=production`.

**Reihenfolge beachten:** `vendor/` liegt nicht im Repo, deshalb muss `composer install`
vor dem ersten Artisan-Befehl laufen – sonst schlägt `key:generate` fehl, und zwar
still, wenn man die Ausgabe nicht liest.

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

```bash
php artisan key:generate
```

Rechte für den Deploy-Benutzer setzen, damit `deploy.sh` schreiben darf, und
`storage/` sowie `bootstrap/cache/` für den Webserver-Benutzer beschreibbar machen.

```bash
chmod +x deploy.sh
```

### 2. SSH-Schlüssel für GitHub

Auf dem **eigenen Rechner** ein Schlüsselpaar erzeugen, das nur für den Deploy da ist:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/dokuvault_deploy -C "github-deploy" -N ""
```

Den **öffentlichen** Teil auf dem Server in die `authorized_keys` des Deploy-Benutzers
eintragen.

Dann die Fingerabdrücke der Host-Schlüssel holen – **auf dem Server**, aus seinen
eigenen Schlüsseldateien:

```bash
for f in /etc/ssh/ssh_host_*_key.pub; do ssh-keygen -lf "$f"; done
```

Nicht per `ssh-keyscan` vom eigenen Rechner: Antwortet auf dem abgefragten Port ein
Gateway oder ein anderer Dienst, bekommt man dessen Schlüssel statt der des Servers –
und der Deploy scheitert später mit `Host key verification failed`, ohne den Grund zu
nennen. Aus den Schlüsseldateien gelesen kann das nicht passieren.

### 3. Secrets in GitHub hinterlegen

Repository → Settings → Secrets and variables → Actions → Reiter **Secrets** →
**New repository secret**. Environments werden nicht gebraucht.

| Secret | Inhalt |
| --- | --- |
| `DEPLOY_HOST` | `doku.dokuvault.de` |
| `DEPLOY_USER` | Benutzer für den SSH-Zugang |
| `DEPLOY_PATH` | `/var/www/dokuvault` |
| `DEPLOY_SSH_KEY` | Inhalt von `~/.ssh/dokuvault_deploy` (der **private** Teil) |
| `DEPLOY_KNOWN_HOSTS` | Ausgabe des Befehls oben – drei kurze `SHA256:`-Zeilen |
| `DEPLOY_URL` | `https://doku.dokuvault.de` |
| `DEPLOY_PORT` | nur nötig, wenn SSH nicht auf 22 läuft |

`DEPLOY_KNOWN_HOSTS` ist kein Beiwerk: Ohne diese Angabe müsste der Deploy die
Host-Prüfung abschalten und wäre gegen einen untergeschobenen Server offen. Der
Workflow holt die Schlüssel zur Laufzeit vom Server und vergleicht ihre
Fingerabdrücke mit diesem Secret.

Ganze `known_hosts`-Zeilen werden weiterhin akzeptiert. Die kurze Form ist nur
weniger fehleranfällig – das lange Base64 bricht beim Kopieren leicht um, und ein
so beschädigter Wert fällt erst beim Deploy auf.

## Aktualisieren

Wer den Deploy aus dem vorigen Abschnitt eingerichtet hat, braucht hier nichts: Ein Push
auf `main` erledigt das. Für eine Installation ohne GitHub-Anbindung ist es dieselbe
Reihenfolge von Hand.

**Vorher sichern.** Migrationen sind nicht rückwärts gedacht; ein Backup ist der einzige
verlässliche Weg zurück:

```bash
cd /var/www/dokuvault && php artisan backup:run
```

Dann der Ablauf. Die Seite geht erst kurz vor den Migrationen aus – `composer` und der
Frontend-Build brauchen keine Auszeit:

```bash
cd /var/www/dokuvault && git pull
```

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader && npm ci && npm run build
```

```bash
php artisan down --retry=15 && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan up
```

Alternativ nimmt [`deploy.sh`](deploy.sh) genau diese Schritte ab – es setzt den Stand
allerdings hart auf `origin/main` und verwirft dabei alles, was auf dem Server geändert
wurde.

**Was beim Versionssprung zu beachten ist:** Neue Berechtigungen kommen als Migration,
bestehende Rollen bekommen sie also automatisch. Das Changelog nennt zu jedem Eintrag,
was sich für bestehende Installationen ändert – ein Blick dorthin vor dem Update spart
Rückfragen. Bleibt nach `git pull` etwas unklar, hilft `php artisan migrate:status`: Es
listet, was noch aussteht, ohne etwas zu tun.

## Demo-Instanz

Für eine öffentliche Demo zusätzlich in die `.env`:

```
DEMO_MODE=true
```

Das bewirkt zweierlei:

- In der Oberfläche erscheint ein Hinweis, dass alles ausprobiert werden darf und
  die Daten stündlich zurückgesetzt werden – auf der Login-Seite mit den Zugangsdaten.
- `php artisan demo:reset` wird entsperrt. **Ohne `DEMO_MODE=true` verweigert der
  Befehl den Dienst**, denn er löscht die komplette Datenbank.

Der Deploy erkennt den Demo-Modus an der `.env` und installiert dann **mit**
Dev-Abhängigkeiten – die Demo-Daten brauchen `fakerphp/faker`. Nach dem Deploy setzt
er die Datenbank einmal zurück.

Für den stündlichen Reset einen Cronjob des Deploy-Benutzers anlegen:

```
0 * * * * cd /var/www/dokuvault && flock -n storage/deploy.lock php artisan demo:reset >> storage/logs/demo-reset.log 2>&1
```

Das `flock -n` ist wichtig: `deploy.sh` nimmt dieselbe Sperre. Ohne sie kann der Reset
mitten in einen laufenden Deploy fallen und `migrate` gegen eine Datenbank laufen, die
gerade geleert wird. Trifft der Cronjob auf einen Deploy, überspringt er den Reset –
richtig so, denn der Deploy setzt die Datenbank selbst zurück.

### Nutzung auswerten

Bei aktivem Demo-Modus hält die App fest, wann die Demo besucht wurde und mit welcher
Rolle. Auswertung auf dem Server:

```bash
cd /var/www/dokuvault && php artisan demo:stats
```

Ausgegeben werden Besuche und Seitenaufrufe gesamt, Besuche je Tag als Balken, die
Verteilung über die Tageszeit und die genutzten Rollen. Einschränken lässt sich das mit
`--month=2026-08` und `--days=30`.

Ein „Besuch" ist eine Sitzung, keine Person: Wer nach Ablauf der Sitzung wiederkommt,
zählt erneut.

Ausgegeben wird außerdem, aus welchen Netzen die Besuche kamen.

**Was nicht erfasst wird:** kein User-Agent, keine aufgerufenen Seiten. Besuche werden über
einen Zufallswert in der Sitzung unterschieden, der sich keiner Person zuordnen lässt. Die
Aufzeichnung liegt als JSONL unter `storage/app/demo-usage/` – eine Datei je Monat, damit sie
den stündlichen Datenbank-Reset übersteht. Alte Monate können einfach gelöscht werden.

**Herkunft.** Wie viel von der Adresse gespeichert wird, steht in `config/custom.php` unter
`demo_ip_logging`, umschaltbar per `DEMO_IP_LOGGING` in der `.env`:

| Wert | |
| --- | --- |
| `aus` | keine Adresse, die Aufzeichnung bleibt ohne Personenbezug |
| `anonym` | **Standard** – gekürzt auf /24 (IPv4) bzw. /48 (IPv6) |
| `voll` | vollständige Adresse |

`anonym` beantwortet die Frage nach der Herkunft genauso gut: Eine GeoIP-Abfrage liefert aus
`91.65.42.0` dasselbe Land wie aus der vollen Adresse. Die **vollständige** IP ist dagegen ein
personenbezogenes Datum nach DSGVO – wer `voll` setzt, braucht auf der Demo eine
Datenschutzerklärung, die die Speicherung, den Zweck und eine Löschfrist nennt. Das ist kein
Grund, es nicht zu tun, aber einer, es bewusst zu tun.

Wichtig für die Aussagekraft: Steht ein Reverse-Proxy vor der App – etwa ein Nginx Proxy
Manager –, sieht sie dessen Adresse statt der des Besuchers. Alle Besuche kämen dann scheinbar
aus einem einzigen Netz. `demo:stats` weist von sich aus darauf hin, sobald aufgezeichnete
Adressen nicht öffentlich sind.

Der Proxy gehört deshalb in die `.env`:

```
TRUSTED_PROXIES=172.18.0.0/16
```

Mehrere Einträge mit Komma trennen, CIDR ist erlaubt. Welche Adresse einzutragen ist, verrät
die Aufzeichnung selbst – solange der Proxy nicht vertraut wird, steht dort seine:

```bash
cd /var/www/dokuvault && tail -n 1 storage/app/demo-usage/$(date +%Y-%m).jsonl
```

`*` vertraut jedem Absender. Das ist bequem, hebt aber die Prüfung auf: Wer die App direkt
erreicht – am Proxy vorbei –, kann sich per `X-Forwarded-For` eine beliebige Herkunft geben.
Vertretbar nur, wenn wirklich ausschließlich der Proxy an die App kommt.

### Was auf einer Demo bewusst offen ist

Die Zugangsdaten stehen im README und im Hinweis-Banner. Jeder Besucher ist damit
Administrator und kann fast alles ändern. Der stündliche Reset räumt hinterher auf.

**Ausgenommen sind die vier Zugänge selbst**: `admin`, `techniker`, `kunde-rw` und
`kunde-r` sind bei aktivem Demo-Modus vollständig gesperrt – kein Löschen, keine Änderung,
auch nicht am Namen. Sonst wären alle übrigen Besucher bis zum nächsten Reset ausgesperrt.
Dass auch der **Benutzername** feststeht, ist kein Übereifer: Der Schutz erkennt die Zugänge
daran, eine Umbenennung würde ihn also aufheben. Welche Benutzernamen geschützt sind, steht
in `config/custom.php` unter `demo_protected_users`. Selbst angelegte Benutzer bleiben voll
bearbeitbar.

Eine Demo sollte deshalb **keine** echten Daten enthalten und auf einem Server
stehen, auf dem sonst nichts läuft.

## Von Hand deployen

Entweder in GitHub unter **Actions → deploy → Run workflow**, oder direkt auf dem Server:

```bash
cd /var/www/dokuvault && ./deploy.sh
```

Das Skript bricht beim ersten Fehler ab und liefert Exitcode 1, damit die GitHub-Action
rot wird statt einen halben Deploy als Erfolg zu melden.

**Der Wartungsmodus deckt nur das Ende ab.** Code holen, `composer install` und der
Frontend-Build laufen bei laufender Seite – sie brauchen keine Auszeit. Erst für
Migrationen, Cache-Neuaufbau und den Demo-Reset geht die Seite auf 503. Gemessen auf
der Demo: **10 Sekunden**, vorher 17. Der Abstand wächst, sobald sich Abhängigkeiten
ändern: `composer install` und `npm ci` sind nur dann Sekundensache, wenn die
Lock-Dateien gleich geblieben sind – sonst dauern sie Minuten, und die lagen früher
komplett im Wartungsfenster. Die Freigabe hängt an einem `trap`, sie kommt also auch,
wenn ein Schritt dazwischen fehlschlägt.

Der Preis dafür: Zwischen `git reset` und `migrate` liefert die Seite schon den neuen
Code gegen das alte Schema aus. Bei Migrationen, die nur etwas hinzufügen – der
Normalfall hier – merkt das niemand. Wer eine Spalte umbenennt oder entfernt, sollte
den Deploy in eine ruhige Minute legen oder in zwei Schritten ausrollen.

`deploy.sh` setzt den Arbeitsstand hart auf `origin/main`. Änderungen, die direkt
auf dem Server gemacht wurden, gehen dabei verloren – das ist Absicht, damit der
Server immer dem Repository entspricht.
