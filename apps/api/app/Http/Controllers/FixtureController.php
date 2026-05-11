<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFixtureScoreRequest;
use App\Http\Resources\FixtureResource;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Services\GameService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-side fixture endpoints (per-tournament) plus the global
 * score-edit. Edits go through the GameService so prediction cascades
 * stay correct.
 */
final class FixtureController extends Controller
{
    public function __construct(
        private readonly FixtureRepositoryInterface $fixtures,
        private readonly GameService $game,
    ) {}

    public function index(Tournament $tournament): AnonymousResourceCollection
    {
        return FixtureResource::collection($this->fixtures->all($tournament));
    }

    /**
     * Manual score override on an existing fixture. Returns the full
     * sibling fixture set for that fixture's tournament so the page
     * doesn't need a follow-up GET.
     */
    public function update(
        UpdateFixtureScoreRequest $request,
        int $fixtureId,
    ): AnonymousResourceCollection {
        $updated = $this->game->editScore(
            fixtureId: $fixtureId,
            homeGoals: $request->homeGoals(),
            awayGoals: $request->awayGoals(),
        );

        return FixtureResource::collection($this->fixtures->all($updated->tournament));
    }
}
