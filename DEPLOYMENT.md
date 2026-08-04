[Deutsch](DEPLOYMENT.de.md) · **English**

# Deployment

On every push to `main` the test workflow runs first. Only if it passes does `deploy` start and run
[`deploy.sh`](deploy.sh) on the server. A red build never reaches the server.

## One-time setup

### 1. On the server

```bash
# Clone the repo into the directory the web server serves
git clone https://github.com/PhilippKuhlmann/dokuvault.git /var/www/dokuvault
cd /var/www/dokuvault
cp .env.example .env
```

Adjust `.env`: `APP_URL`, database credentials, `APP_ENV=production`.

**Mind the order:** `vendor/` is not in the repo, so `composer install` has to run before the first
artisan command — otherwise `key:generate` fails, and it fails quietly if you do not read the output.

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

```bash
php artisan key:generate
```

Give the deploy user write permissions so `deploy.sh` can work, and make `storage/` and
`bootstrap/cache/` writable for the web server user.

```bash
chmod +x deploy.sh
```

### 2. SSH key for GitHub

On **your own machine**, create a key pair used only for deploying:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/dokuvault_deploy -C "github-deploy" -N ""
```

Add the **public** part to the `authorized_keys` of the deploy user on the server.

Then fetch the fingerprints of the host keys — **on the server**, from its own key files:

```bash
for f in /etc/ssh/ssh_host_*_key.pub; do ssh-keygen -lf "$f"; done
```

Not via `ssh-keyscan` from your own machine: if a gateway or some other service answers on the port
you query, you get its key instead of the server's — and the deploy later fails with
`Host key verification failed` without saying why. Read from the key files, that cannot happen.

### 3. Store the secrets in GitHub

Repository → Settings → Secrets and variables → Actions → tab **Secrets** →
**New repository secret**. Environments are not needed.

| Secret | Contents |
| --- | --- |
| `DEPLOY_HOST` | `doku.dokuvault.de` |
| `DEPLOY_USER` | user for the SSH access |
| `DEPLOY_PATH` | `/var/www/dokuvault` |
| `DEPLOY_SSH_KEY` | contents of `~/.ssh/dokuvault_deploy` (the **private** part) |
| `DEPLOY_KNOWN_HOSTS` | output of the command above — three short `SHA256:` lines |
| `DEPLOY_URL` | `https://doku.dokuvault.de` |
| `DEPLOY_PORT` | only needed if SSH does not run on 22 |

`DEPLOY_KNOWN_HOSTS` is not decoration: without it the deploy would have to switch off host
verification and would be open to a substituted server. The workflow fetches the keys from the server
at runtime and compares their fingerprints against this secret.

Full `known_hosts` lines are still accepted. The short form is simply less error-prone — the long
base64 wraps easily when copied, and a value damaged that way only shows up during the deploy.

## Updating

If you set up the deploy from the previous section, there is nothing to do here: a push to `main`
handles it. For an installation without the GitHub connection, it is the same order by hand.

**Back up first.** Migrations are not built to run backwards; a backup is the only reliable way back:

```bash
cd /var/www/dokuvault && php artisan backup:run
```

Then the sequence. The site only goes down shortly before the migrations — `composer` and the
frontend build need no downtime:

```bash
cd /var/www/dokuvault && git pull
```

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader && npm ci && npm run build
```

```bash
php artisan down --retry=15 && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan up
```

Alternatively [`deploy.sh`](deploy.sh) takes exactly these steps off your hands — but it resets the
working tree hard to `origin/main` and discards anything changed on the server.

**What to watch for on a version jump:** new permissions arrive as a migration, so existing roles
receive them automatically. The changelog says for every entry what changes for existing
installations — a look there before updating saves questions. If something is unclear after
`git pull`, `php artisan migrate:status` lists what is still pending without doing anything.

## Demo instance

For a public demo, add this to `.env`:

```
DEMO_MODE=true
```

That does two things:

- The interface shows a notice that everything may be tried out and that the data is reset every
  hour — on the sign-in page including the credentials.
- `php artisan demo:reset` is unlocked. **Without `DEMO_MODE=true` the command refuses to run**,
  because it deletes the entire database.

The deploy detects demo mode from `.env` and then installs **with** dev dependencies — the demo data
needs `fakerphp/faker`. After the deploy it resets the database once.

For the hourly reset, create a cron job for the deploy user:

```
0 * * * * cd /var/www/dokuvault && flock -n storage/deploy.lock php artisan demo:reset >> storage/logs/demo-reset.log 2>&1
```

The `flock -n` matters: `deploy.sh` takes the same lock. Without it the reset can fall into a running
deploy and `migrate` runs against a database that is being emptied. If the cron job meets a deploy it
skips the reset — which is right, because the deploy resets the database itself.

### Measuring usage

With demo mode active the app records when the demo was visited and with which role. Evaluate on the
server:

```bash
cd /var/www/dokuvault && php artisan demo:stats
```

It prints total visits and page views, visits per day as bars, the distribution over the day and the
roles used. Narrow it down with `--month=2026-08` and `--days=30`.

A “visit” is a session, not a person: whoever returns after the session has expired counts again.

It also prints which networks the visits came from.

**What is not recorded:** no user agent, no visited pages. Visits are distinguished by a random value
in the session that cannot be traced to a person. The recording is JSONL under
`storage/app/demo-usage/` — one file per month so it survives the hourly database reset. Old months
can simply be deleted.

**Origin.** How much of the address is stored is set in `config/custom.php` under `demo_ip_logging`,
switchable via `DEMO_IP_LOGGING` in `.env`:

| Value | |
| --- | --- |
| `aus` | no address; the recording stays free of personal data |
| `anonym` | **default** — truncated to /24 (IPv4) or /48 (IPv6) |
| `voll` | full address |

`anonym` answers the question about origin just as well: a GeoIP lookup returns the same country for
`91.65.42.0` as for the full address. The **full** IP, by contrast, is personal data under the GDPR —
whoever sets `voll` needs a privacy notice on the demo naming the storage, the purpose and a
retention period. That is not a reason against it, but a reason to do it deliberately.

Important for the value of the numbers: if a reverse proxy sits in front of the app — a Nginx Proxy
Manager, say — the app sees its address instead of the visitor's. All visits would then appear to
come from a single network. `demo:stats` points this out by itself as soon as recorded addresses are
not public.

The proxy therefore belongs in `.env`:

```
TRUSTED_PROXIES=172.18.0.0/16
```

Separate several entries with commas; CIDR is allowed. Which address to enter is revealed by the
recording itself — as long as the proxy is not trusted, its address is what ends up there:

```bash
cd /var/www/dokuvault && tail -n 1 storage/app/demo-usage/$(date +%Y-%m).jsonl
```

`*` trusts every sender. That is convenient but removes the check: anyone who reaches the app
directly — past the proxy — can claim any origin via `X-Forwarded-For`. Only defensible if the proxy
really is the only way in.

### What is deliberately open on a demo

The credentials are in the README and in the notice banner. Every visitor is therefore an
administrator and can change almost anything. The hourly reset cleans up afterwards.

**The four accounts themselves are excluded**: with demo mode active, `admin`, `techniker`,
`kunde-rw` and `kunde-r` are fully locked — no deleting, no changes, not even to the name. Otherwise
every other visitor would be locked out until the next reset. That the **username** is fixed too is
not overzealous: the protection recognises the accounts by it, so renaming one would lift it. Which
usernames are protected is set in `config/custom.php` under `demo_protected_users`. Accounts you
create yourself stay fully editable.

A demo should therefore contain **no** real data and run on a server where nothing else does.

## Deploying by hand

Either in GitHub under **Actions → deploy → Run workflow**, or directly on the server:

```bash
cd /var/www/dokuvault && ./deploy.sh
```

The script stops at the first error and returns exit code 1, so the GitHub Action turns red instead
of reporting half a deploy as success.

**Maintenance mode only covers the end.** Fetching the code, `composer install` and the frontend
build run while the site is up — they need no downtime. Only for migrations, cache rebuilding and the
demo reset does the site return 503. Measured on the demo: **10 seconds**, previously 17. The gap
grows as soon as dependencies change: `composer install` and `npm ci` are a matter of seconds only
while the lock files stay the same — otherwise they take minutes, and those minutes used to sit
entirely inside the maintenance window. Releasing the site hangs on a `trap`, so it happens even if a
step in between fails.

The price: between `git reset` and `migrate` the site already serves the new code against the old
schema. For migrations that only add something — the normal case here — nobody notices. If you rename
or drop a column, put the deploy in a quiet minute or roll it out in two steps.

`deploy.sh` resets the working tree hard to `origin/main`. Changes made directly on the server are
lost in the process — deliberately, so the server always matches the repository.
