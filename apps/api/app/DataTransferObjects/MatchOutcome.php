<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\Fixture;

/**
 * A played match reduced to the four numbers needed to compute a league
 * table. Used as the StandingsService input so the same code path serves:
 *   - real standings (from played Fixtures, mapped via fromFixture())
 *   - Monte Carlo synthetic standings (from in-memory simulator output)
 *
 * Avoids cloning Eloquent models 60k+ times per Monte Carlo run.
 */
final readonly class MatchOutcome
{
    public function __construct(
        public int $homeTeamId,
        public int $awayTeamId,
        public int $homeGoals,
        public int $awayGoals,
    ) {}

    /**
     * Build from a played Fixture. Caller must guarantee played_at is set
     * (i.e., goals are non-null) — we assert via the int cast.
     */
    public static function fromFixture(Fixture $fixture): self
    {
        return new self(
            homeTeamId: $fixture->home_team_id,
            awayTeamId: $fixture->away_team_id,
            homeGoals: (int) $fixture->home_goals,
            awayGoals: (int) $fixture->away_goals,
        );
    }
}
