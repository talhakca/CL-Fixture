<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * One row in a league table. Pre-sorted by the StandingsService using the
 * EPL tiebreaker chain: Points → Goal Difference → Goals For → name.
 */
final readonly class StandingsRow
{
    public function __construct(
        public int $teamId,
        public string $teamName,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $goalDifference,
        public int $points,
    ) {}
}
