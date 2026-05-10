# Frontend Architecture Rules

## Stack
- **Framework:** Vue 3 (Composition API)
- **State Management:** XState v5
- **Package Manager:** npm
- **Testing:** Vitest + Vue Testing Library
- **HTTP Client:** Axios (called only from XState services/actors)

---

## Component Rules

### Dumb Components (components/)
- Components are **purely presentational** — they carry no logic
- A component only receives data via **props (input)** and emits events via **emits (output)**
- No API calls inside components
- No XState machine references inside components
- No business logic, calculations, or transformations inside components
- No direct store access inside components

```vue
<!-- ✅ Correct -->
<script setup lang="ts">
defineProps<{ user: User }>()
defineEmits<{ (e: 'delete', id: number): void }>()
</script>

<!-- ❌ Wrong -->
<script setup lang="ts">
const user = await fetchUser() // No API calls
const store = useUserStore()   // No store access
</script>
```

### Page Components (pages/)
- Pages are the **only components that can contain logic**
- Pages connect XState machines to dumb components
- Pages pass data down as props and handle emitted events
- Pages orchestrate which machine/actor to use
- Pages must not make direct API calls — delegate to XState services

```vue
<!-- ✅ Correct page -->
<script setup lang="ts">
import { useMachine } from '@xstate/vue'
import { userMachine } from '@/machines/userMachine'
import UserCard from '@/components/UserCard.vue'

const { state, send } = useMachine(userMachine)

const handleDelete = (id: number) => {
  send({ type: 'DELETE_USER', id })
}
</script>

<template>
  <UserCard :user="state.context.user" @delete="handleDelete" />
</template>
```

---

## Function Rules

- **A function must not exceed 50 lines**
- If a function grows beyond 50 lines, break it into smaller, single-responsibility functions
- Functions that contain business logic must live in **services** (`src/services/`)
- Pure utility/helper functions live in `src/utils/`

```ts
// ✅ Correct — logic in service
// src/services/userService.ts
export const formatUserDisplayName = (user: User): string => {
  return `${user.firstName} ${user.lastName}`.trim()
}

// ❌ Wrong — logic inside component
const displayName = computed(() => `${user.firstName} ${user.lastName}`.trim())
```

---

## Data Layer — XState

- **XState is the single source of truth for all data and async operations**
- All API calls must be made inside XState **actors/services** — never from components or pages directly
- State transitions are driven by **events** — never mutate state directly
- Each feature/domain has its own **machine** (`src/machines/`)
- Shared/global state lives in a **root machine** or **parent actor**

```ts
// ✅ Correct — API call inside XState actor
import { createMachine, assign, fromPromise } from 'xstate'
import { userService } from '@/services/userService'

export const userMachine = createMachine({
  id: 'user',
  initial: 'idle',
  context: { user: null, error: null },
  states: {
    idle: {
      on: { FETCH: 'loading' }
    },
    loading: {
      invoke: {
        src: fromPromise(() => userService.getUser()),
        onDone: {
          target: 'success',
          actions: assign({ user: ({ event }) => event.output })
        },
        onError: {
          target: 'error',
          actions: assign({ error: ({ event }) => event.error })
        }
      }
    },
    success: {
      on: { REFETCH: 'loading' }
    },
    error: {
      on: { RETRY: 'loading' }
    }
  }
})

// ❌ Wrong — API call directly in page
const user = await axios.get('/api/user') // Never do this in a component/page
```

---

## Folder Structure

```
apps/web/src/
├── components/       # Dumb components only — no logic
├── pages/            # Page components — orchestrate machines + components
├── machines/         # XState machines — one per domain/feature
├── services/         # Business logic, API calls, data transformation
├── utils/            # Pure utility/helper functions
├── types/            # TypeScript interfaces and types
└── router/           # Vue Router configuration
```

---

## API Communication

- All HTTP requests go through `src/services/`
- Services are called **only from XState actors**
- Use Axios with a configured base instance (`src/services/http.ts`)
- Never use `fetch` or `axios` directly in components, pages, or machines — always via a service function

```ts
// src/services/http.ts
import axios from 'axios'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { 'Content-Type': 'application/json' }
})

// src/services/userService.ts
import { http } from './http'
import type { User } from '@/types'

export const userService = {
  getUser: async (id: number): Promise<User> => {
    const { data } = await http.get(`/users/${id}`)
    return data
  },
  deleteUser: async (id: number): Promise<void> => {
    await http.delete(`/users/${id}`)
  }
}
```

---

## Type Safety

- **Never use `any`.** It silently disables type checking and erases the value of TypeScript.
- **Avoid `unknown` in public APIs.** It is acceptable as a temporary boundary for truly opaque inputs (third-party JSON, error catch clauses), but must be narrowed via a type guard or schema validator before use — never passed downstream untouched.
- Every function declares **explicit parameter and return types**. Inference is fine inside a function body but never on its signature.
- All HTTP/SDK responses must be **typed via the generated SDK** (`@champions-league-fixture/api-sdk`). Do not retype them by hand.
- Prefer **discriminated unions** over optional fields with implicit semantics (`type Result = { kind: 'ok'; data: T } | { kind: 'error'; error: E }`).
- XState `context` and `events` must be fully typed via `setup({ types: { context, events } })`. No untyped `event.data`.
- Props and emits use generic `defineProps<T>()` / `defineEmits<T>()` with explicit interfaces — no runtime prop validators.
- `as` casts and `!` non-null assertions are **last-resort escapes**. If you reach for one, leave a one-line comment explaining the invariant that justifies it.
- TypeScript runs with `strict: true` (already configured in `tsconfig.base.json`). Do not weaken it per-file with `// @ts-ignore` or `// @ts-expect-error` without a linked issue.

```ts
// ✅ Correct — typed via generated SDK + discriminated union
import { health, type Health200 } from '@champions-league-fixture/api-sdk'

type FetchResult<T> = { kind: 'ok'; data: T } | { kind: 'error'; message: string }

const checkHealth = async (): Promise<FetchResult<Health200>> => {
  try {
    return { kind: 'ok', data: await health() }
  } catch (error) {
    return { kind: 'error', message: error instanceof Error ? error.message : 'unknown' }
  }
}

// ❌ Wrong
const checkHealth = async (): Promise<any> => {           // no `any`
  const res: unknown = await axios.get('/health')         // unknown leaking out
  return res
}
```

---

## Event-Driven Principles

- Every user interaction maps to an **XState event**
- UI never directly triggers side effects — it sends events to machines
- Events are descriptive and past/imperative tense: `FETCH_USER`, `DELETE_USER`, `FORM_SUBMITTED`
- Guards, actions, and actors handle all side effects inside machines

---

## Summary Checklist

| Rule | Enforced |
|------|----------|
| Components carry no logic | ✅ |
| Only pages can access machines | ✅ |
| No API calls outside XState actors | ✅ |
| Functions max 50 lines | ✅ |
| Logic lives in services | ✅ |
| XState is the data layer | ✅ |
| No direct store/API access in components | ✅ |
| No `any`; `unknown` only at boundaries, narrowed before use | ✅ |
| Explicit param + return types on every function | ✅ |
| SDK types are the source of truth for API shapes | ✅ |
