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
0 * * * * cd /var/www/dokuvault && php artisan demo:reset >> storage/logs/demo-reset.log 2>&1
```

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

**Was nicht erfasst wird:** keine IP-Adresse, kein User-Agent, keine aufgerufenen Seiten.
Besuche werden über einen Zufallswert in der Sitzung unterschieden, der sich keiner Person
zuordnen lässt. Die Aufzeichnung liegt als JSONL unter `storage/app/demo-usage/` – eine
Datei je Monat, damit sie den stündlichen Datenbank-Reset übersteht. Alte Monate können
einfach gelöscht werden.

### Was auf einer Demo bewusst offen ist

Die Zugangsdaten stehen im README und im Hinweis-Banner. Jeder Besucher ist damit
Administrator und kann alles ändern – auch das Admin-Passwort. Genau dagegen hilft
der stündliche Reset: Nach spätestens einer Stunde ist der Ausgangszustand zurück.

Eine Demo sollte deshalb **keine** echten Daten enthalten und auf einem Server
stehen, auf dem sonst nichts läuft.

## Von Hand deployen

Entweder in GitHub unter **Actions → deploy → Run workflow**, oder direkt auf dem Server:

```bash
cd /var/www/dokuvault && ./deploy.sh
```

Das Skript schaltet zu Beginn den Wartungsmodus ein und gibt die Seite am Ende wieder
frei – auch wenn ein Schritt dazwischen fehlschlägt. Es bricht beim ersten Fehler ab
und liefert Exitcode 1, damit die GitHub-Action rot wird statt einen halben Deploy
als Erfolg zu melden.

`deploy.sh` setzt den Arbeitsstand hart auf `origin/main`. Änderungen, die direkt
auf dem Server gemacht wurden, gehen dabei verloren – das ist Absicht, damit der
Server immer dem Repository entspricht.
