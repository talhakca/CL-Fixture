<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * One row of "team X has Y% chance of winning the league" — produced by
 * PredictionService and stored in the predictions table per week.
 */
final readonly class PredictionRow
{
    /**
     * @param  float  $championshipProbability  in [0, 1]
     */
    public function __construct(
        public int $teamId,
        public string $teamName,
        public float $championshipProbability,
    ) {}
}
