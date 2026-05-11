<?php

declare(strict_types=1);

use App\Models\Fixture;
use App\Models\Tournament;
use Database\Seeders\TeamSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->seed(TeamSeeder::class);
    Config::set('game.monte_carlo_iterations', 200);
});

it('creates a tournament with auto-generated fixtures', function () {
    $response = $this->postJson('/api/tournaments')
        ->assertCreated()
        ->assertJsonPath('data.total_weeks', 6)
        ->assertJsonPath('data.last_played_week', 0);

    $id = $response->json('data.id');
    expect(Tournament::find($id))->not->toBeNull();
    expect(Fixture::where('tournament_id', $id)->count())->toBe(12);
});

it('lists tournaments newest-first', function () {
    $first = Tournament::factory()->create(['started_at' => now()->subHour()]);
    $second = Tournament::factory()->create(['started_at' => now()]);

    $response = $this->getJson('/api/tournaments')->assertOk();

    expect($response->json('data.0.id'))->toBe($second->id);
    expect($response->json('data.1.id'))->toBe($first->id);
});

it('returns 404 for unknown tournament id', function () {
    $this->getJson('/api/tournaments/9999')->assertNotFound();
});

it('deletes a tournament and cascades its fixtures and predictions', function () {
    $tournament = $this->postJson('/api/tournaments')->json('data');
    $this->postJson("/api/tournaments/{$tournament['id']}/play-week");

    $this->deleteJson("/api/tournaments/{$tournament['id']}")
        ->assertNoContent();

    expect(Tournament::find($tournament['id']))->toBeNull();
    expect(Fixture::where('tournament_id', $tournament['id'])->count())->toBe(0);
});
