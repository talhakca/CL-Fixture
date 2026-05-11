<?php

declare(strict_types=1);

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\PredictionService;
use Database\Seeders\TeamSeeder;

beforeEach(function () {
    $this->seed(TeamSeeder::class);

    // Smaller iteration count via per-tournament settings keeps these
    // tests fast (~1k still gives ~3% standard error, plenty for the
    // assertions below).
    $this->tournament = Tournament::factory()->create([
        'settings' => ['monte_carlo_iterations' => 1000],
    ]);
});

it('persists exactly one row per team for the requested week', function () {
    Fixture::factory()->create([
        'tournament_id' => $this->tournament->id,
        'week' => 1,
        'home_team_id' => Team::where('name', 'PSG')->value('id'),
        'away_team_id' => Team::where('name', 'Bayern')->value('id'),
        'home_goals' => 2, 'away_goals' => 1, 'played_at' => now(),
    ]);

    app(PredictionService::class)->computeAndStore($this->tournament, 1);

    expect(
        Prediction::where('tournament_id', $this->tournament->id)
            ->where('week', 1)
            ->count(),
    )->toBe(4);
});

it('produces probabilities that sum to ~1', function () {
    app(PredictionService::class)->computeAndStore($this->tournament, 1);

    $sum = (float) Prediction::where('tournament_id', $this->tournament->id)
        ->where('week', 1)
        ->sum('championship_probability');

    expect($sum)->toBeGreaterThan(0.999)->and($sum)->toBeLessThan(1.001);
});

it('gives 100% when one team has clinched mathematically', function () {
    $teams = Team::all()->keyBy('name');
    $tid = $this->tournament->id;

    // Three played weeks: PSG wins all of its matches.
    Fixture::factory()->played(3, 0)->create([
        'tournament_id' => $tid, 'week' => 1,
        'home_team_id' => $teams['PSG']->id, 'away_team_id' => $teams['Bayern']->id,
    ]);
    Fixture::factory()->played(0, 0)->create([
        'tournament_id' => $tid, 'week' => 1,
        'home_team_id' => $teams['Arsenal']->id, 'away_team_id' => $teams['Atletico']->id,
    ]);
    Fixture::factory()->played(2, 0)->create([
        'tournament_id' => $tid, 'week' => 2,
        'home_team_id' => $teams['PSG']->id, 'away_team_id' => $teams['Arsenal']->id,
    ]);
    Fixture::factory()->played(0, 0)->create([
        'tournament_id' => $tid, 'week' => 2,
        'home_team_id' => $teams['Bayern']->id, 'away_team_id' => $teams['Atletico']->id,
    ]);
    Fixture::factory()->played(1, 0)->create([
        'tournament_id' => $tid, 'week' => 3,
        'home_team_id' => $teams['PSG']->id, 'away_team_id' => $teams['Atletico']->id,
    ]);
    Fixture::factory()->played(0, 0)->create([
        'tournament_id' => $tid, 'week' => 3,
        'home_team_id' => $teams['Bayern']->id, 'away_team_id' => $teams['Arsenal']->id,
    ]);

    Fixture::factory()->create([
        'tournament_id' => $tid, 'week' => 4,
        'home_team_id' => $teams['Arsenal']->id, 'away_team_id' => $teams['PSG']->id,
    ]);
    Fixture::factory()->create([
        'tournament_id' => $tid, 'week' => 4,
        'home_team_id' => $teams['Atletico']->id, 'away_team_id' => $teams['Bayern']->id,
    ]);
    Fixture::factory()->create([
        'tournament_id' => $tid, 'week' => 5,
        'home_team_id' => $teams['PSG']->id, 'away_team_id' => $teams['Bayern']->id,
    ]);
    Fixture::factory()->create([
        'tournament_id' => $tid, 'week' => 5,
        'home_team_id' => $teams['Atletico']->id, 'away_team_id' => $teams['Arsenal']->id,
    ]);

    app(PredictionService::class)->computeAndStore($this->tournament, 3);

    $psg = Prediction::where('tournament_id', $tid)
        ->where('week', 3)
        ->where('team_id', $teams['PSG']->id)
        ->value('championship_probability');

    expect((float) $psg)->toBe(1.0);
});

it('overwrites existing snapshots when called twice for the same week', function () {
    app(PredictionService::class)->computeAndStore($this->tournament, 1);
    app(PredictionService::class)->computeAndStore($this->tournament, 1);

    expect(
        Prediction::where('tournament_id', $this->tournament->id)
            ->where('week', 1)
            ->count(),
    )->toBe(4);
});
