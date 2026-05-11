<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * The output of MatchSimulatorService::simulate(). Pure value object, no
 * relation to Eloquent — keeping the simulator decoupled from the DB layer
 * lets Monte Carlo run thousands of simulations without persistence.
 */
final readonly class MatchResult
{
    public function __construct(
        public int $homeGoals,
        public int $awayGoals,
    ) {}
}
