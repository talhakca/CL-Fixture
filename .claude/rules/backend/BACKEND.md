# Backend Architecture Rules

## Stack
- **Framework:** Laravel 11+
- **Database:** PostgreSQL
- **Testing:** Pest
- **Architecture:** Repository Pattern + Service Layer

---

## Layer Responsibilities

```
Request → Controller → Service → Repository → Model
Response ← Controller ← Service ← Repository ← Model
```

### Controllers
- Controllers handle **only request and response** — nothing else
- No business logic in controllers
- No database queries in controllers
- No model access in controllers (except via service)
- Controllers call one service method per action and return the result

```php
// ✅ Correct
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);
        return response()->json($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());
        return response()->json($user, 201);
    }
}

// ❌ Wrong — logic in controller
class UserController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $user = User::find($id);           // No direct model access
        $user->last_seen = now();          // No logic
        $user->save();                     // No persistence
        return response()->json($user);
    }
}
```

---

### Services (`app/Services/`)
- Services contain all **business logic**
- Services orchestrate repositories and other services
- Services must not directly query the database — use repositories
- A single service method must not exceed **50 lines**
- Services are injected via Laravel's service container

```php
// ✅ Correct
class UserService
{
    public function __construct(private UserRepository $userRepository) {}

    public function create(array $data): User
    {
        $data['password'] = bcrypt($data['password']);
        return $this->userRepository->create($data);
    }

    public function findById(int $id): User
    {
        return $this->userRepository->findOrFail($id);
    }
}
```

---

### Repositories (`app/Repositories/`)
- Repositories handle all **database interaction**
- Repositories use Eloquent models internally
- Repositories must not contain business logic
- Every repository implements a corresponding interface

```php
// ✅ Interface
interface UserRepositoryInterface
{
    public function findOrFail(int $id): User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
}

// ✅ Implementation
class UserRepository implements UserRepositoryInterface
{
    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);
        $user->update($data);
        return $user->fresh();
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}
```

---

### Models (`app/Models/`)
- Models define **Eloquent relationships, casts, and fillable fields only**
- No business logic in models
- No custom query scopes that contain business rules
- Keep models thin

```php
// ✅ Correct
class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

---

## Function / Method Rules

- **No method may exceed 50 lines**
- Single Responsibility Principle — one method does one thing
- If a method grows beyond 50 lines, extract private helper methods
- Use descriptive method names: `createUserWithRole()` not `create2()`

---

## Request Validation

- All validation lives in **Form Request classes** (`app/Http/Requests/`)
- Controllers use typed Form Requests, never `$request->validate()` inline

```php
// ✅ Correct
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }
}
```

---

## API Response Format

All API responses follow this consistent structure:

```json
// Success
{
  "data": { ... },
  "message": "Operation successful"
}

// Error
{
  "message": "Resource not found",
  "errors": { ... }
}

// Paginated
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

Use **API Resources** (`app/Http/Resources/`) for all responses — never return raw models.

```php
// ✅ Correct
return new UserResource($user);
return UserResource::collection($users);

// ❌ Wrong
return response()->json($user);
```

---

## Folder Structure

```
app/
├── Http/
│   ├── Controllers/      # Request/response only
│   ├── Requests/         # Form request validation
│   └── Resources/        # API response transformation
├── Models/               # Eloquent models — thin
├── Repositories/
│   ├── Interfaces/       # Repository contracts
│   └── ...               # Implementations
├── Services/             # Business logic
└── Providers/
    └── RepositoryServiceProvider.php  # Bind interfaces to implementations
```

---

## Dependency Injection

Bind repository interfaces in a dedicated service provider:

```php
// app/Providers/RepositoryServiceProvider.php
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
```

Register it in `bootstrap/providers.php`.

---

## Type Safety

- **Never use `mixed`** as a parameter, property, or return type. If a value is unknown, narrow it with a typed DTO, an enum, or a value object before passing it downstream.
- **Always declare types on parameters and return values.** A method without a return type is a bug, not a shortcut.
- **No bare `array`.** Either use a typed shape (`array{id: int, name: string}`), a typed collection (`Collection<int, User>`), or a dedicated DTO class. Generic arrays leak untyped data through the call stack.
- **Strict types** at the top of every PHP file: `declare(strict_types=1);`
- **Property types** are required on all class properties (`public int $id`, not `public $id`).
- **Form Requests** must define a `validated()` shape via a typed return or DTO; do not pass raw `$request->all()` arrays past the controller.
- **API Resources** define the response contract — they are the type boundary. Never return raw model arrays or `mixed` from a controller.
- The OpenAPI spec consumed by the frontend SDK is **derived from your types**. Untyped or `mixed` returns produce useless `any`-equivalents in the generated SDK and break the contract for the frontend.
- Use **enums** (PHP 8.1+) for fixed sets of values — never `string` with magic constants.

```php
// ✅ Correct — strict, typed end-to-end
declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\CreateUserData;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

class UserService
{
    public function __construct(private UserRepositoryInterface $userRepository) {}

    public function create(CreateUserData $data): User
    {
        return $this->userRepository->create($data);
    }
}

// ❌ Wrong — untyped, leaks mixed
class UserService
{
    public function create($data)              // no parameter type
    {                                           // no return type
        return $this->userRepository->create($data); // $data shape unknown
    }
}
```

### Tooling

- **Larastan / PHPStan level 8** is the target. Anything less is opting out of type safety. CI runs PHPStan; do not introduce ignored errors without a justification comment.
- Avoid `@phpstan-ignore` and `@var` casts unless you can name the specific external limitation forcing it.

---

## Summary Checklist

| Rule | Enforced |
|------|----------|
| No logic in controllers | ✅ |
| No DB queries outside repositories | ✅ |
| No business logic in repositories | ✅ |
| No logic in models | ✅ |
| Methods max 50 lines | ✅ |
| Validation in Form Requests | ✅ |
| Responses via API Resources | ✅ |
| Repository pattern with interfaces | ✅ |
| `declare(strict_types=1)` in every PHP file | ✅ |
| No `mixed`, no bare `array` — DTOs / typed shapes | ✅ |
| Typed params + return types on every method | ✅ |
| Enums for fixed value sets | ✅ |
| PHPStan level 8 clean | ✅ |
