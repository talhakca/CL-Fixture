<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only repository — teams are static seed data, never created/updated
 * via the API. Services depend on this interface so unit tests can swap it
 * with an in-memory fake.
 */
interface TeamRepositoryInterface
{
    /**
     * @return Collection<int, Team>
     */
    public function all(): Collection;

    public function findOrFail(int $id): Team;

    public function count(): int;
}
