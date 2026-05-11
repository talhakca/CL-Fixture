<?php

declare(strict_types=1);

use App\Exceptions\FixtureAlreadyGeneratedException;
use App\Exceptions\InvalidTeamCountException;
use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\FixtureGeneratorService;
use Database\Seeders\TeamSeeder;

beforeEach(function () {
    $this->seed(TeamSeeder::class);
    $this->tournament = Tournament::factory()->create();
});

it('produces 12 fixtures across 6 weeks for a 4-team double round-robin', function () {
    app(FixtureGeneratorService::class)->generate($this->tournament);

    expect(Fixture::count())->toBe(12);
    expect(Fixture::distinct('week')->count('week'))->toBe(6);

    foreach (range(1, 6) as $week) {
        expect(Fixture::where('week', $week)->count())->toBe(2);
    }
});

it('every fixture row carries the tournament_id', function () {
    app(FixtureGeneratorService::class)->generate($this->tournament);

    expect(Fixture::where('tournament_id', $this->tournament->id)->count())->toBe(12);
    expect(Fixture::where('tournament_id', '!=', $this->tournament->id)->count())->toBe(0);
});

it('makes every team play every other team exactly twice', function () {
    app(FixtureGeneratorService::class)->generate($this->tournament);

    $teams = Team::all();
    foreach ($teams as $a) {
        foreach ($teams as $b) {
            if ($a->id === $b->id) {
                continue;
            }

            $matches = Fixture::query()
                ->where(function ($q) use ($a, $b) {
                    $q->where('home_team_id', $a->id)->where('away_team_id', $b->id);
                })
                ->orWhere(function ($q) use ($a, $b) {
                    $q->where('home_team_id', $b->id)->where('away_team_id', $a->id);
                })
                ->count();

            expect($matches)->toBe(2);
        }
    }
});

it('balances home and away games — every team plays 3 home and 3 away', function () {
    app(FixtureGeneratorService::class)->generate($this->tournament);

    foreach (Team::all() as $team) {
        $home = Fixture::where('home_team_id', $team->id)->count();
        $away = Fixture::where('away_team_id', $team->id)->count();
        expect($home)->toBe(3);
        expect($away)->toBe(3);
    }
});

it('refuses to generate twice for the same tournament', function () {
    $svc = app(FixtureGeneratorService::class);
    $svc->generate($this->tournament);

    expect(fn () => $svc->generate($this->tournament))->toThrow(FixtureAlreadyGeneratedException::class);
});

it('refuses to generate when the team count is not even', function () {
    Team::query()->limit(1)->delete();

    expect(fn () => app(FixtureGeneratorService::class)->generate($this->tournament))
        ->toThrow(InvalidTeamCountException::class);
});
