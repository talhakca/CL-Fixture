<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Round-robin scheduling needs an even number of teams (≥ 2). The seeder
 * provides exactly 4 — anything else means seed data is broken.
 */
final class InvalidTeamCountException extends RuntimeException
{
    public static function create(int $actual): self
    {
        return new self("Round-robin requires an even number of teams (≥2); got {$actual}.");
    }
}
