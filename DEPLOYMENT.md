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

**Was nicht erfasst wird:** keine IP-Adresse, kein User-Agent, keine aufgerufenen Seiten.
Besuche werden über einen Zufallswert in der Sitzung unterschieden, der sich keiner Person
zuordnen lässt. Die Aufzeichnung liegt als JSONL unter `storage/app/demo-usage/` – eine
Datei je Monat, damit sie den stündlichen Datenbank-Reset übersteht. Alte Monate können
einfach gelöscht werden.

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
Frontend-Build laufen bei laufender Seite – das dauert ein bis zwei Minuten, und die
Seite dafür abzuschalten hieße, sie für nichts abzuschalten. Erst für Migrationen,
Cache-Neuaufbau und den Demo-Reset geht sie kurz auf 503, meist wenige Sekunden. Die
Freigabe hängt an einem `trap`, sie kommt also auch, wenn ein Schritt dazwischen
fehlschlägt.

Der Preis dafür: Zwischen `git reset` und `migrate` liefert die Seite schon den neuen
Code gegen das alte Schema aus. Bei Migrationen, die nur etwas hinzufügen – der
Normalfall hier – merkt das niemand. Wer eine Spalte umbenennt oder entfernt, sollte
den Deploy in eine ruhige Minute legen oder in zwei Schritten ausrollen.

`deploy.sh` setzt den Arbeitsstand hart auf `origin/main`. Änderungen, die direkt
auf dem Server gemacht wurden, gehen dabei verloren – das ist Absicht, damit der
Server immer dem Repository entspricht.
