<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tournament;
use App\Repositories\Interfaces\TournamentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class TournamentRepository implements TournamentRepositoryInterface
{
    /**
     * @return Collection<int, Tournament>
     */
    public function all(): Collection
    {
        return Tournament::query()->orderByDesc('started_at')->get();
    }

    public function findOrFail(int $id): Tournament
    {
        return Tournament::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Tournament
    {
        return Tournament::query()->create($attributes);
    }

    public function delete(Tournament $tournament): bool
    {
        return (bool) $tournament->delete();
    }

    /**
     * Used to compute the default name for a new tournament when the
     * caller doesn't supply one ("Season 1", "Season 2", ...).
     */
    public function nextSeasonNumber(): int
    {
        return Tournament::query()->count() + 1;
    }
}
