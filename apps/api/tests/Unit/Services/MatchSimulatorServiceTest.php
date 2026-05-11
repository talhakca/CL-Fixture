<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\Tournament;
use App\Services\MatchSimulatorService;
use Illuminate\Support\Facades\Config;
use Random\Engine\Mt19937;
use Random\Randomizer;

function deterministicSimulator(int $seed): MatchSimulatorService
{
    return new MatchSimulatorService(new Randomizer(new Mt19937($seed)));
}

function team(int $attack, int $defense, string $name = 'Test'): Team
{
    return Team::factory()->make([
        'name' => $name,
        'attack_strength' => $attack,
        'defense_strength' => $defense,
    ]);
}

function tournamentWithSettings(?array $settings = null): Tournament
{
    return Tournament::factory()->create(['settings' => $settings]);
}

it('returns a deterministic score for a given seed', function () {
    $tournament = tournamentWithSettings();
    $home = team(90, 80, 'Home');
    $away = team(60, 60, 'Away');

    $first = deterministicSimulator(42)->simulate($tournament, $home, $away);
    $second = deterministicSimulator(42)->simulate($tournament, $home, $away);

    expect($first->homeGoals)->toBe($second->homeGoals);
    expect($first->awayGoals)->toBe($second->awayGoals);
});

it('produces non-negative goals for both teams', function () {
    $tournament = tournamentWithSettings();
    $sim = deterministicSimulator(7);
    $result = $sim->simulate($tournament, team(50, 50), team(50, 50));

    expect($result->homeGoals)->toBeGreaterThanOrEqual(0);
    expect($result->awayGoals)->toBeGreaterThanOrEqual(0);
});

it('with luck=0 the strong team wins notably more often than the weak one', function () {
    $tournament = tournamentWithSettings(['luck_amplitude' => 0.0]);

    $strong = team(95, 90, 'Strong');
    $weak = team(35, 30, 'Weak');
    $sim = deterministicSimulator(123);

    $homeWins = 0;
    $awayWins = 0;
    for ($i = 0; $i < 500; $i++) {
        $r = $sim->simulate($tournament, $strong, $weak);
        if ($r->homeGoals > $r->awayGoals) {
            $homeWins++;
        } elseif ($r->homeGoals < $r->awayGoals) {
            $awayWins++;
        }
    }

    expect($homeWins)->toBeGreaterThan($awayWins * 3);
});

it('home advantage tilts even matches toward the home side', function () {
    $tournament = tournamentWithSettings(['luck_amplitude' => 0.0, 'home_advantage' => 5]);

    $a = team(70, 70);
    $b = team(70, 70);
    $sim = deterministicSimulator(99);

    $homeWins = 0;
    $awayWins = 0;
    for ($i = 0; $i < 600; $i++) {
        $r = $sim->simulate($tournament, $a, $b);
        if ($r->homeGoals > $r->awayGoals) {
            $homeWins++;
        } elseif ($r->awayGoals > $r->homeGoals) {
            $awayWins++;
        }
    }

    expect($homeWins)->toBeGreaterThan($awayWins);
});

it('per-tournament settings override the global config', function () {
    Config::set('game.base_goals', 2.5);
    Config::set('game.home_advantage', 5);
    Config::set('game.luck_amplitude', 0.3);
    Config::set('game.noise_range', 30);

    $tournament = tournamentWithSettings([
        'base_goals' => 99.0,
        'home_advantage' => 5,
        'luck_amplitude' => 0.0,
        'noise_range' => 30,
    ]);
    $home = team(50, 50);
    $away = team(50, 50);

    // Same matchup, but the tournament forces base_goals=99 — total goals
    // per match must be far above what the global default (2.5) would
    // produce. Sample a few matches to keep the test fast.
    $totalGoals = 0;
    for ($i = 0; $i < 5; $i++) {
        $r = deterministicSimulator($i)->simulate($tournament, $home, $away);
        $totalGoals += $r->homeGoals + $r->awayGoals;
    }

    expect($totalGoals)->toBeGreaterThan(50);
});
