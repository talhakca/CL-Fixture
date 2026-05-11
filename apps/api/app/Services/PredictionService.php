<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MatchOutcome;
use App\DataTransferObjects\PredictionRow;
use App\Models\Team;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Repositories\Interfaces\PredictionRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Monte Carlo championship-probability estimator.
 *
 * What it does, in plain words:
 *   1. Snapshot the current state (played fixtures stay frozen).
 *   2. For each remaining fixture, ask MatchSimulatorService to play it.
 *   3. Compute the final standings of that synthetic season.
 *   4. Tally one win for whoever finished #1.
 *   5. Repeat N times (per-tournament setting → config fallback, default 10k).
 *   6. championship_probability[team] = wins[team] / N.
 *
 * When the season is already over, every iteration produces the same
 * champion — the math collapses to 100% / 0% naturally, no special case.
 *
 * RNG isolation:
 *   - The simulator used INSIDE Monte Carlo is constructed with its own
 *     Randomizer → its state never bleeds into the simulator the GameService
 *     uses for play-week / play-all.
 */
final class PredictionService
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly FixtureRepositoryInterface $fixtures,
        private readonly PredictionRepositoryInterface $predictions,
        private readonly StandingsService $standingsService,
    ) {}

    public function computeAndStore(Tournament $tournament, int $week): void
    {
        $teams = $this->teams->all();
        $playedOutcomes = $this->fixtures
            ->played($tournament)
            ->map(static fn ($fixture): MatchOutcome => MatchOutcome::fromFixture($fixture));
        $pendingFixtures = $this->fixtures->pending($tournament);

        $iterations = (int) $tournament->config('monte_carlo_iterations');
        $winCounts = $this->runMonteCarlo(
            $tournament,
            $teams,
            $playedOutcomes,
            $pendingFixtures,
            $iterations,
        );

        $rows = [];
        foreach ($teams as $team) {
            $rows[] = [
                'team_id' => $team->id,
                'championship_probability' => ($winCounts[$team->id] ?? 0) / $iterations,
            ];
        }

        $this->predictions->upsertSnapshot($tournament, $week, $rows);
    }

    /**
     * @return list<PredictionRow>|null
     */
    public function latest(Tournament $tournament): ?array
    {
        $week = $this->predictions->latestWeek($tournament);
        if ($week === 0) {
            return null;
        }

        return $this->forWeek($tournament, $week);
    }

    /**
     * @return list<PredictionRow>
     */
    public function forWeek(Tournament $tournament, int $week): array
    {
        return $this->predictions
            ->forWeek($tournament, $week)
            ->map(static fn ($prediction): PredictionRow => new PredictionRow(
                teamId: $prediction->team_id,
                teamName: $prediction->team->name,
                championshipProbability: (float) $prediction->championship_probability,
            ))
            ->all();
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, MatchOutcome>  $playedOutcomes
     * @param  Collection<int, \App\Models\Fixture>  $pendingFixtures
     * @return array<int, int>  team_id → win count
     */
    private function runMonteCarlo(
        Tournament $tournament,
        Collection $teams,
        Collection $playedOutcomes,
        Collection $pendingFixtures,
        int $iterations,
    ): array {
        // Isolated simulator with its own RNG state — no leak into the
        // GameService's simulator. Mt19937 keyed by random_int() gives a
        // fresh entropy seed per call (tests rebind the container if they
        // need determinism).
        $simulator = new MatchSimulatorService(new Randomizer(new Mt19937(random_int(0, PHP_INT_MAX))));

        $wins = [];
        for ($i = 0; $i < $iterations; $i++) {
            $simulated = $this->simulatePending($tournament, $simulator, $pendingFixtures);

            $allOutcomes = $playedOutcomes->concat($simulated);
            $standings = $this->standingsService->computeFor($teams, $allOutcomes);

            $champion = $standings[0]->teamId;
            $wins[$champion] = ($wins[$champion] ?? 0) + 1;
        }

        return $wins;
    }

    /**
     * @param  Collection<int, \App\Models\Fixture>  $pendingFixtures
     * @return Collection<int, MatchOutcome>
     */
    private function simulatePending(
        Tournament $tournament,
        MatchSimulatorService $simulator,
        Collection $pendingFixtures,
    ): Collection {
        return $pendingFixtures->map(static function ($fixture) use ($tournament, $simulator): MatchOutcome {
            $result = $simulator->simulate($tournament, $fixture->homeTeam, $fixture->awayTeam);

            return new MatchOutcome(
                homeTeamId: $fixture->home_team_id,
                awayTeamId: $fixture->away_team_id,
                homeGoals: $result->homeGoals,
                awayGoals: $result->awayGoals,
            );
        });
    }
}
