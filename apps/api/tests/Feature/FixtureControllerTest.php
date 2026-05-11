<?php

declare(strict_types=1);

use App\Models\Fixture;
use App\Models\Prediction;
use Database\Seeders\TeamSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->seed(TeamSeeder::class);
    Config::set('game.monte_carlo_iterations', 200);

    $this->tournament = $this->postJson('/api/tournaments')->json('data');
    $this->postJson("/api/tournaments/{$this->tournament['id']}/play-week");
});

it('updates a fixture score and returns the full updated fixture set', function () {
    $fixture = Fixture::whereNotNull('played_at')->first();

    $response = $this->putJson("/api/fixtures/{$fixture->id}", [
        'home_goals' => 7,
        'away_goals' => 0,
    ])->assertOk()->assertJsonCount(12, 'data');

    $edited = collect($response->json('data'))->firstWhere('id', $fixture->id);
    expect($edited['home_goals'])->toBe(7);
    expect($edited['away_goals'])->toBe(0);

    $fixture->refresh();
    expect($fixture->home_goals)->toBe(7);
    expect($fixture->away_goals)->toBe(0);
});

it('recomputes predictions for the edited fixture\'s week onwards', function () {
    $fixture = Fixture::whereNotNull('played_at')->first();
    $week = $fixture->week;

    $beforeIds = Prediction::where('tournament_id', $this->tournament['id'])
        ->where('week', $week)->pluck('id')->all();

    $this->putJson("/api/fixtures/{$fixture->id}", [
        'home_goals' => 9,
        'away_goals' => 0,
    ])->assertOk();

    $afterIds = Prediction::where('tournament_id', $this->tournament['id'])
        ->where('week', $week)->pluck('id')->all();

    expect(array_intersect($beforeIds, $afterIds))->toBeEmpty();
    expect(Prediction::where('tournament_id', $this->tournament['id'])
        ->where('week', $week)->count())->toBe(4);
});

it('rejects negative goals', function () {
    $fixture = Fixture::first();

    $this->putJson("/api/fixtures/{$fixture->id}", [
        'home_goals' => -1,
        'away_goals' => 0,
    ])->assertStatus(422);
});

it('rejects missing fields', function () {
    $fixture = Fixture::first();

    $this->putJson("/api/fixtures/{$fixture->id}", ['home_goals' => 1])
        ->assertStatus(422);
});

it('returns 404 for unknown fixture id', function () {
    $this->putJson('/api/fixtures/9999', ['home_goals' => 1, 'away_goals' => 0])
        ->assertNotFound();
});
