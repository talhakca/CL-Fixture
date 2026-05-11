<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MatchOutcome;
use App\DataTransferObjects\StandingsRow;
use App\Models\Team;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Computes a Premier-League-style table from a set of played matches.
 *
 * Two entry points:
 *   - compute()      → builds the table from the live DB (real standings).
 *   - computeFor()   → builds from supplied teams + outcomes (used by
 *                       Monte Carlo to evaluate synthetic seasons without
 *                       touching the database).
 *
 * Tiebreaker chain (EPL):
 *   1. Points (W=3, D=1, L=0)
 *   2. Goal difference (GF − GA)
 *   3. Goals for
 *   4. Team name (alphabetical) — final deterministic fallback so tests
 *      can assert exact ordering.
 */
final class StandingsService
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly FixtureRepositoryInterface $fixtures,
    ) {}

    /**
     * Real standings as of NOW: pulls played fixtures + all teams from the
     * repositories and feeds them into the pure computation.
     *
     * @return list<StandingsRow>
     */
    public function compute(Tournament $tournament): array
    {
        $teams = $this->teams->all();
        $outcomes = $this->fixtures
            ->played($tournament)
            ->map(static fn ($fixture): MatchOutcome => MatchOutcome::fromFixture($fixture));

        return $this->computeFor($teams, $outcomes);
    }

    /**
     * Standings AS OF the end of $week. Filters played fixtures to only
     * those whose week ≤ $week, then runs the same aggregator. Powers the
     * frontend WeekProgressBar's "season replay" feature.
     *
     * @return list<StandingsRow>
     */
    public function computeAsOfWeek(Tournament $tournament, int $week): array
    {
        $teams = $this->teams->all();
        $outcomes = $this->fixtures
            ->played($tournament)
            ->filter(static fn ($fixture): bool => $fixture->week <= $week)
            ->map(static fn ($fixture): MatchOutcome => MatchOutcome::fromFixture($fixture));

        return $this->computeFor($teams, $outcomes);
    }

    /**
     * Pure computation. No DB. Operates on:
     *   - $teams:    every team that should appear in the table
     *   - $outcomes: every played match (real or simulated)
     *
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, MatchOutcome>|iterable<int, MatchOutcome>  $outcomes
     * @return list<StandingsRow>
     */
    public function computeFor(Collection $teams, iterable $outcomes): array
    {
        $accumulator = $this->initAccumulator($teams);

        foreach ($outcomes as $outcome) {
            $this->applyOutcome($accumulator, $outcome);
        }

        $rows = $this->materializeRows($accumulator);
        $this->sortByEplTiebreaker($rows);

        return $rows;
    }

    /**
     * Seed an accumulator with one bucket per team. Buckets live as plain
     * arrays for speed — DTOs would mean reconstructing the row 4 times
     * per match, which adds up across Monte Carlo's 60k+ iterations.
     *
     * @param  Collection<int, Team>  $teams
     * @return array<int, array{team: Team, played: int, won: int, drawn: int, lost: int, goals_for: int, goals_against: int}>
     */
    private function initAccumulator(Collection $teams): array
    {
        $rows = [];
        foreach ($teams as $team) {
            $rows[$team->id] = [
                'team' => $team,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ];
        }

        return $rows;
    }

    /**
     * Update both teams' running totals for a single match.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyOutcome(array &$rows, MatchOutcome $outcome): void
    {
        $home = &$rows[$outcome->homeTeamId];
        $away = &$rows[$outcome->awayTeamId];

        $home['played']++;
        $away['played']++;

        $home['goals_for'] += $outcome->homeGoals;
        $home['goals_against'] += $outcome->awayGoals;
        $away['goals_for'] += $outcome->awayGoals;
        $away['goals_against'] += $outcome->homeGoals;

        if ($outcome->homeGoals > $outcome->awayGoals) {
            $home['won']++;
            $away['lost']++;
        } elseif ($outcome->homeGoals < $outcome->awayGoals) {
            $home['lost']++;
            $away['won']++;
        } else {
            $home['drawn']++;
            $away['drawn']++;
        }
    }

    /**
     * Convert the accumulator buckets into immutable StandingsRow DTOs,
     * computing GD and points from the running totals.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<StandingsRow>
     */
    private function materializeRows(array $rows): array
    {
        $standings = [];
        foreach ($rows as $row) {
            $points = $row['won'] * 3 + $row['drawn'];
            $goalDifference = $row['goals_for'] - $row['goals_against'];

            $standings[] = new StandingsRow(
                teamId: $row['team']->id,
                teamName: $row['team']->name,
                played: $row['played'],
                won: $row['won'],
                drawn: $row['drawn'],
                lost: $row['lost'],
                goalsFor: $row['goals_for'],
                goalsAgainst: $row['goals_against'],
                goalDifference: $goalDifference,
                points: $points,
            );
        }

        return $standings;
    }

    /**
     * EPL tiebreaker: descending Pts → GD → GF, then alphabetical name as
     * the deterministic last-resort tie-breaker (so tests can assert exact
     * ordering when, e.g., two teams are mathematically identical).
     *
     * @param  list<StandingsRow>  $rows
     */
    private function sortByEplTiebreaker(array &$rows): void
    {
        usort(
            $rows,
            static fn (StandingsRow $a, StandingsRow $b): int =>
                [$b->points, $b->goalDifference, $b->goalsFor]
                <=> [$a->points, $a->goalDifference, $a->goalsFor]
                ?: $a->teamName <=> $b->teamName,
        );
    }
}
