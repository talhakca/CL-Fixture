<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Prediction;
use App\Models\Tournament;
use App\Repositories\Interfaces\PredictionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PredictionRepository implements PredictionRepositoryInterface
{
    /**
     * @return Collection<int, Prediction>
     */
    public function forWeek(Tournament $tournament, int $week): Collection
    {
        return $this->scopedQuery($tournament)
            ->where('week', $week)
            ->with('team')
            ->orderByDesc('championship_probability')
            ->orderBy('team_id')
            ->get();
    }

    public function latestWeek(Tournament $tournament): int
    {
        return (int) $this->scopedQuery($tournament)->max('week');
    }

    /**
     * @param  array<int, array{team_id: int, championship_probability: float}>  $rows
     */
    public function upsertSnapshot(Tournament $tournament, int $week, array $rows): void
    {
        $now = now();

        DB::transaction(function () use ($tournament, $week, $rows, $now): void {
            $payload = array_map(
                static fn (array $row): array => [
                    'tournament_id' => $tournament->id,
                    'week' => $week,
                    'team_id' => $row['team_id'],
                    'championship_probability' => $row['championship_probability'],
                    'computed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $rows,
            );

            Prediction::query()->upsert(
                values: $payload,
                uniqueBy: ['tournament_id', 'week', 'team_id'],
                update: ['championship_probability', 'computed_at', 'updated_at'],
            );
        });
    }

    public function deleteFromWeek(Tournament $tournament, int $week): int
    {
        return $this->scopedQuery($tournament)->where('week', '>=', $week)->delete();
    }

    public function deleteAll(Tournament $tournament): int
    {
        return $this->scopedQuery($tournament)->delete();
    }

    private function scopedQuery(Tournament $tournament): \Illuminate\Database\Eloquent\Builder
    {
        return Prediction::query()->where('tournament_id', $tournament->id);
    }
}
