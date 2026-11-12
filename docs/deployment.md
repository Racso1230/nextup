# Deployment

How to run nextup on the VPS at `https://nextup.ojfdigital.co.uk`. This is a runbook for someone new to servers:
follow the steps in order, and read the "why" notes if a command looks scary.

Nothing here has been deployed yet. The repo only contains the pieces:

| File                       | What it is                                                                      |
| -------------------------- | ------------------------------------------------------------------------------- |
| `Dockerfile`               | Builds two images: the site (`runtime`, the default) and the SSR server (`ssr`) |
| `compose.prod.yaml`        | The six services, networks and volumes                                          |
| `docker/entrypoint.sh`     | Runs migrations (web container only) and warms Laravel's caches on start        |
| `docker/php.ini`           | Production PHP and opcache settings                                             |
| `docker/Caddyfile.snippet` | The site block for the shared Caddy                                             |
| `docker/setup-env.sh`      | Creates the two secret env files without ever echoing a secret                  |

## How it fits together

```
internet -> Caddy (HTTPS, shared with the other sites)
              -> nextup            FrankenPHP serving Laravel, port 8080 inside Docker
                   -> nextup-ssr    Node, renders the Vue pages to HTML (port 13714)
                   -> nextup-db     Postgres 17            (private network only)
                   -> nextup-redis  Redis 7                (private network only)
            nextup-queue       php artisan queue:work (imports, IGDB enrichment)
            nextup-scheduler   php artisan schedule:work
```

No container publishes a port. Only Caddy is reachable from the internet. The database, Redis and the SSR server sit on
a private Docker network (`nextup_internal`) with no internet access at all.

The scheduler matters: Laravel's scheduler runs the nightly library imports (03:00 UTC), `igdb:sync` hourly and
`demo:prune` hourly. If `nextup-scheduler` is not running, none of that happens.

## What production relies on in the app

These three are already in the code; they are listed so you know why they exist:

1. `config/inertia.php` reads `INERTIA_SSR_URL`. Without it the site would look for the SSR server on
   localhost inside its own container and silently fall back to client-side rendering.
2. `bootstrap/app.php` trusts the proxy (`trustProxies(at: '*')`). Caddy terminates HTTPS and
   forwards plain HTTP, so Laravel must trust its `X-Forwarded-*` headers or it thinks every request is `http://`.
   (Laravel does not read a `TRUSTED_PROXIES` variable by itself, so setting one in the env file does nothing.)
3. `app/Providers/AppServiceProvider.php` calls `URL::forceHttps()` in production, so generated links, assets
   and the Steam OpenID return URL are always `https://`.

## 1. One-time setup

### 1.1 DNS record (Namecheap)

1. Namecheap -> Domain List -> `ojfdigital.co.uk` -> Manage -> Advanced DNS.
2. Add a new record: Type `A Record`, Host `nextup`, Value `57.131.25.108`, TTL Automatic.
3. Check it after a few minutes: `nslookup nextup.ojfdigital.co.uk` should answer `57.131.25.108`.

Caddy cannot get the HTTPS certificate until this record resolves, so do it first.

### 1.2 Secret env files

Log in (`ssh <your-alias>`), then get the script onto the server (copy it with `scp`, or paste it into a file with
`nano setup-env.sh`). From the folder that holds it:

```bash
bash setup-env.sh          # writes ~/stack/nextup.env and ~/stack/nextup-db.env
```

It asks for the Steam key, IGDB client ID and secret (typed hidden, nothing appears on screen), optional alert email and
mail settings, and generates `APP_KEY` and the database password itself. Both files are mode 600 (only you can read
them). It refuses to overwrite existing files unless you pass `--force`.

Afterwards, copy both files into your password manager as a backup: a lost `APP_KEY` cannot be recovered.

Check the permissions, without showing the contents: `ls -l ~/stack/nextup*.env` should show `-rw-------`.

#### Every variable in `nextup.env`

Values are never committed. Descriptions only:

| Variable                                                   | Description                                                                                   |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `APP_NAME`                                                 | Display name, `nextup`.                                                                       |
| `APP_ENV`                                                  | `production`.                                                                                 |
| `APP_DEBUG`                                                | `false`. Never `true` in production: it prints secrets in error pages.                        |
| `APP_URL`                                                  | `https://nextup.ojfdigital.co.uk`. Also becomes the Steam OpenID realm and return URL.        |
| `APP_KEY`                                                  | Encrypts sessions and cookies. Generated by the script (`base64:...`).                        |
| `LOG_CHANNEL`                                              | `stack`.                                                                                      |
| `LOG_STACK`                                                | `json`: one JSON object per line, secrets redacted.                                           |
| `LOG_JSON_TARGET`                                          | `stderr`, so `docker logs` shows the JSON.                                                    |
| `LOG_LEVEL`                                                | `info` (use `debug` only while investigating).                                                |
| `DB_CONNECTION`                                            | `pgsql`.                                                                                      |
| `DB_HOST`                                                  | `nextup-db`, the compose service name.                                                        |
| `DB_PORT`                                                  | `5432`.                                                                                       |
| `DB_DATABASE`                                              | `nextup`. Must match `POSTGRES_DB`.                                                           |
| `DB_USERNAME`                                              | `nextup`. Must match `POSTGRES_USER`.                                                         |
| `DB_PASSWORD`                                              | Generated. Must match `POSTGRES_PASSWORD` in `nextup-db.env`.                                 |
| `REDIS_CLIENT`                                             | `phpredis`.                                                                                   |
| `REDIS_HOST`                                               | `nextup-redis`, the compose service name.                                                     |
| `REDIS_PORT`                                               | `6379`.                                                                                       |
| `REDIS_PASSWORD`                                           | `null` (Redis is on the private network only).                                                |
| `SESSION_DRIVER`                                           | `redis`.                                                                                      |
| `SESSION_SECURE_COOKIE`                                    | `true`: session cookies only travel over HTTPS.                                               |
| `SESSION_DOMAIN`                                           | `null`.                                                                                       |
| `CACHE_STORE`                                              | `redis`.                                                                                      |
| `QUEUE_CONNECTION`                                         | `redis`.                                                                                      |
| `INERTIA_SSR_URL`                                          | `http://nextup-ssr:13714`. Read by `config/inertia.php`.                                      |
| `STEAM_API_KEY`                                            | Steam Web API key (https://steamcommunity.com/dev/apikey). Also used as the Socialite secret. |
| `IGDB_CLIENT_ID`                                           | Twitch developer application client ID (IGDB uses Twitch login).                              |
| `IGDB_CLIENT_SECRET`                                       | Twitch application client secret.                                                             |
| `ALERT_MAIL_TO`                                            | Where failed-job alert emails go. Empty disables the email (failures are still logged).       |
| `MAIL_MAILER`                                              | `smtp`, or `log` if you have no mail provider yet.                                            |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | SMTP settings from your mail provider.                                                        |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`                      | The sender shown on alert emails.                                                             |

`nextup-db.env` holds `POSTGRES_DB`, `POSTGRES_USER` and `POSTGRES_PASSWORD`. The password is only used the first time
the database volume is created; changing it later does not change the existing database.

### 1.3 Caddy

Caddy is the shared web server that handles HTTPS for every site on the VPS.

1. Append `docker/Caddyfile.snippet` to the shared Caddyfile (for example `cat Caddyfile.snippet >> ~/stack/Caddyfile`;
   open the file afterwards and check the block landed on its own lines).
2. Reload Caddy without downtime: `docker compose -f ~/stack/compose.yaml exec caddy caddy reload --config /etc/caddy/Caddyfile`
   (use the service name and path your stack already uses).

The snippet proxies to `nextup:8080`, adds the security headers (HSTS for one day while you test, `nosniff`, referrer
policy, `X-Frame-Options` and `frame-ancestors`), compresses responses and caches `/build/*` for a year. It does not
block `/auth/steam/callback`, where Steam sends people back after login.

Caddy and nextup must share a Docker network. Including `compose.prod.yaml` in the same stack (below) does this
automatically, because the `default` network is shared.

### 1.4 Add the services to the stack

Two options. For a shared stack, **`include:` is the better choice**, because the nextup definitions stay in one file in
the repo and deploys cannot drift from it.

**Option A, `include:` (recommended).** In `~/stack/compose.yaml`, add at the top level:

```yaml
include:
    - path: nextup/compose.prod.yaml # relative to ~/stack
```

Relative paths in an included file (`nextup.env`, `nextup-db.env`) resolve from **that file's folder**. If the repo
checkout is `~/stack/nextup/`, either copy the two env files there or move `nextup.env` and `nextup-db.env` to that
folder (the script accepts a target folder: `bash setup-env.sh ~/stack/nextup`). Set `NEXTUP_SRC` (the build context
folder) to the checkout if it is not the compose file's own folder.

**Option B, copy the services in.** Paste the services, `networks` and `volumes` from `compose.prod.yaml` into
`~/stack/compose.yaml`. This keeps everything in one file, but you must remember to copy changes across by hand.

Either way, check the result with `docker compose -f ~/stack/compose.yaml config -q` (no output means valid).

### 1.5 Deploy key and the `live` branch pipeline

The other sites deploy by pushing to a `live` branch. Reuse that pipeline for nextup; here is what to add (these steps
touch GitHub and your server's deploy files, so do them yourself, they are not automated here):

1. **Read-only deploy key.** On the server: `ssh-keygen -t ed25519 -f ~/.ssh/nextup_deploy -N ""`. In the nextup GitHub
   repo: Settings -> Deploy keys -> Add deploy key, paste `~/.ssh/nextup_deploy.pub`, and leave "Allow write access"
   **unticked**. Add a host alias to `~/.ssh/config` (for example `Host github-nextup` using that key) so git picks the
   right key.
2. **Pipeline entry.** In `~/stack/deploy/sites.sh`, add a nextup entry modelled on the existing ones: the repo URL (via
   the alias), the `live` branch, the checkout folder, and the service names `nextup nextup-ssr nextup-queue
   nextup-scheduler`. The deploy step must tag the old image first (`docker tag nextup:latest nextup:previous`, same for
   `nextup-ssr`), then rebuild and `up -d`.
3. Create the `live` branch on GitHub from `main` when you are ready to go. **Pushing to `live` is what deploys.**

## 2. First deploy

If you are not using the pipeline yet, do it by hand from the stack folder:

```bash
cd ~/stack
docker compose build nextup nextup-ssr
docker compose up -d nextup-db nextup-redis nextup nextup-ssr nextup-queue nextup-scheduler
docker compose ps
```

The web container runs `php artisan migrate --force` on start. The queue and scheduler wait until the web container is
healthy. First start can take a minute. Watch it with `docker compose logs -f nextup`.

### Verify checklist

Run these from your own computer or the server. Tick them off in order:

- [ ] **Containers healthy:** `docker compose ps` shows `healthy` for `nextup`, `nextup-db`, `nextup-redis`,
      `nextup-ssr`, `nextup-queue` and `nextup-scheduler`.
- [ ] **HTTPS and liveness:** `curl -i https://nextup.ojfdigital.co.uk/up` returns `200`, with the
      `Strict-Transport-Security` header.
- [ ] **Dependencies:** `curl https://nextup.ojfdigital.co.uk/health` returns `{"status":"ok", ...}` with `ok` for
      database, cache and queue.
- [ ] **SSR works:** the hero text must be in the raw HTML, not only added by JavaScript.
      `curl -s https://nextup.ojfdigital.co.uk/ | grep -c "tonight"` should print a number above 0 (the headline is
      "What should you play tonight?", one span per word). If it prints 0, check `INERTIA_SSR_URL` and
      `docker compose logs nextup-ssr`.
- [ ] **Steam login:** open the site in a browser, click sign in with Steam, approve on Steam, and land back on nextup
      signed in. The realm and return URL come from `APP_URL`, so it must be exactly the `https://` address.
- [ ] **Queue and scheduler:** `docker compose exec nextup-scheduler php artisan schedule:list` lists
      `library:queue-nightly`, `demo:prune` and `igdb:sync`. `docker compose logs --tail 20 nextup-queue` shows the
      worker running with no errors. Importing a library from the app should move through the queue.
- [ ] **Logs are JSON:** `docker compose logs --tail 5 nextup` shows one JSON object per line.
- [ ] **No exposed ports:** `docker compose ps` shows no `0.0.0.0:` mappings for any nextup service.

## 3. Rollback

The pipeline tags the previous images as `nextup:previous` and `nextup-ssr:previous` before each build. To go back:

```bash
cd ~/stack
docker tag nextup:previous nextup:latest
docker tag nextup-ssr:previous nextup-ssr:latest
docker compose up -d --no-build nextup nextup-ssr nextup-queue nextup-scheduler
```

`--no-build` matters: it makes compose use the tagged images instead of rebuilding the current code.

**Migrations are not rolled back automatically.** If the bad release added a migration, the old code now runs against
the newer schema. Most additive migrations (new tables or nullable columns) are harmless to old code. If one is not,
restore the database from a backup (section 4) or write a fix-forward migration. Do not run `migrate:rollback` on
production without a fresh backup first.

## 4. Backups (still open)

Not set up yet. Until it is, a database loss means losing every user's data. A suggested nightly dump, added with
`crontab -e` on the server:

```cron
15 2 * * * docker compose -f /home/<user>/stack/compose.yaml exec -T nextup-db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > /home/<user>/backups/nextup-$(date +\%F).sql.gz && find /home/<user>/backups -name 'nextup-*.sql.gz' -mtime +14 -delete
```

Create the folder first (`mkdir -p ~/backups`). Replace `<user>` with your server username. Note that `%` must be
escaped as `\%` in cron lines. This keeps 14 days on the same machine, which does not survive losing the VPS: copying
the dumps off-server (for example with `rclone` to object storage) is the other half and is still to do. Test a
restore once: `gunzip -c file.sql.gz | docker compose exec -T nextup-db psql -U nextup nextup_restore_test`
(create that scratch database first).

Redis holds only the queue and cache, so it needs no backup; its `appendonly` volume just keeps queued jobs across
restarts.

## 5. Updating the Steam and IGDB keys

1. Edit the secret file: `nano ~/stack/nextup.env`, replace the value of `STEAM_API_KEY`, `IGDB_CLIENT_ID` or
   `IGDB_CLIENT_SECRET`, and save. Keep the value in double quotes and do not paste it anywhere else.
2. Restart so every container picks it up, and the config cache is rebuilt:
   `docker compose up -d --force-recreate nextup nextup-queue nextup-scheduler`.
3. For IGDB, the cached Twitch token expires on its own; to drop it immediately, run
   `docker compose exec nextup php artisan cache:clear`.
4. Re-check `/health` and run a library import.

To start over from scratch (for example after a leak), run `bash setup-env.sh --force`. This also generates a new
`APP_KEY`, which signs everyone out, and a new database password, which will **not** match the existing database
volume. In that case change the database password inside Postgres first
(`ALTER USER nextup PASSWORD '...'`) or use the edit-by-hand route above.

## 6. IGDB terms and credit

nextup uses IGDB data under the Twitch Developer Services Agreement. Keep to it:

- Show IGDB credit wherever IGDB-sourced data (names, genres, themes, cover art) is displayed. A visible
  "Game data from IGDB" link in the footer or about page is the usual way. Check the live site has it before launch.
- The credentials belong to your Twitch developer application. Never commit them, and rotate the secret if it leaks.
- Respect the rate limit (4 requests per second). The IGDB client is already rate limited; do not raise it.
- Do not present IGDB data as your own, and read the current terms (https://api-docs.igdb.com and the Twitch Developer
  Services Agreement) when adding new uses of the data, since they can change.
