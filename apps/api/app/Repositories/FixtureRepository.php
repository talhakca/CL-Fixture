<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Fixture;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class FixtureRepository implements FixtureRepositoryInterface
{
    /**
     * @return Collection<int, Fixture>
     */
    public function all(Tournament $tournament): Collection
    {
        return $this->scopedQuery($tournament)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Fixture>
     */
    public function played(Tournament $tournament): Collection
    {
        return $this->scopedQuery($tournament)
            ->whereNotNull('played_at')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Fixture>
     */
    public function pending(Tournament $tournament): Collection
    {
        return $this->scopedQuery($tournament)
            ->whereNull('played_at')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Fixture>
     */
    public function forWeek(Tournament $tournament, int $week): Collection
    {
        return $this->scopedQuery($tournament)
            ->where('week', $week)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int $id): Fixture
    {
        return Fixture::query()->findOrFail($id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function bulkCreate(array $rows): void
    {
        $now = now();
        $stamped = array_map(
            static fn (array $row): array => [
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $rows,
        );

        Fixture::query()->insert($stamped);
    }

    public function recordResult(Fixture $fixture, int $homeGoals, int $awayGoals): Fixture
    {
        $fixture->fill([
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played_at' => now(),
        ])->save();

        return $fixture->fresh(['homeTeam', 'awayTeam']) ?? $fixture;
    }

    public function clearScores(Tournament $tournament): void
    {
        $this->scopedQuery($tournament)->update([
            'home_goals' => null,
            'away_goals' => null,
            'played_at' => null,
        ]);
    }

    public function exists(Tournament $tournament): bool
    {
        return $this->scopedQuery($tournament)->exists();
    }

    public function lastPlayedWeek(Tournament $tournament): int
    {
        return (int) $this->scopedQuery($tournament)
            ->whereNotNull('played_at')
            ->max('week');
    }

    public function totalWeeks(Tournament $tournament): int
    {
        return (int) $this->scopedQuery($tournament)->max('week');
    }

    private function scopedQuery(Tournament $tournament): \Illuminate\Database\Eloquent\Builder
    {
        return Fixture::query()->where('tournament_id', $tournament->id);
    }
}
