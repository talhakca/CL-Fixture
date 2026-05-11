# Champions League Mini Fixture

A 4-team round-robin league simulator. Generate a season, play it out week by week (or all at once), see live standings rebuild as scores change, and watch a Monte Carlo championship-probability widget update after every result.

- **Live demo (web):** https://cl-fixture-api.vercel.app
- **API:** https://api-production-5bc0.up.railway.app/api
- **OpenAPI docs:** https://api-production-5bc0.up.railway.app/docs/api

---

## What's inside

| Layer | Tech | Path |
|---|---|---|
| API | Laravel 13 · PHP 8.4 · PostgreSQL 16 · Pest | `apps/api/` |
| Web | Vue 3 (Composition API) · XState 5 · Vite · Tailwind v4 · Vitest | `apps/web/` |
| Generated SDK | Orval (Axios) — TS client generated from the Laravel OpenAPI spec | `libs/api-sdk/` |
| UI library | shadcn-vue primitives + dumb domain components | `libs/ui/` |
| Container runtime | Podman compose (dev) · Multi-stage Dockerfile for Railway (prod) | `docker/` |
| Deploy | Vercel (FE static) · Railway (API + managed Postgres) | `vercel.json`, `docker/api.Dockerfile` |

---

## Quickstart

```sh
brew install podman                          # one time
podman machine init && podman machine start  # macOS only, one time
git clone <repo> && cd nx
npm install --legacy-peer-deps
npm run compose:setup                        # ~60–120s on first run
```

That's it. After setup:

- Web: http://localhost:4200
- API: http://localhost:8000/api (and `/up` for the healthcheck)
- Postgres: `psql -h localhost -U app champions_league` (password `secret`)

The setup script builds the api + web + postgres containers, runs `composer install` + `npm install` inside them, applies migrations, seeds the 4 teams (PSG, Bayern, Arsenal, Atletico), and generates a fresh `APP_KEY`.

---

## Repo map

```
apps/
├── api/                          Laravel API
│   ├── app/
│   │   ├── Http/Controllers/     Request → service. No business logic here.
│   │   ├── Http/Requests/        Form Request validation.
│   │   ├── Http/Resources/       Response shaping. Source of truth for API contract.
│   │   ├── Services/             Business logic: fixture gen, match sim, predictions, standings.
│   │   ├── Repositories/         All DB access. Interface + impl per aggregate.
│   │   ├── DataTransferObjects/  Typed data movement between layers.
│   │   ├── Models/               Eloquent — thin: relationships, casts, fillable only.
│   │   └── Exceptions/           Domain-specific exception types.
│   ├── database/
│   │   ├── migrations/           Schema. Postgres-targeted.
│   │   ├── factories/            Pest test data.
│   │   └── seeders/              TeamSeeder is idempotent (updateOrCreate).
│   ├── routes/api.php            /api/* routes.
│   └── tests/                    Pest tests (Feature + Unit).
├── web/                          Vue 3 frontend
│   └── src/
│       ├── pages/                Only place allowed to wire machines to components.
│       ├── machines/             XState machines, one per domain (fixture, prediction, …).
│       ├── services/             HTTP boundary — always called from XState actors.
│       ├── utils/                Pure helpers.
│       └── router/               Vue Router.
libs/
├── api-sdk/                      Generated TS client. Don't hand-edit src/generated.
└── ui/                           Dumb components — no logic, props in / emits out.
docker/                           All container files (compose + Dockerfiles + runtime configs).
.claude/rules/                    Architecture rules the codebase is held to (frontend/backend/testing).
```

---

## Architecture rules (the short version)

Lifted from `.claude/rules/` (full files there). The codebase is held to these strictly:

**Backend** (`.claude/rules/backend/BACKEND.md`)
- `Request → Controller → Service → Repository → Model`. Each layer has one job.
- Controllers do request/response only — no business logic, no DB queries.
- Services own business logic; repositories own DB access.
- No method > 50 lines.
- `declare(strict_types=1)` everywhere. No `mixed`. No bare `array` — typed DTO or `array{...}` shape.
- All HTTP responses go through API Resources, not raw models.
- Larastan / PHPStan level 8.

**Frontend** (`.claude/rules/frontend/FRONTEND.md`)
- XState is the single source of truth for data and async ops.
- Dumb components in `libs/ui/` and `apps/web/src/components/` have no logic — props in, emits out.
- Only pages (`apps/web/src/pages/`) wire machines to components.
- All HTTP calls live inside XState actors, never inside components.
- SDK types are the source of truth for API shapes — no hand-retyping.

**Testing** (`.claude/rules/testing/TESTING.md`)
- Pest for the API (Feature tests for controllers, Unit tests for services).
- Vitest + Vue Testing Library for the web.
- Coverage gate: 80% (CI enforced).

---

## Domain algorithms

Three pieces of math run the simulation. The interesting bits in plain words:

### 1. Fixture generation — Circle Method (Berger tables)

`apps/api/app/Services/FixtureGeneratorService.php`

Round-robin scheduling classic. Fix team[0] as a pivot, rotate the other n-1 teams around it; each round pairs `(working[i], working[n-1-i])`. After `n-1` rounds every pair has played once. We replay the same rounds home/away-swapped for the second leg.

For 4 teams: 3 rounds × 2 matches × 2 legs = **12 fixtures over 6 weeks**.

### 2. Match simulation — Poisson goals (Knuth sampler)

`apps/api/app/Services/MatchSimulatorService.php`

For each team:

```
λ = base_goals × attack_eff / (attack_eff + opponent_defense_eff)
```

`attack_eff` and `defense_eff` are the seeded team strengths plus uniform luck noise (`luck_amplitude × noise_range`); the home side gets a small bonus on attack only.

Goal counts are drawn from `Poisson(λ)` via **Knuth's sampler** — multiply uniform(0,1) draws until the running product drops below `e^(-λ)`, return iteration count − 1. Cheap for the small λ values football produces (~1–3 → 3–5 iterations).

Tunables (per-tournament override → global fallback in `apps/api/config/game.php`):
- `base_goals` — average goals per side
- `home_advantage` — attack bonus for the home team
- `luck_amplitude`, `noise_range` — noise scale

### 3. Championship odds — Monte Carlo

`apps/api/app/Services/PredictionService.php`

After each played week, simulate the rest of the season `N` times (default 10,000):

1. Freeze the played fixtures.
2. For every remaining fixture, play it via `MatchSimulatorService`.
3. Compute the final standings of the synthetic season.
4. Tally one win for whoever finished #1.
5. After N iterations, `championship_probability[team] = wins[team] / N`.

The Monte Carlo simulator owns its own `Randomizer(Mt19937)` instance — its RNG state can never bleed into the "real" play-week simulator that the UI triggers.

When the season is already over, every iteration picks the same champion and the math collapses to 100% / 0% naturally — no special case.

---

## The OpenAPI ↔ SDK chain

The frontend never hand-types API request/response shapes. The flow:

```
Laravel routes/controllers/DTOs → Scramble → openapi.json → Orval → libs/api-sdk/src/generated/*.ts
                  (php)                              (ts)
```

Concretely:

```sh
# Re-export OpenAPI from Laravel introspection
nx run api-sdk:spec:export          # writes apps/api/storage/api-docs/openapi.json

# Re-generate the TS SDK from the spec
nx run api-sdk:generate             # writes libs/api-sdk/src/generated/

# Both at once
npm run sdk:sync
# or, while running inside compose:
npm run compose:sdk
```

The generated SDK is **committed to git** because Vercel's build environment doesn't run PHP — the FE build needs the SDK to exist on disk. Workflow: change the API, run `npm run compose:sdk`, commit the diff alongside the API change.

---

## Local development (Podman)

### Daily flow

```sh
npm run compose:up      # start (detached)
npm run compose:logs    # tail combined logs
npm run compose:down    # stop (named volumes persist)
```

### Common tasks

```sh
npm run compose:migrate          # apply new migrations
npm run compose:seed             # re-seed teams (idempotent)
npm run compose:test:api         # run Pest inside the container
npm run compose:sdk              # regen OpenAPI + frontend SDK
npm run compose:migrate-fresh    # destructive reset + reseed
```

### How the dev stack stays out of your way

- `apps/api/` is bind-mounted into the API container — artisan picks up source changes on the next request.
- The whole workspace is bind-mounted into the web container — Vite HMR works against your editor's saves.
- `vendor/` and `node_modules/` ride on **named volumes** so macOS bind-mount perf isn't a factor and host/container PHP/Node versions can disagree without breaking each other.
- `apps/api/.env.docker.example` is read-only-mounted over `/app/.env` in the container so the dev stack's container-flavored config can't fight with the host's `.env` — you can keep running `php artisan serve` natively if you ever want to.

If `compose:setup` hangs, the most likely cause is host port conflicts on `5432`, `8000`, or `4200` — usually a leftover `php artisan serve` or local Postgres.

---

## Tests

```sh
npm run compose:test:api         # Pest, inside the api container
nx run web:test                  # Vitest, on the host (no container needed)
nx run ui:test                   # Vitest for libs/ui
nx run api-sdk:test              # Vitest for the SDK barrel + http instance
```

Backend tests run against an in-memory SQLite per `phpunit.xml`, not Postgres — they exercise the same Laravel migrations, just on the lighter driver for speed. Production and dev compose both use Postgres.

---

## Production deployment

### Frontend (Vercel)

Auto-deploys on push to `main`. Config in `vercel.json`:

```json
{
  "buildCommand": "cd ../.. && npx nx run web:build",
  "outputDirectory": "../../apps/web/dist",
  "installCommand": "cd ../.. && npm install --legacy-peer-deps",
  "framework": "vite"
}
```

Project env vars (Vercel dashboard):
- `VITE_API_URL=https://<railway-api-url>/api` — the trailing `/api` matters; the SDK builds paths like `/teams`, `/tournaments/{id}/...` without the prefix.

### API + Postgres (Railway)

Build target: `docker/api.Dockerfile` (multi-stage, ~190 MB). Settings:

- **Build → Builder:** `Dockerfile`
- **Build → Dockerfile Path:** `docker/api.Dockerfile`
- **Deploy → Healthcheck Path:** `/up`
- **Deploy → Healthcheck Timeout:** 60s (composer install + migrate can take a moment on cold start)

Variables (Railway dashboard):
- `DB_CONNECTION=pgsql` — required, Laravel 11+ defaults to sqlite otherwise
- `DATABASE_URL` — auto-injected when the Postgres plugin is attached as a service reference
- `APP_KEY=base64:…` — generate locally with `php artisan key:generate --show`
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}`
- `LOG_CHANNEL=stderr` (so logs land in Railway's log viewer, not in the container's filesystem)

The Dockerfile's `entrypoint.sh` runs `config:cache` + `route:cache` + `view:cache` + `migrate --force` + `db:seed --force` on every container start, then hands off to `supervisord` which runs nginx + php-fpm side by side.

### CORS

`apps/api/config/cors.php` allows the production Vercel origin, preview deploys (pattern match), and the local dev Vite server.

---

## Container layout (`docker/`)

```
docker/
├── compose.yml                  Local dev orchestration (postgres + api + web)
├── api.dev.Dockerfile           Dev API: php:8.4-cli-alpine + composer + artisan serve
├── api.dev.entrypoint.sh        Cold-start composer install if vendor/ is empty
├── web.dev.Dockerfile           Dev Web: node:22-alpine + Vite
├── api.Dockerfile               Prod API: multi-stage, nginx + php-fpm + supervisor
├── api/
│   ├── entrypoint.sh            Prod: cache + migrate + seed + supervisord
│   ├── nginx.conf               Listen 8080, fastcgi_pass 127.0.0.1:9000
│   ├── php-fpm.conf             Pool config
│   ├── supervisord.conf         Manages nginx + php-fpm as parallel processes
│   └── php-production.ini       opcache + memory + stderr logging
└── postgres/init.sql            First-boot DB bootstrap placeholder
```

Why two Dockerfiles instead of one multi-target image: dev uses `php artisan serve` (single process, file-watch-friendly) with bind-mounted source and a self-healing composer install; prod uses nginx + php-fpm with vendor baked into the image and opcache validation off. They have so little in common that one file would be more confusing than two.

---

## Known caveats

- **Lockfile peer-dep drift.** `@nx/vue@22.7.1` doesn't satisfy strict peer-deps against Vue 3.5; install paths use `--legacy-peer-deps` everywhere (local install, web Dockerfile, Vercel build). When Nx 23 lands this can come off.
- **Container vs host dev.** Both work, but don't run them at the same time — they fight over ports `8000`, `4200`, `5432`. Pick one.
- **SDK drift guard.** Forgetting to run `npm run compose:sdk` after an API change means the FE silently uses stale types. CI guard for this is in the next spec.
