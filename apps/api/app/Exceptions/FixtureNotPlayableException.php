<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when "play next week" is called but every fixture has already
 * been played, OR when fixtures don't exist at all yet. Self-renders as
 * HTTP 409 Conflict.
 */
final class FixtureNotPlayableException extends RuntimeException
{
    public static function noFixtures(): self
    {
        return new self('No fixtures generated yet. Generate the season first.');
    }

    public static function seasonOver(): self
    {
        return new self('All fixtures have already been played.');
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }
}
