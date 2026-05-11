<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\FixtureRepository;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Repositories\Interfaces\PredictionRepositoryInterface;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use App\Repositories\Interfaces\TournamentRepositoryInterface;
use App\Repositories\PredictionRepository;
use App\Repositories\TeamRepository;
use App\Repositories\TournamentRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds repository contracts to concrete Eloquent-backed implementations.
 *
 * Services depend only on the *Interface — never on the concrete class —
 * so feature tests can rebind to in-memory fakes via $this->app->bind()
 * when DB round-trips would slow tests down.
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TeamRepositoryInterface::class, TeamRepository::class);
        $this->app->bind(TournamentRepositoryInterface::class, TournamentRepository::class);
        $this->app->bind(FixtureRepositoryInterface::class, FixtureRepository::class);
        $this->app->bind(PredictionRepositoryInterface::class, PredictionRepository::class);
    }
}
