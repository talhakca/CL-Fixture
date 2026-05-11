<?php

declare(strict_types=1);

use App\Models\Tournament;
use Database\Seeders\TeamSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->seed(TeamSeeder::class);
    Config::set('game.monte_carlo_iterations', 200);
});

it('returns all 4 teams', function () {
    $this->getJson('/api/teams')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'attack_strength', 'defense_strength']]]);
});

it('returns 12 fixtures after a tournament is created', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');

    $this->getJson("/api/tournaments/{$tournament['id']}/fixtures")
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonStructure(['data' => [[
            'id', 'week', 'home_team', 'away_team',
            'home_goals', 'away_goals', 'is_played', 'winner_team_id',
        ]]]);
});

it('returns standings sorted by points DESC', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');
    $this->postJson("/api/tournaments/{$tournament['id']}/play-all");

    $response = $this->getJson("/api/tournaments/{$tournament['id']}/standings")->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(4);

    for ($i = 0; $i < count($data) - 1; $i++) {
        expect($data[$i]['points'])->toBeGreaterThanOrEqual($data[$i + 1]['points']);
    }
});

it('returns an empty data array when no predictions have been computed yet', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');

    $this->getJson("/api/tournaments/{$tournament['id']}/predictions")
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('returns latest predictions after a week is played', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');
    $this->postJson("/api/tournaments/{$tournament['id']}/play-week");

    $this->getJson("/api/tournaments/{$tournament['id']}/predictions")
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure(['data' => [['team_id', 'team_name', 'championship_probability']]]);
});

it('returns 404 for an unknown predictions week', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');

    $this->getJson("/api/tournaments/{$tournament['id']}/predictions/99")->assertNotFound();
});

it('returns standings as of a specific week — only fixtures up to that week count', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');
    $this->postJson("/api/tournaments/{$tournament['id']}/play-week");

    $week1 = $this->getJson("/api/tournaments/{$tournament['id']}/standings/1")
        ->assertOk()->json('data');
    $live = $this->getJson("/api/tournaments/{$tournament['id']}/standings")
        ->assertOk()->json('data');

    expect(collect($week1)->sum('played'))->toBe(4);
    expect($week1)->toEqual($live);
});

it('week 0 standings show all zeros (before any match)', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');

    $rows = $this->getJson("/api/tournaments/{$tournament['id']}/standings/0")
        ->assertOk()->json('data');

    expect($rows)->toHaveCount(4);
    expect(collect($rows)->sum('played'))->toBe(0);
    expect(collect($rows)->sum('points'))->toBe(0);
});

it('two tournaments are isolated from each other', function () {
    $a = $this->postJson('/api/tournaments')->json('data');
    $b = $this->postJson('/api/tournaments')->json('data');

    $this->postJson("/api/tournaments/{$a['id']}/play-all");

    $fixturesA = $this->getJson("/api/tournaments/{$a['id']}/fixtures")->json('data');
    $fixturesB = $this->getJson("/api/tournaments/{$b['id']}/fixtures")->json('data');

    expect(collect($fixturesA)->every(fn ($f) => $f['is_played'] === true))->toBeTrue();
    expect(collect($fixturesB)->every(fn ($f) => $f['is_played'] === false))->toBeTrue();
});
