<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Prediction;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;

interface PredictionRepositoryInterface
{
    /**
     * @return Collection<int, Prediction>
     */
    public function forWeek(Tournament $tournament, int $week): Collection;

    public function latestWeek(Tournament $tournament): int;

    /**
     * @param  array<int, array{team_id: int, championship_probability: float}>  $rows
     */
    public function upsertSnapshot(Tournament $tournament, int $week, array $rows): void;

    public function deleteFromWeek(Tournament $tournament, int $week): int;

    public function deleteAll(Tournament $tournament): int;
}
