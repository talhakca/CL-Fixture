<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 *
 * Default state creates an UNPLAYED week-1 fixture between two random teams.
 * Use ::played(h, a) to seed a finished match for standings tests.
 */
final class FixtureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week' => 1,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_goals' => null,
            'away_goals' => null,
            'played_at' => null,
        ];
    }

    /**
     * Mark the fixture as played with the given score. Used heavily in
     * StandingsService and PredictionService tests to set up a known
     * mid-season state without running the simulator.
     */
    public function played(int $homeGoals, int $awayGoals): self
    {
        return $this->state(fn (): array => [
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played_at' => now(),
        ]);
    }
}
