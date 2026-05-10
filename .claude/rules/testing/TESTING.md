# Testing Rules

## Coverage Requirement
- **Minimum 80% code coverage** across both frontend and backend
- CI/CD pipeline will fail if coverage drops below 80%

---

## Backend — Pest (Laravel)

### Setup
```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

### What to Test
| Layer | Test Type | Tool |
|-------|-----------|------|
| Controllers | Feature (HTTP) tests | Pest + Laravel HTTP |
| Services | Unit tests | Pest |
| Repositories | Unit tests (with in-memory DB) | Pest + RefreshDatabase |
| Models | Unit tests | Pest |

### Rules
- Controllers are tested via **feature tests** (full HTTP request/response cycle)
- Services are tested in **isolation** — mock repositories
- Repositories are tested against a **real SQLite test database** (not PostgreSQL)
- Never test implementation details — test behavior and outcomes
- Use **factories** for all test data — never hardcode

### Feature Test Example (Controller)
```php
// tests/Feature/UserControllerTest.php
use App\Models\User;

it('returns a user by id', function () {
    $user = User::factory()->create();

    $response = $this->getJson("/api/users/{$user->id}");

    $response->assertOk()
             ->assertJsonPath('data.id', $user->id)
             ->assertJsonPath('data.email', $user->email);
});

it('returns 404 for non-existent user', function () {
    $this->getJson('/api/users/99999')
         ->assertNotFound();
});
```

### Unit Test Example (Service)
```php
// tests/Unit/UserServiceTest.php
use App\Services\UserService;
use App\Repositories\Interfaces\UserRepositoryInterface;

it('hashes password when creating user', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('create')
               ->once()
               ->withArgs(fn($data) => password_verify('secret', $data['password']))
               ->andReturn(new User());

    $service = new UserService($repository);
    $service->create(['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret']);
});
```

### Running Tests
```bash
php artisan test --coverage --min=80
```

### Configuration (`phpunit.xml`)
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

---

## Frontend — Vitest + Vue Testing Library

### Setup
```bash
npm install -D vitest @vue/test-utils @testing-library/vue @testing-library/jest-dom jsdom @vitest/coverage-v8
```

### What to Test
| Layer | Test Type |
|-------|-----------|
| Dumb Components | Render + emit tests |
| Page Components | Integration — machine + component |
| XState Machines | Unit — state transition tests |
| Services | Unit — mock HTTP, test return values |
| Utils | Unit — pure function tests |

### Rules
- Components are tested for **rendered output and emitted events** — not internal state
- Pages are tested with a **mocked XState machine** — not real API calls
- Machines are tested using XState's `createActor` — assert state transitions
- Services are tested with **mocked Axios** — never hit a real API in tests
- No snapshot tests — they are brittle and low value

### Component Test Example (Dumb Component)
```ts
// tests/components/UserCard.test.ts
import { render, fireEvent } from '@testing-library/vue'
import UserCard from '@/components/UserCard.vue'

const user = { id: 1, name: 'John Doe', email: 'john@example.com' }

it('renders user name and email', () => {
  const { getByText } = render(UserCard, { props: { user } })
  expect(getByText('John Doe')).toBeTruthy()
  expect(getByText('john@example.com')).toBeTruthy()
})

it('emits delete event with user id on button click', async () => {
  const { getByRole, emitted } = render(UserCard, { props: { user } })
  await fireEvent.click(getByRole('button', { name: /delete/i }))
  expect(emitted().delete[0]).toEqual([1])
})
```

### Machine Test Example (XState)
```ts
// tests/machines/userMachine.test.ts
import { createActor } from 'xstate'
import { userMachine } from '@/machines/userMachine'

it('transitions from idle to loading on FETCH event', () => {
  const actor = createActor(userMachine).start()
  expect(actor.getSnapshot().value).toBe('idle')

  actor.send({ type: 'FETCH' })
  expect(actor.getSnapshot().value).toBe('loading')
})

it('transitions to success state after data is loaded', async () => {
  const actor = createActor(userMachine).start()
  actor.send({ type: 'FETCH' })
  await new Promise(r => setTimeout(r, 100)) // wait for actor
  expect(actor.getSnapshot().value).toBe('success')
})
```

### Service Test Example
```ts
// tests/services/userService.test.ts
import { userService } from '@/services/userService'
import { http } from '@/services/http'
import { vi } from 'vitest'

vi.mock('@/services/http')

it('fetches a user by id', async () => {
  const mockUser = { id: 1, name: 'John' }
  vi.mocked(http.get).mockResolvedValue({ data: mockUser })

  const result = await userService.getUser(1)
  expect(result).toEqual(mockUser)
  expect(http.get).toHaveBeenCalledWith('/users/1')
})
```

### Running Tests
```bash
npm run test          # watch mode
npm run test:coverage # with coverage report
```

### `vitest.config.ts`
```ts
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    coverage: {
      provider: 'v8',
      thresholds: { global: { lines: 80, functions: 80, branches: 80, statements: 80 } },
      exclude: ['node_modules/', 'src/main.ts', 'src/router/']
    }
  }
})
```

---

## CI Coverage Gate

Both frontend and backend coverage checks run in CI. A pull request **cannot be merged** if either drops below 80%. See `.github/workflows/ci.yml` for the pipeline configuration.
