<?php

declare(strict_types=1);

use App\DataTransferObjects\MatchOutcome;
use App\Models\Team;
use App\Services\StandingsService;
use Illuminate\Support\Collection;

function makeTeams(): Collection
{
    return collect([
        Team::factory()->create(['name' => 'A', 'attack_strength' => 80, 'defense_strength' => 80]),
        Team::factory()->create(['name' => 'B', 'attack_strength' => 70, 'defense_strength' => 70]),
        Team::factory()->create(['name' => 'C', 'attack_strength' => 60, 'defense_strength' => 60]),
        Team::factory()->create(['name' => 'D', 'attack_strength' => 50, 'defense_strength' => 50]),
    ]);
}

it('awards 3 points for a win and 1 for a draw', function () {
    $teams = makeTeams();
    [$a, $b, $c, $d] = $teams->all();

    $outcomes = collect([
        new MatchOutcome($a->id, $b->id, 2, 0), // A beats B
        new MatchOutcome($c->id, $d->id, 1, 1), // draw
    ]);

    $rows = app(StandingsService::class)->computeFor($teams, $outcomes);
    $byName = collect($rows)->keyBy('teamName');

    expect($byName['A']->points)->toBe(3);
    expect($byName['B']->points)->toBe(0);
    expect($byName['C']->points)->toBe(1);
    expect($byName['D']->points)->toBe(1);
});

it('sorts by points first', function () {
    $teams = makeTeams();
    [$a, $b, $c, $d] = $teams->all();

    $outcomes = collect([
        new MatchOutcome($a->id, $b->id, 0, 1), // B 3pts
        new MatchOutcome($c->id, $d->id, 2, 1), // C 3pts
        new MatchOutcome($a->id, $c->id, 1, 1), // A 1, C 4
        new MatchOutcome($b->id, $d->id, 0, 0), // B 4, D 1
    ]);

    $rows = app(StandingsService::class)->computeFor($teams, $outcomes);

    // C and B both on 4 points; tiebreaker is GD then GF.
    // C: GF=3 GA=2 GD=+1 ; B: GF=1 GA=0 GD=+1 ; equal GD, C has more GF
    expect($rows[0]->teamName)->toBe('C');
    expect($rows[1]->teamName)->toBe('B');
});

it('sorts by goal difference when points are tied', function () {
    $teams = makeTeams();
    [$a, $b, $c, $d] = $teams->all();

    $outcomes = collect([
        new MatchOutcome($a->id, $b->id, 5, 0), // A: GD +5, 3pts
        new MatchOutcome($c->id, $d->id, 1, 0), // C: GD +1, 3pts
    ]);

    $rows = app(StandingsService::class)->computeFor($teams, $outcomes);

    expect($rows[0]->teamName)->toBe('A'); // higher GD wins the tiebreaker
    expect($rows[1]->teamName)->toBe('C');
});

it('sorts by goals for when points and goal difference are tied', function () {
    $teams = makeTeams();
    [$a, $b, $c, $d] = $teams->all();

    // Both A and C win 2-1 — equal pts (3) and GD (+1).
    $outcomes = collect([
        new MatchOutcome($a->id, $b->id, 2, 1),
        new MatchOutcome($c->id, $d->id, 2, 1),
    ]);

    $rows = app(StandingsService::class)->computeFor($teams, $outcomes);

    // Both at 2 GF — alphabetical fallback puts A before C.
    expect($rows[0]->teamName)->toBe('A');
    expect($rows[1]->teamName)->toBe('C');
});

it('counts draws and losses correctly across multiple matches', function () {
    $teams = makeTeams();
    [$a, $b, $c, $d] = $teams->all();

    $outcomes = collect([
        new MatchOutcome($a->id, $b->id, 1, 1),
        new MatchOutcome($a->id, $c->id, 0, 2),
        new MatchOutcome($a->id, $d->id, 3, 0),
    ]);

    $rows = collect(app(StandingsService::class)->computeFor($teams, $outcomes))->keyBy('teamName');

    $aRow = $rows['A'];
    expect($aRow->played)->toBe(3);
    expect($aRow->won)->toBe(1);
    expect($aRow->drawn)->toBe(1);
    expect($aRow->lost)->toBe(1);
    expect($aRow->goalsFor)->toBe(4);
    expect($aRow->goalsAgainst)->toBe(3);
    expect($aRow->points)->toBe(4);
});
