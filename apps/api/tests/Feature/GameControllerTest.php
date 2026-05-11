<?php

declare(strict_types=1);

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Tournament;
use Database\Seeders\TeamSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->seed(TeamSeeder::class);
    Config::set('game.monte_carlo_iterations', 200);
});

it('creates a tournament with a freshly-generated schedule', function () {
    $this->postJson('/api/tournaments')
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'total_weeks', 'last_played_week']]);

    expect(Tournament::count())->toBe(1);
    expect(Fixture::count())->toBe(12);
});

it('plays a week and returns the updated fixture set', function () {
    $tournament = Tournament::factory()->create();
    app(\App\Services\FixtureGeneratorService::class)->generate($tournament);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/play-week")
        ->assertOk()
        ->assertJsonPath('meta.week', 1)
        ->assertJsonCount(12, 'data');

    $playedInResponse = collect($response->json('data'))->where('is_played', true);
    expect($playedInResponse->count())->toBe(2);

    expect(Fixture::whereNotNull('played_at')->count())->toBe(2);
    expect(Prediction::where('tournament_id', $tournament->id)->where('week', 1)->count())
        ->toBe(4);
});

it('returns previous-week standings + predictions on the second play-week', function () {
    $tournament = Tournament::factory()->create();
    app(\App\Services\FixtureGeneratorService::class)->generate($tournament);

    // First week — no previous data exists.
    $first = $this->postJson("/api/tournaments/{$tournament->id}/play-week")->assertOk();
    expect($first->json('meta.previous_standings'))->toBe([]);
    expect($first->json('meta.previous_predictions'))->toBe([]);

    // Second week — previous = end-of-week-1.
    $second = $this->postJson("/api/tournaments/{$tournament->id}/play-week")
        ->assertOk()
        ->assertJsonPath('meta.week', 2);

    expect($second->json('meta.previous_standings'))->toHaveCount(4);
    expect($second->json('meta.previous_predictions'))->toHaveCount(4);
});

it('refuses play-week when nothing is left to play', function () {
    $tournament = Tournament::factory()->create();
    app(\App\Services\FixtureGeneratorService::class)->generate($tournament);

    $this->postJson("/api/tournaments/{$tournament->id}/play-all")->assertOk();
    $this->postJson("/api/tournaments/{$tournament->id}/play-week")->assertStatus(409);
});

it('plays the entire season with play-all', function () {
    $tournament = Tournament::factory()->create();
    app(\App\Services\FixtureGeneratorService::class)->generate($tournament);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/play-all")
        ->assertOk()
        ->assertJsonPath('meta.weeks', [1, 2, 3, 4, 5, 6])
        ->assertJsonCount(12, 'data');

    $allPlayed = collect($response->json('data'))->every(fn ($f) => $f['is_played'] === true);
    expect($allPlayed)->toBeTrue();

    expect(Fixture::whereNotNull('played_at')->count())->toBe(12);
    expect(
        Prediction::where('tournament_id', $tournament->id)->distinct('week')->count('week'),
    )->toBe(6);
});

it('reset-scores clears results but keeps the schedule', function () {
    $tournament = Tournament::factory()->create();
    app(\App\Services\FixtureGeneratorService::class)->generate($tournament);
    $this->postJson("/api/tournaments/{$tournament->id}/play-week");

    $this->postJson("/api/tournaments/{$tournament->id}/reset-scores")
        ->assertOk()
        ->assertJsonCount(12, 'data');

    expect(Fixture::count())->toBe(12);
    expect(Fixture::whereNotNull('played_at')->count())->toBe(0);
    expect(Prediction::where('tournament_id', $tournament->id)->count())->toBe(0);
});
