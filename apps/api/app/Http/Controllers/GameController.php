<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FixtureResource;
use App\Http\Resources\PredictionRowResource;
use App\Http\Resources\StandingsRowResource;
use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use App\Services\GameService;
use App\Services\PredictionService;
use App\Services\StandingsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Mutation endpoints scoped to a tournament:
 *   POST /api/tournaments/{id}/play-week
 *   POST /api/tournaments/{id}/play-all
 *   POST /api/tournaments/{id}/reset-scores
 *
 * Each response carries the updated fixture set so the frontend doesn't
 * need a follow-up GET. play-week additionally returns the standings and
 * prediction snapshots from the week BEFORE play, so the UI can render
 * rank-change arrows without computing the delta from cached state.
 *
 * Domain exceptions (FixtureNotPlayable) self-render their HTTP status
 * code — no try/catch needed.
 */
final class GameController extends Controller
{
    public function __construct(
        private readonly GameService $game,
        private readonly FixtureRepositoryInterface $fixtures,
        private readonly StandingsService $standings,
        private readonly PredictionService $predictions,
    ) {}

    public function playWeek(Tournament $tournament): AnonymousResourceCollection
    {
        $previousWeek = $this->fixtures->lastPlayedWeek($tournament);
        $previousStandings = $previousWeek > 0
            ? $this->standings->computeAsOfWeek($tournament, $previousWeek)
            : [];
        $previousPredictions = $previousWeek > 0
            ? $this->predictions->forWeek($tournament, $previousWeek)
            : [];

        $week = $this->game->playWeek($tournament);

        return FixtureResource::collection($this->fixtures->all($tournament))
            ->additional([
                'meta' => [
                    'week' => $week,
                    'previous_standings' => StandingsRowResource::collection($previousStandings)->resolve(),
                    'previous_predictions' => PredictionRowResource::collection($previousPredictions)->resolve(),
                ],
            ]);
    }

    public function playAll(Tournament $tournament): AnonymousResourceCollection
    {
        $weeks = $this->game->playAll($tournament);

        return FixtureResource::collection($this->fixtures->all($tournament))
            ->additional(['meta' => ['weeks' => $weeks]]);
    }

    public function resetScores(Tournament $tournament): AnonymousResourceCollection
    {
        $this->game->resetScores($tournament);

        return FixtureResource::collection($this->fixtures->all($tournament));
    }
}
