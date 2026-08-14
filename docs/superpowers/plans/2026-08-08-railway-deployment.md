# Railway Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **First action on execution:** copy this file to `docs/superpowers/plans/2026-08-08-railway-deployment.md` and work from there. Plan mode restricted authoring to `~/.claude/plans/`.

**Goal:** Make the existing Docker image deployable on Railway so HoopSense+ runs publicly for a ~3-month thesis defense period, with working websockets, email verification, and persistent uploads.

**Architecture:** One Railway service runs the whole app — FrankenPHP (web) + `queue:work` + `reverb:start` under supervisord — plus a managed MySQL service and a volume mounted at `/app/storage`. Python stays a subprocess of the queue worker, not a service. Reverb is exposed through a second Railway domain pointed at port 8080 on the same service.

**Tech Stack:** Laravel 13, Inertia 2, React 18/TS (Vite), Laravel Reverb, FrankenPHP (PHP 8.4), MySQL 8, supervisord, Pest 4.

## Global Constraints

- **Layering is enforced** (CLAUDE.md): Controller → FormRequest → Service → Repository → Model. No task here touches that path; the only app-code change is a seeder plus a new config file.
- **`Components/ui/` must never be modified.** No frontend source changes in this plan at all — only Vite *build inputs*.
- **`config()` not `env()` outside config files.** `docker/start.sh` runs `php artisan config:cache`, after which `env()` returns `null`. This is why Task 1 introduces a config file rather than reading `env()` in the seeder.
- **Reverb's port is 8080 and is hardcoded** in `docker/supervisord.conf`. `$PORT` must never resolve to 8080.
- **Vite inlines `import.meta.env.VITE_*` at build time.** Any `VITE_` value must be a Docker **build arg**, declared with `ARG` in the stage that runs `npm run build`. Railway injects service variables at build time only for `ARG`s declared in that stage.
- Local dev stays SQLite; production targets MySQL. Nothing in this plan may change local dev behavior.
- Run `vendor/bin/pint` before each PHP commit.

---

### Task 1: Env-driven demo account password

`database/seeders/DemoUserSeeder.php:16` hardcodes `password123` for three accounts, and `docker/start.sh` seeds automatically on an empty database — so those credentials land on a public URL. Move the password into config so Railway can set it.

**Files:**
- Create: `config/demo.php`
- Create: `tests/Feature/DemoUserSeederTest.php`
- Modify: `database/seeders/DemoUserSeeder.php` (all three `updateOrCreate` calls)

**Interfaces:**
- Consumes: nothing.
- Produces: config key `demo.password` (string), sourced from the `DEMO_PASSWORD` env var, default `'password123'`. Task 2 documents this var in `.env.example`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DemoUserSeederTest.php`. Note it seeds `DemoUserSeeder` *directly* and builds the team with a factory — do **not** call `TeamPlayersSeeder`, which dispatches `RebuildPlayerStats` and would invoke the Python engine on the `sync` queue.

```php
<?php

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the demo admin with the configured password', function () {
    config()->set('demo.password', 's3cret-from-env');

    $this->seed(DemoUserSeeder::class);

    $user = User::query()->where('email', 'test@email.com')->firstOrFail();

    expect(Hash::check('s3cret-from-env', $user->password))->toBeTrue();
});

it('seeds each team coach with the configured password', function () {
    config()->set('demo.password', 'coach-pass');
    Team::factory()->create(['code' => 'GSW']);
    Team::factory()->create(['code' => 'LAK']);

    $this->seed(DemoUserSeeder::class);

    foreach (['warriors@email.com', 'lakers@email.com'] as $email) {
        $coach = User::query()->where('email', $email)->firstOrFail();
        expect(Hash::check('coach-pass', $coach->password))->toBeTrue();
    }
});

it('defaults to password123 when DEMO_PASSWORD is unset', function () {
    $this->seed(DemoUserSeeder::class);

    $user = User::query()->where('email', 'test@email.com')->firstOrFail();

    expect(Hash::check('password123', $user->password))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DemoUserSeederTest`
Expected: the first two tests FAIL — `Hash::check` returns `false` because the seeder still writes the literal `password123`. The third test passes already (that's fine, it's the regression guard).

- [ ] **Step 3: Create the config file**

Create `config/demo.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Account Password
    |--------------------------------------------------------------------------
    |
    | DemoUserSeeder runs automatically against an empty production database
    | (see docker/start.sh). Those accounts sit on a public URL, so the
    | password is read from the environment rather than committed here.
    |
    */

    'password' => env('DEMO_PASSWORD', 'password123'),

];
```

- [ ] **Step 4: Use the config value in the seeder**

In `database/seeders/DemoUserSeeder.php`, read it once at the top of `run()` and substitute it into all three `updateOrCreate` calls:

```php
public function run(): void
{
    $password = (string) config('demo.password');

    User::updateOrCreate(
        ['email' => 'test@email.com'],
        [
            'name' => 'Demo Admin',
            'password' => $password,
            'email_verified_at' => now(),
            'team_id' => null,
        ],
    );

    // ...and the same `'password' => $password,` swap inside the
    // $warriors and $lakers blocks. Leave everything else untouched.
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=DemoUserSeederTest`
Expected: 3 passed.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint config/demo.php database/seeders/DemoUserSeeder.php tests/Feature/DemoUserSeederTest.php
git add config/demo.php database/seeders/DemoUserSeeder.php tests/Feature/DemoUserSeederTest.php
git commit -m "feat(deploy): read demo account password from DEMO_PASSWORD env"
```

---

### Task 2: Correct the stale Python env vars in `.env.example`

`.env.example` still advertises `PYTHON_ANALYTICS_URL=http://127.0.0.1:8001`. There is no HTTP analytics service — `config/analytics.php` reads `PYTHON_BIN` and `PYTHON_ENGINE_PATH`. Anyone configuring Railway from `.env.example` would set the wrong variables.

**Files:**
- Create: `tests/Unit/EnvExampleTest.php`
- Modify: `.env.example`

**Interfaces:**
- Consumes: `demo.password` config key from Task 1.
- Produces: an `.env.example` that is a complete, correct source for the Railway variable list in the "Railway dashboard setup" section below.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/EnvExampleTest.php`. This lives in `tests/Unit` — per `tests/Pest.php`, Unit tests get no `RefreshDatabase`, and this needs no database.

```php
<?php

it('documents every env var the config layer actually reads', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)
        ->toContain('PYTHON_BIN=')
        ->toContain('PYTHON_ENGINE_PATH=')
        ->toContain('DEMO_PASSWORD=')
        ->toContain('REVERB_SERVER_HOST=')
        ->toContain('REVERB_SERVER_PORT=');
});

it('does not advertise the removed HTTP analytics service', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->not->toContain('PYTHON_ANALYTICS_URL');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EnvExampleTest`
Expected: both FAIL — the first on the missing `PYTHON_BIN=`, the second on the still-present `PYTHON_ANALYTICS_URL`.

- [ ] **Step 3: Edit `.env.example`**

Replace the line `PYTHON_ANALYTICS_URL=http://127.0.0.1:8001` with:

```
PYTHON_BIN=python3
PYTHON_ENGINE_PATH=analytics/engine.py
```

Leave `MODEL_RECOMMENDATION_TIMEOUT=15` where it is. Then add `DEMO_PASSWORD=password123` near the bottom, and extend the existing Reverb block with the two server-side bind vars (these are distinct from the client-facing `REVERB_HOST`/`REVERB_PORT` already present — `config/reverb.php` reads `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` for the listener, and `REVERB_HOST` only as the advertised hostname):

```
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=EnvExampleTest`
Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add .env.example tests/Unit/EnvExampleTest.php
git commit -m "docs(env): replace stale PYTHON_ANALYTICS_URL with real engine vars"
```

---

### Task 3: Bake `VITE_REVERB_*` into the frontend build

**This is the bug that would silently kill the live-game feature in production.** `resources/js/bootstrap.js:11-19` constructs `Echo` from `import.meta.env.VITE_REVERB_HOST` etc. Vite inlines those at build time. The Dockerfile's `frontend` stage declares no `ARG`s, so `npm run build` sees them as empty and every deployed browser tries to open a websocket against `undefined`/`localhost`.

**Files:**
- Modify: `Dockerfile` (stage 1, `FROM node:20-alpine AS frontend`)

**Interfaces:**
- Consumes: nothing.
- Produces: build args `VITE_APP_NAME`, `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`. Railway supplies these from service variables of the same name.

- [ ] **Step 1: Write the failing test**

There is no PHP test for a Docker build; the test is the build itself. Run it first to watch it fail:

```bash
docker build --target frontend \
  --build-arg VITE_REVERB_HOST=reverb.example.test \
  --build-arg VITE_REVERB_APP_KEY=testkey123 \
  -t hoopsense-frontend .

docker run --rm hoopsense-frontend \
  sh -c "grep -rl 'reverb.example.test' /app/public/build/assets || echo 'NOT FOUND'"
```

- [ ] **Step 2: Confirm it fails**

Expected: `NOT FOUND`. The host never reached the bundle because the stage ignores the build arg.

- [ ] **Step 3: Add the ARG/ENV block to the frontend stage**

`ARG`s must be declared **in the stage that consumes them** — they do not cross stage boundaries. Place them after `npm ci` (so the dependency layer stays cacheable when only these values change) and before `COPY . .`:

```dockerfile
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Vite inlines import.meta.env.VITE_* at build time — these must be build
# args, not runtime env. Railway injects matching service variables here.
ARG VITE_APP_NAME="HoopSense+"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY . .
RUN npm run build
```

- [ ] **Step 4: Re-run the build to verify it passes**

```bash
docker build --target frontend \
  --build-arg VITE_REVERB_HOST=reverb.example.test \
  --build-arg VITE_REVERB_APP_KEY=testkey123 \
  -t hoopsense-frontend .

docker run --rm hoopsense-frontend \
  sh -c "grep -rl 'reverb.example.test' /app/public/build/assets || echo 'NOT FOUND'"
```

Expected: one or more `/app/public/build/assets/app-*.js` paths printed. Not `NOT FOUND`.

- [ ] **Step 5: Commit**

```bash
git add Dockerfile
git commit -m "fix(docker): bake VITE_REVERB_* into the frontend build stage"
```

---

### Task 4: Swap `php artisan serve` for FrankenPHP

`docker/supervisord.conf:9` runs `php artisan serve`, a single-threaded development server: one slow request blocks every other request, including the `/broadcasting/auth` handshake Reverb depends on. Two coaches on a live game plus win-probability polling is precisely the load that exposes it.

The runtime base image and the supervisord web command must change together — the container is broken if only one lands, so this is one task.

**Files:**
- Create: `docker/php.ini`
- Modify: `Dockerfile` (stage 2, runtime)
- Modify: `docker/supervisord.conf` (`[program:laravel]` → `[program:web]`)
- Modify: `docker/start.sh` (PORT default)

**Interfaces:**
- Consumes: the `frontend` stage from Task 3 (`COPY --from=frontend /app/public/build public/build` is unchanged).
- Produces: a container listening on `$PORT` (HTTP, default 8000) for web and `8080` for Reverb.

- [ ] **Step 1: Write the failing test**

Stand up the local harness, then smoke-test the current image:

```bash
docker network create hoopsense-test
docker run -d --name hoopsense-mysql --network hoopsense-test \
  -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=hoopsense mysql:8

docker build -t hoopsense .
docker run -d --name hoopsense-app --network hoopsense-test -p 8000:8000 \
  -e PORT=8000 -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=http://localhost:8000 -e LOG_CHANNEL=stderr \
  -e DB_CONNECTION=mysql -e DB_HOST=hoopsense-mysql -e DB_PORT=3306 \
  -e DB_DATABASE=hoopsense -e DB_USERNAME=root -e DB_PASSWORD=secret \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
  -e BROADCAST_CONNECTION=reverb -e REVERB_APP_ID=local -e REVERB_APP_KEY=local \
  -e REVERB_APP_SECRET=local -e REVERB_HOST=localhost -e REVERB_PORT=8080 \
  -e REVERB_SCHEME=http -e DEMO_PASSWORD=demo-pass \
  hoopsense

# Concurrency probe — 20 simultaneous requests to the health endpoint
time (for i in $(seq 1 20); do curl -s -o /dev/null http://localhost:8000/up & done; wait)
docker exec hoopsense-app sh -c "command -v frankenphp || echo 'NO FRANKENPHP'"
```

- [ ] **Step 2: Confirm the baseline**

Expected: `NO FRANKENPHP`, and the 20-request probe serialises (visibly slower than it should be — record the elapsed time to compare against Step 5). Then tear down the app container only, keeping MySQL:

```bash
docker rm -f hoopsense-app
```

- [ ] **Step 3a: Create `docker/php.ini`**

```ini
; Production PHP settings for the Railway image.
memory_limit = 256M
upload_max_filesize = 16M
post_max_size = 16M
expose_php = Off

opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
; Code never changes inside an immutable image — skip the stat() per include.
opcache.validate_timestamps = 0
```

- [ ] **Step 3b: Rewrite the runtime stage of `Dockerfile`**

Replace `FROM php:8.4-cli` and its `apt-get`/`docker-php-ext-install` block. The FrankenPHP images ship `install-php-extensions`, which handles the `gd` freetype/jpeg configuration itself — the whole `libpng-dev`/`libjpeg62-turbo-dev`/`libfreetype6-dev`/`libonig-dev`/`libxml2-dev`/`libzip-dev`/`libsqlite3-dev` list goes away:

```dockerfile
FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip opcache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    python3 python3-pip \
    default-mysql-client \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
```

Everything after this point in the existing Dockerfile is unchanged: the composer binary copy, `WORKDIR /app`, the `composer install --no-dev --optimize-autoloader --no-scripts --no-interaction` layer, `COPY . .`, `rm -f public/hot`, `composer dump-autoload --optimize`, `COPY --from=frontend /app/public/build public/build`, the `pip3 install --break-system-packages -r requirements.txt || true` line (keep it — `requirements.txt` is stdlib-only today but is where Phase 3 ML deps land), the storage `mkdir`/`chmod`, the supervisord and start.sh copies, and `CMD`.

- [ ] **Step 3c: Update `docker/supervisord.conf`**

Rename `[program:laravel]` to `[program:web]` and replace its `command`. Leave `[program:queue-worker]` and `[program:reverb]` exactly as they are.

```ini
[program:web]
command=frankenphp php-server --root /app/public --listen :%(ENV_PORT)s
directory=/app
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

Use FrankenPHP's **classic mode** (`php-server`), not worker mode. Worker mode keeps the Laravel kernel booted between requests and leaks container state — not something to debug before a defense.

- [ ] **Step 3d: Default `PORT` in `docker/start.sh`**

`supervisord` expands `%(ENV_PORT)s` and hard-fails if `PORT` is unset. Add this immediately after `set -e`, before the first `echo`:

```bash
# Railway injects PORT. Default for local runs — must NOT be 8080, which is
# already claimed by the reverb program in supervisord.conf.
export PORT="${PORT:-8000}"
```

Leave the rest of `start.sh` in its current order. Seeding must stay **before** `php artisan config:cache`, otherwise `config('demo.password')` resolves against a cache built before the seeder runs.

- [ ] **Step 4: Rebuild and re-run**

```bash
docker build -t hoopsense .
docker run -d --name hoopsense-app --network hoopsense-test -p 8000:8000 \
  -e PORT=8000 -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=http://localhost:8000 -e LOG_CHANNEL=stderr \
  -e DB_CONNECTION=mysql -e DB_HOST=hoopsense-mysql -e DB_PORT=3306 \
  -e DB_DATABASE=hoopsense -e DB_USERNAME=root -e DB_PASSWORD=secret \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
  -e BROADCAST_CONNECTION=reverb -e REVERB_APP_ID=local -e REVERB_APP_KEY=local \
  -e REVERB_APP_SECRET=local -e REVERB_HOST=localhost -e REVERB_PORT=8080 \
  -e REVERB_SCHEME=http -e DEMO_PASSWORD=demo-pass \
  hoopsense
docker logs -f hoopsense-app   # watch migrations + seed, then Ctrl-C
```

- [ ] **Step 5: Verify it passes**

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:8000/up
docker exec hoopsense-app supervisorctl status
time (for i in $(seq 1 20); do curl -s -o /dev/null http://localhost:8000/up & done; wait)
```

Expected: `200`; `supervisorctl status` shows `web`, `queue-worker`, and `reverb` all `RUNNING`; the 20-request probe completes substantially faster than the Step 2 baseline.

- [ ] **Step 6: Commit**

```bash
git add Dockerfile docker/php.ini docker/supervisord.conf docker/start.sh
git commit -m "feat(docker): serve via FrankenPHP instead of artisan serve"
```

---

### Task 5: Railway service config and build-context trim

**Files:**
- Create: `railway.json`
- Modify: `.dockerignore`

**Interfaces:**
- Consumes: the working image from Task 4, and the `/up` health route already registered at `bootstrap/app.php:13`.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```bash
test -f railway.json && echo "EXISTS" || echo "MISSING"
docker run --rm hoopsense ls /app | grep -x docs && echo "DOCS SHIPPED" || echo "clean"
```

- [ ] **Step 2: Confirm it fails**

Expected: `MISSING`, then `DOCS SHIPPED`.

- [ ] **Step 3a: Create `railway.json`**

Pins the builder (Railway would otherwise try Nixpacks) and wires the health check to the route Laravel already exposes.

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile"
  },
  "deploy": {
    "healthcheckPath": "/up",
    "healthcheckTimeout": 300,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

`healthcheckTimeout` is 300s because the first boot runs migrations *and* the full demo seed, and `TeamPlayersSeeder` runs `RebuildPlayerStats` synchronously for 20 players.

- [ ] **Step 3b: Extend `.dockerignore`**

Append to the existing list (keep every current entry):

```
docs
playwright-report
.pytest_cache
*.xlsx
.agents
.codex
.cursor
.superpowers
.impeccable
```

`*.xlsx` catches the stray `player_game_history_30_games.xlsx` sitting in the repo root.

- [ ] **Step 4: Verify**

```bash
python3 -c "import json; json.load(open('railway.json')); print('valid json')"
docker build -t hoopsense .
docker run --rm hoopsense ls /app | grep -x docs && echo "DOCS SHIPPED" || echo "clean"
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:8000/up
```

Expected: `valid json`, then `clean`, and the health check still returns `200` after the rebuild (regression guard — `.dockerignore` changes are an easy way to accidentally drop a needed file).

- [ ] **Step 5: Commit**

```bash
git add railway.json .dockerignore
git commit -m "feat(deploy): add railway.json and trim the docker build context"
```

---

### Task 6: End-to-end verification against the local harness

No commit — this is the gate before touching Railway. It exercises the three subsystems that a `curl /up` cannot: the queue worker's Python subprocess, the volume, and broadcasting.

- [ ] **Step 1: Confirm the PHP suite is still green**

Run: `php artisan test`
Expected: all pass, including the three new tests from Tasks 1–2.

Run: `npm run typecheck`
Expected: no errors (no `.tsx` was touched; this catches an accidental edit).

- [ ] **Step 2: Verify the Python engine runs inside the container**

```bash
docker exec hoopsense-app python3 --version
docker exec hoopsense-app sh -c \
  "echo '{\"command\":\"bpm\",\"payload\":{}}' | python3 analytics/engine.py"
```
Expected: a Python 3.x version, then a JSON object on stdout (an error-shaped JSON for the empty payload is fine — it proves the interpreter and the script path in `config/analytics.php` resolve).

- [ ] **Step 3: Verify the queue worker completes a real job**

Log in at `http://localhost:8000` as `warriors@email.com` / `demo-pass`. Open a player and add a game history entry. The pipeline is `RebuildPlayerStats` → `ComputePlayerPlusMinus` → Python `bpm`.

Expected: `plus_minus` renders as `—` on save, then becomes a number within a few seconds on refresh. Per CLAUDE.md it must never render `0` or blank while pending.

- [ ] **Step 4: Verify the volume**

```bash
docker rm -f hoopsense-app
docker volume create hoopsense-storage
# re-run the Task 4 Step 4 command with: -v hoopsense-storage:/app/storage
```
Upload a team logo, then `docker rm -f hoopsense-app` and start it again with the same volume.
Expected: the logo still renders. (`docker/start.sh` recreates `storage/framework/*` on every boot precisely because the mount replaces the directory.)

- [ ] **Step 5: Verify broadcasting**

Open a live game as `warriors@email.com` in one browser and `lakers@email.com` in another, then record an event.
Expected: the event appears in the second window with no refresh. **This is the decisive test** — it proves the Vite build args from Task 3, the Reverb process, and `/broadcasting/auth` all line up.

- [ ] **Step 6: Verify SMTP end to end**

Test the Gmail credentials locally — finding out they're wrong after deploying costs a
full rebuild cycle. Config is cached at boot, so the mail vars must be present when the
container *starts*; `docker exec -e` won't reach them.

```bash
docker rm -f hoopsense-app
# Re-run the Task 4 Step 4 command with these six flags appended:
#   -e MAIL_MAILER=smtp -e MAIL_HOST=smtp.gmail.com -e MAIL_PORT=587 \
#   -e MAIL_USERNAME=you@gmail.com -e MAIL_PASSWORD=<16-char app password> \
#   -e MAIL_FROM_ADDRESS=you@gmail.com -e MAIL_FROM_NAME=HoopSense+
```

First a fast credential check:

```bash
docker exec hoopsense-app php artisan tinker --execute \
  "Illuminate\Support\Facades\Mail::raw('HoopSense+ SMTP check', fn(\$m) => \$m->to('you@gmail.com')->subject('SMTP check')); echo 'sent';"
```

Expected: `sent`, and the mail arrives. A wrong or space-containing app password throws
`Symfony\Component\Mailer\Exception\TransportException: Username and Password not accepted`.

Then the path that actually matters — register a brand-new account at
`http://localhost:8000/register` with an address you can read.
Expected: the app redirects to the verify-email wall, the verification mail arrives, and
clicking the link lets you through to the dashboard. That exercises `MustVerifyEmail`,
the `verified` middleware, and the queue worker together.

- [ ] **Step 7: Tear down**

```bash
docker rm -f hoopsense-app hoopsense-mysql
docker network rm hoopsense-test
docker volume rm hoopsense-storage
```

---

## Railway dashboard setup (manual — not a code task)

**Services:** one app service from this repo, one MySQL. Attach a volume to the app service mounted at `/app/storage`.

**Two domains on the app service.** Railway allows multiple domains per service, each with its own target port:
- `<app>.up.railway.app` → `$PORT`
- `<reverb>.up.railway.app` → `8080`

**Deployment is inherently two-pass.** `REVERB_HOST` isn't knowable until the domain exists, and `VITE_REVERB_*` are baked in at build time. So: deploy once → create both domains → set the variables → **redeploy** so the bundle picks them up. Skipping the redeploy is the single most likely way to end up with a site that loads but has dead websockets.

**Variables** (generate `APP_KEY` with `php artisan key:generate --show`):

```
APP_NAME=HoopSense+          APP_ENV=production        APP_DEBUG=false
APP_KEY=base64:...           APP_URL=https://<app-domain>
LOG_CHANNEL=stderr           LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=${{MySQL.RAILWAY_PRIVATE_DOMAIN}}
DB_PORT=3306
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database      SESSION_SECURE_COOKIE=true
CACHE_STORE=database         QUEUE_CONNECTION=database

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<random>  REVERB_APP_KEY=<random>  REVERB_APP_SECRET=<random>
REVERB_HOST=<reverb-domain>  REVERB_PORT=443     REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0   REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY=${{REVERB_APP_KEY}}
VITE_REVERB_HOST=${{REVERB_HOST}}
VITE_REVERB_PORT=443         VITE_REVERB_SCHEME=https

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<your-full-gmail-address>
MAIL_PASSWORD=<16-char Google app password, no spaces>
MAIL_FROM_ADDRESS=<same gmail address>
MAIL_FROM_NAME=HoopSense+
# Do NOT set MAIL_SCHEME. config/mail.php reads it as env('MAIL_SCHEME') with no
# default; leaving it unset lets Laravel infer smtp:// + STARTTLS from port 587.
# Setting it explicitly is the usual way this breaks.

PYTHON_BIN=python3           PYTHON_ENGINE_PATH=analytics/engine.py
DEMO_PASSWORD=<pick one>
```

Two of these are load-bearing in non-obvious ways:
- `DB_HOST` uses `RAILWAY_PRIVATE_DOMAIN`, not the public proxy host. Private-network traffic isn't billed as egress; the public proxy would meter every query.
- `LOG_CHANNEL=stderr` — the default `stack`/`single` writes into `storage/logs` on the volume, where Railway's log viewer can't see it and it grows without bound for three months.

**SMTP — resolved: Gmail app password.**

Exactly two things in this app send mail, both from framework auth. A grep for
`toMail`, `MailMessage`, and `Mailable` across `app/` returns nothing:

1. **Email verification.** `RegisteredUserController` fires `Registered`, and because
   `User implements MustVerifyEmail` (`app/Models/User.php:17`) Laravel mails the verify
   link. Every route in `routes/web.php:23` is behind `verified`, so a user who can't
   receive it never gets past the wall.
2. **Password reset.** `routes/auth.php:28`.

Live-game invites are **not** in this list — `LiveGameInviteNotification::via()` returns
`['database', 'broadcast']`, so they travel over the notifications table and Reverb and
never touch mail. Nothing about the live-game feature depends on SMTP.

That puts total volume in the low tens across three months, so the provider is chosen on
setup friction: **a Gmail app password**. No domain, no signup, delivers to anyone.
Generate it at Google Account → Security → App passwords; the account needs 2-Step
Verification enabled first. Paste the 16 characters with the spaces stripped.

**Fallback if you'd rather not wire mail at all:** `DemoUserSeeder` sets
`email_verified_at => now()` on all three accounts, so `test@`, `warriors@`, and
`lakers@` walk straight past the `verified` middleware. Leaving `MAIL_MAILER=log` is
viable *if* the defense runs only on those logins — the cost is a dead password-reset
link and a hard lockout for anyone who clicks Register. Choosing this means skipping
Task 6 Step 6 and leaving the `MAIL_*` block above unset.

## Cost

FrankenPHP's steady-state memory is higher than `artisan serve` (Caddy plus a PHP thread pool, ~0.5 GB vs ~0.4 GB), which moves the earlier estimate from ~$11/mo to roughly **$12–13/mo — about $38 for three months**. The alternative is a demo that stalls under two concurrent users.

