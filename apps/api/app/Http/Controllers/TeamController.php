<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TeamResource;
use App\Repositories\Interfaces\TeamRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only — teams are static seed data.
 */
final class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
    ) {}

    /**
     * List every team in the league.
     */
    public function index(): AnonymousResourceCollection
    {
        return TeamResource::collection($this->teams->all());
    }
}
