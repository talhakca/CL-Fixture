<?php

declare(strict_types=1);

use App\Http\Controllers\FixtureController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

/*
 * Routes are auto-prefixed with /api by bootstrap/app.php. Tournament
 * lifecycle and reads/writes scoped to a tournament live under
 * /api/tournaments/{tournament}; teams are global; PUT /fixtures/{id} is
 * the only state-changing route that doesn't take a tournament from the
 * path because the fixture itself carries the tournament_id.
 */

Route::get('/health', HealthController::class)->name('health');

Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');

Route::prefix('tournaments')->name('tournaments.')->group(function (): void {
    Route::get('/', [TournamentController::class, 'index'])->name('index');
    Route::post('/', [TournamentController::class, 'store'])->name('store');

    Route::prefix('{tournament}')->whereNumber('tournament')->group(function (): void {
        Route::get('/', [TournamentController::class, 'show'])->name('show');
        Route::delete('/', [TournamentController::class, 'destroy'])->name('destroy');

        Route::get('/fixtures', [FixtureController::class, 'index'])->name('fixtures.index');

        Route::get('/standings', [StandingsController::class, 'index'])->name('standings.index');
        Route::get('/standings/{week}', [StandingsController::class, 'show'])
            ->whereNumber('week')
            ->name('standings.show');

        Route::get('/predictions', [PredictionController::class, 'index'])->name('predictions.index');
        Route::get('/predictions/{week}', [PredictionController::class, 'show'])
            ->whereNumber('week')
            ->name('predictions.show');

        Route::post('/play-week', [GameController::class, 'playWeek'])->name('play-week');
        Route::post('/play-all', [GameController::class, 'playAll'])->name('play-all');
        Route::post('/reset-scores', [GameController::class, 'resetScores'])->name('reset-scores');
    });
});

Route::put('/fixtures/{fixture}', [FixtureController::class, 'update'])
    ->whereNumber('fixture')
    ->name('fixtures.update');
