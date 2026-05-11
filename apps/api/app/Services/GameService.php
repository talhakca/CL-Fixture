<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FixtureNotPlayableException;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Repositories\Interfaces\PredictionRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Top-level orchestrator for in-tournament actions. Controllers depend on
 * THIS service only — they don't touch repositories or other services
 * directly. Every action is scoped to a Tournament passed in by the
 * controller (resolved via route-model binding).
 *
 * Schedule generation lives in TournamentService::create — a tournament
 * always has its fixtures from birth, never an empty-schedule state.
 *
 * State-changing flows:
 *   - playWeek()    — simulate the next pending week
 *   - playAll()     — simulate every remaining week
 *   - editScore()   — manual score override (recomputes from that week)
 *   - resetScores() — wipe results, keep schedule
 */
final class GameService
{
    public function __construct(
        private readonly FixtureRepositoryInterface $fixtures,
        private readonly PredictionRepositoryInterface $predictions,
        private readonly MatchSimulatorService $simulator,
        private readonly PredictionService $predictionService,
    ) {}

    /**
     * Simulate every fixture in the next unplayed week. After persisting
     * results we compute the prediction snapshot for that week so the
     * frontend can render it without re-running Monte Carlo.
     *
     * @return int  the week number that was just played
     */
    public function playWeek(Tournament $tournament): int
    {
        if (! $this->fixtures->exists($tournament)) {
            throw FixtureNotPlayableException::noFixtures();
        }

        $nextWeek = $this->fixtures->lastPlayedWeek($tournament) + 1;
        $weekFixtures = $this->fixtures->forWeek($tournament, $nextWeek);

        if ($weekFixtures->isEmpty()) {
            throw FixtureNotPlayableException::seasonOver();
        }

        DB::transaction(function () use ($tournament, $weekFixtures, $nextWeek): void {
            foreach ($weekFixtures as $fixture) {
                if ($fixture->played_at !== null) {
                    continue;
                }
                $this->simulateAndPersist($tournament, $fixture);
            }

            $this->predictionService->computeAndStore($tournament, $nextWeek);
        });

        return $nextWeek;
    }

    /**
     * @return list<int>  the weeks that were just played, in order
     */
    public function playAll(Tournament $tournament): array
    {
        if (! $this->fixtures->exists($tournament)) {
            throw FixtureNotPlayableException::noFixtures();
        }

        $weeks = [];
        while ($this->fixtures->lastPlayedWeek($tournament) < $this->fixtures->totalWeeks($tournament)) {
            $weeks[] = $this->playWeek($tournament);
        }

        return $weeks;
    }

    /**
     * Override the score on an already-played fixture. Anything computed
     * after that fixture's week becomes stale — predictions for that week
     * onward are recomputed against the new fixture state.
     */
    public function editScore(int $fixtureId, int $homeGoals, int $awayGoals): Fixture
    {
        return DB::transaction(function () use ($fixtureId, $homeGoals, $awayGoals): Fixture {
            $fixture = $this->fixtures->findOrFail($fixtureId);
            $fixture = $this->fixtures->recordResult($fixture, $homeGoals, $awayGoals);

            $tournament = $fixture->tournament;

            $this->predictions->deleteFromWeek($tournament, $fixture->week);

            $lastPlayedWeek = $this->fixtures->lastPlayedWeek($tournament);
            for ($w = $fixture->week; $w <= $lastPlayedWeek; $w++) {
                $this->predictionService->computeAndStore($tournament, $w);
            }

            return $fixture;
        });
    }

    public function resetScores(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament): void {
            $this->fixtures->clearScores($tournament);
            $this->predictions->deleteAll($tournament);
        });
    }

    private function simulateAndPersist(Tournament $tournament, Fixture $fixture): void
    {
        $result = $this->simulator->simulate($tournament, $fixture->homeTeam, $fixture->awayTeam);
        $this->fixtures->recordResult($fixture, $result->homeGoals, $result->awayGoals);
    }
}
