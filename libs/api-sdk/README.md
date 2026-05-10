# @champions-league-fixture/api-sdk

Typed HTTP client for the Laravel API in `apps/api`. The SDK is **generated** from the backend's OpenAPI specification — do not hand-edit anything under `src/generated/`.

## Pipeline

```
apps/api  ──(scramble)──▶  storage/api-docs/openapi.json  ──(orval)──▶  libs/api-sdk/src/generated
```

1. **scramble** (`dedoc/scramble`, Laravel) introspects controllers + Form Requests + API Resources and writes an OpenAPI 3.1 document.
2. **orval** consumes that document and emits typed `axios-functions` client code.
3. The web app imports from `@champions-league-fixture/api-sdk`. SDK calls are wrapped by services in `apps/web/src/services/` per `FRONTEND.md`.

## Generating the SDK

From the workspace root:

```bash
# Re-export the OpenAPI spec from Laravel
npx nx run api-sdk:spec:export

# Re-generate the TypeScript client
npx nx run api-sdk:generate

# Both, in order
npx nx run api-sdk:sync

# Convenience alias
npm run sdk:gen
```

`spec:export` requires PHP 8.3+ and the API's composer dependencies installed (`composer install` inside `apps/api`). `generate` is pure Node and only needs `apps/api/storage/api-docs/openapi.json` to exist.

## Adding or changing an endpoint

1. Add the route + controller in `apps/api` (follow `BACKEND.md` — typed Form Request, API Resource for the response).
2. Run `npx nx run api-sdk:sync`. Scramble re-derives the spec from your types; orval re-generates the SDK.
3. The generated function appears in `src/generated/api.ts` with full types from the schemas in `src/generated/schemas/`.
4. Wrap it in a service under `apps/web/src/services/` — components, pages, and machines must not import the SDK directly.

If the spec changes shape, **commit the generated diff** alongside the backend change. Reviewers should see both.

## Custom HTTP layer

`src/http.ts` is the single Axios instance for every SDK call:

- `sdkAxios` — the shared Axios instance.
- `configureSdk({ baseURL, headers })` — called once from `apps/web/src/services/sdk.ts` during app bootstrap to wire the API base URL and auth headers.
- `customInstance<T>()` — orval's `override.mutator`. Every generated call routes through this, so interceptors (auth, error normalization, telemetry) added here apply globally.

Do not write a second Axios instance elsewhere in the app.

## Layout

```
libs/api-sdk/
├── src/
│   ├── http.ts             # Axios instance + custom mutator
│   ├── http.spec.ts        # Mutator unit tests
│   ├── index.ts            # Public barrel
│   └── generated/          # ⚠️ orval output — do not edit
│       ├── api.ts          # Typed endpoint functions
│       └── schemas/        # Typed request/response models
├── orval.config.ts         # Generation config (split mode, custom mutator)
├── project.json            # Nx targets: generate / spec:export / sync / test / typecheck / lint
├── tsconfig*.json
└── vitest.config.mts
```

## Type-safety contract

The SDK's value is end-to-end typing. Per the project rules:

- **Backend**: typed Form Requests + API Resources + no `mixed` returns → scramble produces a precise spec.
- **SDK**: orval emits exact TypeScript types from that spec.
- **Frontend**: services and XState machines consume those types directly — never `any`, and never re-typed by hand.

Anywhere this chain is broken (a `mixed` PHP return, a hand-written interface duplicating an SDK type, an `any` cast in a service) is a bug.
