<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use App\Services\TournamentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tournament lifecycle endpoints. The simulation actions (play-week,
 * play-all, etc.) live in GameController under the same /tournaments/{id}
 * prefix; this controller only owns create / list / read / delete.
 */
final class TournamentController extends Controller
{
    public function __construct(
        private readonly TournamentService $tournaments,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TournamentResource::collection($this->tournaments->list());
    }

    public function store(): TournamentResource
    {
        return new TournamentResource($this->tournaments->create());
    }

    public function show(Tournament $tournament): TournamentResource
    {
        return new TournamentResource($tournament);
    }

    public function destroy(Tournament $tournament): \Illuminate\Http\Response
    {
        $this->tournaments->delete($tournament);

        return response()->noContent();
    }
}
