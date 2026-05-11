<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Fixture;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;

interface FixtureRepositoryInterface
{
    /**
     * @return Collection<int, Fixture>
     */
    public function all(Tournament $tournament): Collection;

    /**
     * Played-only fixtures, ordered by week ASC.
     *
     * @return Collection<int, Fixture>
     */
    public function played(Tournament $tournament): Collection;

    /**
     * Pending fixtures (not yet played), ordered by week ASC.
     *
     * @return Collection<int, Fixture>
     */
    public function pending(Tournament $tournament): Collection;

    /**
     * @return Collection<int, Fixture>
     */
    public function forWeek(Tournament $tournament, int $week): Collection;

    /**
     * Single-fixture lookup by primary key. The fixture knows its own
     * tournament — no scope param needed.
     */
    public function findOrFail(int $id): Fixture;

    /**
     * Bulk insert. Each row must already include `tournament_id`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function bulkCreate(array $rows): void;

    public function recordResult(Fixture $fixture, int $homeGoals, int $awayGoals): Fixture;

    public function clearScores(Tournament $tournament): void;

    public function exists(Tournament $tournament): bool;

    public function lastPlayedWeek(Tournament $tournament): int;

    public function totalWeeks(Tournament $tournament): int;
}
