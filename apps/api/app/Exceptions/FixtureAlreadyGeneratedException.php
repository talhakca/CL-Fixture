<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by FixtureGeneratorService when callers try to (re)generate while
 * a schedule already exists. Self-renders as HTTP 409 Conflict so the
 * controller layer doesn't need a try/catch.
 */
final class FixtureAlreadyGeneratedException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Fixtures already generated. Reset the season first.');
    }

    /**
     * Laravel's exception handler invokes render() if defined, using its
     * return value as the HTTP response — controllers stay clean.
     */
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }
}
