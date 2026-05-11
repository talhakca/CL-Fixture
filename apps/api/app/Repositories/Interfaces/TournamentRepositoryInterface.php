<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;

interface TournamentRepositoryInterface
{
    /**
     * @return Collection<int, Tournament>
     */
    public function all(): Collection;

    public function findOrFail(int $id): Tournament;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Tournament;

    public function delete(Tournament $tournament): bool;

    public function nextSeasonNumber(): int;
}
