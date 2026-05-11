<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FixtureAlreadyGeneratedException;
use App\Exceptions\InvalidTeamCountException;
use App\Models\Team;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;

/**
 * Builds a full double round-robin schedule and persists it.
 *
 * Algorithm (circle method, a.k.a. Berger tables):
 *   - Place team[0] as the "fixed" pivot.
 *   - The other (n-1) teams sit around it.
 *   - Round k pairs are (working[i], working[n-1-i]) for i=0..n/2-1.
 *   - After each round, rotate: the last element moves to position 1,
 *     others shift right. team[0] never moves.
 *   - This generates n-1 rounds with n/2 matches each — every pair
 *     appears exactly once. That's the FIRST leg.
 *   - For the SECOND leg we replay the same rounds with home/away
 *     flipped, giving each pair a return fixture.
 *
 * For 4 teams: 3 rounds × 2 matches × 2 legs = 12 fixtures, 6 weeks.
 */
final class FixtureGeneratorService
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly FixtureRepositoryInterface $fixtures,
    ) {}

    public function generate(Tournament $tournament): void
    {
        // Idempotency guard — every tournament gets a fresh schedule on
        // creation; we never re-generate into an existing one.
        if ($this->fixtures->exists($tournament)) {
            throw FixtureAlreadyGeneratedException::create();
        }

        // Pull teams as a 0-indexed list. The repository sorts by id so the
        // schedule is deterministic across runs (important for tests).
        $teams = $this->teams->all()->values()->all();
        $teamCount = count($teams);

        if ($teamCount < 2 || $teamCount % 2 !== 0) {
            throw InvalidTeamCountException::create($teamCount);
        }

        $rows = [];
        $week = 1;

        // First leg — original home/away from the circle method.
        foreach ($this->roundRobinRounds($teams) as $round) {
            foreach ($round as [$home, $away]) {
                $rows[] = [
                    'tournament_id' => $tournament->id,
                    'week' => $week,
                    'home_team_id' => $home->id,
                    'away_team_id' => $away->id,
                ];
            }
            $week++;
        }

        // Second leg — same pairings, home/away swapped so each pair plays
        // once at each ground. This yields a fair home/away distribution
        // across the season for every team.
        foreach ($this->roundRobinRounds($teams) as $round) {
            foreach ($round as [$home, $away]) {
                $rows[] = [
                    'tournament_id' => $tournament->id,
                    'week' => $week,
                    'home_team_id' => $away->id,
                    'away_team_id' => $home->id,
                ];
            }
            $week++;
        }

        $this->fixtures->bulkCreate($rows);
    }

    /**
     * Yields one round at a time. Each round is a list of [home, away]
     * pairs. Generator is used (not eager array) to make the rotation
     * intent obvious — round at a time, mutate in place.
     *
     * @param  list<Team>  $teams
     * @return iterable<list<array{0: Team, 1: Team}>>
     */
    private function roundRobinRounds(array $teams): iterable
    {
        $n = count($teams);
        $rounds = $n - 1;
        $pairsPerRound = intdiv($n, 2);

        // Working copy; index 0 stays fixed, the rest rotate each round.
        $working = $teams;

        for ($r = 0; $r < $rounds; $r++) {
            $pairs = [];
            for ($i = 0; $i < $pairsPerRound; $i++) {
                $pairs[] = [$working[$i], $working[$n - 1 - $i]];
            }

            yield $pairs;

            // Rotate: keep working[0], move last element to position 1,
            // shift the rest right. e.g., [A,B,C,D] -> [A,D,B,C].
            $rotated = [$working[0], $working[$n - 1]];
            for ($i = 1; $i < $n - 1; $i++) {
                $rotated[] = $working[$i];
            }
            $working = $rotated;
        }
    }
}
