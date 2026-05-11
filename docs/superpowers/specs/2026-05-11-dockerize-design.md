# Dockerize the Champions League Mini Fixture project

**Date:** 2026-05-11
**Status:** Approved, ready for implementation plan

## Goal

Containerize the Nx monorepo (Laravel API + Vue frontend + Postgres) so that:

1. A new contributor can clone the repo, install Podman, and run a single `npm run compose:setup` to get a fully functional local environment — DB seeded, API live, frontend hot-reloading.
2. The API can be deployed to Railway from a production Dockerfile in this repo.
3. The frontend continues to deploy to Vercel via its native Vite preset (no Dockerfile needed for prod), but is still runnable in a container for local dev parity.

CI/CD pipelines are explicitly **out of scope** — that work lives in a follow-up spec. This spec only covers what's needed to ship working containers locally and a Dockerfile that Railway can build.

## Non-goals

- GitHub Actions / CI workflows (lint, type-check, test gates, image build, deploy).
- SDK drift guard (`git diff --exit-code libs/api-sdk/src/generated/` after regen).
- Image registry / push pipelines.
- Staging environment provisioning.
- Vulnerability scanning (Trivy / Snyk).
- Backup / disaster recovery for the Railway Postgres instance.
- Multi-architecture (arm64/amd64) build matrix.
- Web production Dockerfile — Vercel handles this with its native Vite preset.

## Deployment targets (fixed by user decision)

- **Frontend:** Vercel (native Vite preset, no Dockerfile).
- **API:** Railway (consumes `docker/api.Dockerfile`).
- **Database:** Railway-managed Postgres (no Dockerfile, injected `DATABASE_URL`).
- **Local container runtime:** Podman (Dockerfile syntax identical to Docker; `podman compose` reads `compose.yml`).

## File structure

All container-related files live under `docker/` at the repo root, plus a `.dockerignore` and per-app `.env.docker.example` files. Documentation in `README.md` and convenience scripts in `package.json`.

```
docker/
├── compose.yml                          Local dev orchestration (3 services)
├── api.dev.Dockerfile                   PHP 8.3 CLI + composer + artisan serve
├── api.Dockerfile                       Prod (Railway): multi-stage, nginx + php-fpm
├── web.dev.Dockerfile                   Node 20 + Vite dev server
├── api/                                 Prod runtime configs
│   ├── entrypoint.sh                    Migrate + seed + start supervisord
│   ├── nginx.conf                       Listen 8080, fastcgi to php-fpm
│   ├── php-fpm.conf                     FPM pool tuning
│   ├── supervisord.conf                 Manage nginx + php-fpm processes
│   └── php-production.ini               opcache + memory_limit + post_max_size
└── postgres/
    └── init.sql                         Create champions_league DB on first boot

apps/api/.env.docker.example             Container-flavored Laravel .env template
apps/web/.env.docker.example             VITE_API_URL pointing at host:8000
.dockerignore                            Repo root — minimize build context
README.md                                "Local development (Podman)" section added
package.json                             compose:* npm scripts added
vercel.json                              Optional: explicit Vite build config (repo root)
```

The naming convention `<service>.[dev.]Dockerfile` keeps IDE syntax highlighting working (matches on `.Dockerfile` suffix) while making the service obvious from the filename.

## Part 1 — Local dev: compose services + lifecycle

### Services

`docker/compose.yml` defines three services with explicit dependencies and healthchecks:

**`postgres`** — `postgres:16-alpine`
- DB: `champions_league`, user `app`, password `secret` (hardcoded for local dev; flagged in spec)
- Bind-mounts `./postgres/init.sql` to `/docker-entrypoint-initdb.d/init.sql:ro` for first-boot DB creation
- Healthcheck via `pg_isready`; `interval: 5s`, `retries: 10`
- Persistent named volume `postgres-data` for `/var/lib/postgresql/data`
- Port 5432 exposed to host so `psql` from the laptop works

**`api`** — built from `docker/api.dev.Dockerfile`
- Build context: `../apps/api` (relative to compose file)
- Bind-mount: `../apps/api:/app` (source live-reloads)
- Named volume: `api-vendor:/app/vendor` (composer deps isolated from host)
- Env vars: `DB_HOST=postgres`, `DB_DATABASE=champions_league`, `DB_USERNAME=app`, `DB_PASSWORD=secret`
- Port 8000 exposed (artisan serve)
- `depends_on: postgres (service_healthy)` — won't start until DB accepts connections
- Default command: `php artisan serve --host=0.0.0.0 --port=8000`. PHP picks up source changes on the next request (no watcher needed — `artisan serve` is request-rebuilt).

**`web`** — built from `docker/web.dev.Dockerfile`
- Build context: `../` (workspace root, since Nx needs `nx.json`, `tsconfig.base.json`, and `libs/*`)
- Bind-mount: `../:/workspace`
- Named volume: `web-node-modules:/workspace/node_modules`
- Env var: `VITE_API_URL=http://localhost:8000`
- Port 4200 exposed
- Default command: `npx nx run web:dev --host 0.0.0.0 --port 4200` (Vite HMR over WebSocket)

### Lifecycle: `package.json` scripts

These are thin `podman compose` wrappers so contributors don't have to remember flags:

| Script | Underlying command | Purpose |
|---|---|---|
| `compose:up` | `podman compose -f docker/compose.yml up -d` | Start in background |
| `compose:down` | `podman compose -f docker/compose.yml down` | Stop; named volumes persist |
| `compose:logs` | `podman compose -f docker/compose.yml logs -f` | Tail combined logs |
| `compose:setup` | `compose:up` → wait healthy → `compose:install` → `compose:migrate-fresh` → `compose:env-init` | One-shot first-time setup |
| `compose:install` | `podman exec api composer install` + `podman exec web npm ci` | Install deps inside containers |
| `compose:env-init` | If `apps/api/.env` missing: copy `.env.docker.example` → `.env` and run `php artisan key:generate` | Bootstrap a fresh APP_KEY |
| `compose:migrate` | `podman exec api php artisan migrate --force` | Apply pending migrations |
| `compose:migrate-fresh` | `podman exec api php artisan migrate:fresh --seed --force` | Destructive reset + seed 4 teams |
| `compose:seed` | `podman exec api php artisan db:seed --force` | Re-seed (idempotent — `TeamSeeder` uses `updateOrCreate`) |
| `compose:test:api` | `podman exec api php artisan test` | Pest |
| `compose:sdk` | `podman exec api php artisan scramble:export` + `podman exec web npx nx run api-sdk:generate` | Regenerate OpenAPI + TS SDK |

After `npm run compose:setup`, the contributor has:
- API live at `http://localhost:8000`
- Web live at `http://localhost:4200`
- Postgres on `localhost:5432` with 4 teams seeded

### Bind-mount + named-volume strategy

- **Source code:** bind-mounted from host → editor changes are visible inside containers instantly. `php artisan serve` and Vite both auto-reload on file change.
- **Dependencies (`vendor/`, `node_modules/`):** named volumes → not synced with host. This avoids the macOS volume-sync penalty (which is significant on bind-mounted `node_modules` with thousands of small files) and prevents host-installed deps from clashing with container deps if PHP/Node versions differ.

### Dev Dockerfiles

`docker/api.dev.Dockerfile`:

```dockerfile
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
      git curl libzip-dev oniguruma-dev icu-dev postgresql-dev \
      $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql zip intl pcntl \
    && pecl install redis \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

`docker/web.dev.Dockerfile`:

```dockerfile
FROM node:20-alpine

WORKDIR /workspace

RUN apk add --no-cache git python3 make g++

COPY package.json package-lock.json ./
COPY nx.json tsconfig.base.json tsconfig.json ./
COPY apps/web/package.json ./apps/web/
COPY libs/ui/package.json ./libs/ui/
COPY libs/api-sdk/package.json ./libs/api-sdk/

RUN npm ci

EXPOSE 4200

CMD ["npx", "nx", "run", "web:dev", "--host", "0.0.0.0", "--port", "4200"]
```

Both keep `vendor/` and `node_modules/` *inside* the named volumes — the `COPY` of package manifests in `web.dev.Dockerfile` is only there so `npm ci` runs during image build, populating the volume on first start.

## Part 2 — Production API Dockerfile (Railway)

`docker/api.Dockerfile` — multi-stage, alpine-based:

**Stage 1 — composer install** (`php:8.3-cli-alpine`)
- Install `composer` + `git`
- Copy `composer.json` + `composer.lock`
- `composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist`
- Output: `/app/vendor`

**Stage 2 — runtime** (`php:8.3-fpm-alpine`)
- Install nginx, supervisor, postgresql-client, build deps for `pdo_pgsql`, `intl`, `zip`, `pcntl`
- Install PHP extensions + opcache
- Copy `php-production.ini` → `/usr/local/etc/php/conf.d/`
- Copy `nginx.conf` → `/etc/nginx/nginx.conf` (listens on `:8080`, root `/app/public`, fastcgi_pass to `127.0.0.1:9000`)
- Copy `php-fpm.conf` → `/usr/local/etc/php-fpm.d/www.conf`
- Copy `supervisord.conf` → `/etc/supervisord.conf` (runs nginx + php-fpm side-by-side)
- Copy `--from=composer` stage `/app/vendor` → `/app/vendor`
- Copy `apps/api/.` → `/app` (without `vendor/` or `node_modules/` thanks to `.dockerignore`)
- `chown www-data:www-data /app/storage /app/bootstrap/cache`
- `chmod +x /entrypoint.sh`
- `EXPOSE 8080`
- `ENTRYPOINT ["/entrypoint.sh"]`

**`entrypoint.sh`:**

```sh
#!/bin/sh
set -e

# Cache config + routes (fast cold start)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations and seed teams (idempotent via TeamSeeder.updateOrCreate)
php artisan migrate --force
php artisan db:seed --force

exec /usr/bin/supervisord -c /etc/supervisord.conf
```

### Railway integration

- **Build:** Railway dashboard → service settings → **Root Directory:** `/`, **Dockerfile Path:** `docker/api.Dockerfile`. (Or check in a `railway.json` to lock this — left as an optional polish.)
- **Postgres:** Add Railway's managed Postgres plugin; Railway injects `DATABASE_URL`. Laravel 11+ reads `DATABASE_URL` natively, so `DB_HOST` / `DB_DATABASE` / etc. don't need to be set.
- **Other env vars (set manually in Railway dashboard):**
  - `APP_KEY` — generated locally with `php artisan key:generate --show`, pasted in
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<railway-app>.up.railway.app`
  - `LOG_CHANNEL=stderr` (Railway aggregates stderr → dashboard logs)
- **Healthcheck endpoint:** `GET /up` (Laravel 11+ built-in). Configure Railway's healthcheck to this path.
- **Image size target:** < 150 MB compressed.

### Why nginx + php-fpm + supervisor and not FrankenPHP / Octane

FrankenPHP (single binary) is a viable modern alternative and worth mentioning as a future-state option. The default here is the "boring + correct" Laravel production stack because:
- Wider community knowledge → easier interview discussion
- Mature opcache + supervisor patterns
- No risk of Octane-specific bugs (memory leaks across requests, etc.)

This is a deliberate trade against modernity for predictability.

## Part 3 — Frontend production handoff (Vercel)

The frontend goes to Vercel; no Dockerfile is involved in production. `docker/web.dev.Dockerfile` is **local-dev only**.

**Optional `vercel.json` at repo root** (explicit > implicit):

```json
{
  "buildCommand": "nx run web:build",
  "outputDirectory": "apps/web/dist",
  "installCommand": "npm ci",
  "framework": "vite"
}
```

**SDK strategy:**

The generated frontend SDK (`libs/api-sdk/src/generated/`) is **committed to git**. Vercel doesn't run PHP, so it cannot regenerate the SDK from `scramble:export`. The contract:

- Developer changes the API → runs `npm run compose:sdk` locally → commits the SDK diff → pushes.
- Vercel build step only runs `nx run web:build`; the SDK is already in the tree.
- The follow-up CI/CD spec adds a `git diff --exit-code libs/api-sdk/src/generated/` guard in the API workflow so a PR with API changes but no SDK regen fails CI.

**Vercel env vars** (dashboard, per environment):
- `VITE_API_URL=https://<railway-app>.up.railway.app` (production)
- Optional preview env override pointing at a staging Railway URL.

## Part 4 — Environment, secrets, ignore

### `.dockerignore` (repo root)

```
node_modules/
**/node_modules/
**/vendor/
**/.git/
**/dist/
**/test-output/
**/storage/logs/*
**/storage/framework/cache/*
**/storage/framework/sessions/*
**/storage/framework/views/*
.env
.env.*
!.env.docker.example
!.env.example
**/coverage/
**/.idea/
**/.vscode/
**/.DS_Store
```

This shrinks the build context dramatically — `node_modules/` and `vendor/` are >1 GB combined — and prevents secrets in `.env` files from ever entering an image.

### `apps/api/.env.docker.example`

```env
APP_NAME="Champions League Mini Fixture"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=champions_league
DB_USERNAME=app
DB_PASSWORD=secret

LOG_CHANNEL=stack
LOG_LEVEL=debug
```

`compose:env-init` copies this to `apps/api/.env` if absent, then runs `php artisan key:generate` to fill `APP_KEY`.

### `apps/web/.env.docker.example`

```env
VITE_API_URL=http://localhost:8000
```

Same copy pattern — `compose:env-init` ensures `apps/web/.env` exists.

### Secrets matrix

| Environment | Mechanism | Notes |
|---|---|---|
| Local dev | `.env` files (gitignored), seeded from `.env.docker.example` | Hardcoded `app`/`secret` for Postgres — acceptable for local dev only |
| Railway (prod) | Railway dashboard → service → Variables | `APP_KEY` set manually before first deploy; `DATABASE_URL` injected by Postgres plugin |
| Vercel (prod) | Vercel dashboard → project → Environment Variables | `VITE_API_URL` only |
| Vercel previews | Vercel preview env override | Optional; point at staging Railway URL |

### `.gitignore` updates

Ensure these are present (most likely already there):

```
.env
.env.local
apps/*/.env
!.env.example
!apps/*/.env.example
!apps/*/.env.docker.example
```

## Part 5 — Documentation

Append a new section to `README.md`:

> ## Local development (Podman)
>
> ### Prerequisites
> - Podman 4.7+ (`brew install podman` on macOS)
> - `podman machine init && podman machine start` (macOS first-time)
>
> ### One-command setup
> ```sh
> npm run compose:setup
> ```
>
> After ~60s: API at http://localhost:8000, Web at http://localhost:4200, Postgres at localhost:5432.
>
> ### Daily flow
> ```sh
> npm run compose:up      # start
> npm run compose:logs    # tail logs
> npm run compose:down    # stop (data persists)
> ```
>
> ### Common tasks
> ```sh
> npm run compose:migrate          # apply new migrations
> npm run compose:seed             # re-seed (idempotent)
> npm run compose:test:api         # run Pest
> npm run compose:sdk              # regen OpenAPI + frontend SDK
> npm run compose:migrate-fresh    # destructive reset + reseed
> ```
>
> ## Production deployment
>
> - **Frontend:** Vercel auto-builds via `nx run web:build` on push to `main`. Set `VITE_API_URL` in env vars.
> - **API + Postgres:** Railway builds `docker/api.Dockerfile`. Postgres-managed plugin injects `DATABASE_URL`. Set `APP_KEY` once before first deploy.

## Part 6 — Verification (smoke matrix)

Used at the end of implementation to confirm the work is correct:

| Check | Command | Expected |
|---|---|---|
| Compose cold start | `npm run compose:setup` from a clean repo | All services healthy in < 90s |
| API alive | `curl http://localhost:8000/up` | HTTP 200 |
| Web served | `curl -I http://localhost:4200` | HTTP 200, Vite dev server headers |
| Web HMR | Edit `LandingPage.vue`, observe browser | Auto-refresh fires |
| API hot reload | Edit `apps/api/app/...`, next request | New behavior live (no manual restart) |
| Seed | `podman exec api psql ... -c "SELECT name FROM teams"` | 4 rows: PSG, Bayern, Arsenal, Atletico |
| Pest | `npm run compose:test:api` | All tests green |
| SDK regen | `npm run compose:sdk` after a controller change | Diff in `libs/api-sdk/src/generated/` |
| Prod build | `podman build -f docker/api.Dockerfile -t cl-api .` (build context is repo root so the Dockerfile can `COPY apps/api/...` and `COPY docker/api/...`) | Image builds; size < 150 MB compressed |
| Prod run | `podman run --rm -p 8080:8080 --env-file .env.prod-test cl-api` | Container boots, `curl http://localhost:8080/up` → 200 |

(Prod run needs a temporary `.env.prod-test` with `APP_KEY` + a reachable test Postgres — not committed.)

## Out of scope (explicit handoff)

These belong to the follow-up CI/CD spec, not this one:

- GitHub Actions workflows (lint, type-check, test, coverage gate).
- SDK drift guard in CI.
- Image build + registry push.
- Railway preview environments tied to PRs.
- Staging URL for Vercel previews.
- Vulnerability scanning (Trivy, Snyk, Dependabot).
- Automatic migration rollback on deploy failure.
