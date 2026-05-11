<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Team;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class TeamRepository implements TeamRepositoryInterface
{
    /**
     * @return Collection<int, Team>
     */
    public function all(): Collection
    {
        // Stable order keeps standings/predictions deterministic when teams
        // are tied — important for assertions in feature tests.
        return Team::query()->orderBy('id')->get();
    }

    public function findOrFail(int $id): Team
    {
        return Team::query()->findOrFail($id);
    }

    public function count(): int
    {
        return Team::query()->count();
    }
}
