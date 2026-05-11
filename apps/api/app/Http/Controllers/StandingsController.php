<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\StandingsRowResource;
use App\Models\Tournament;
use App\Services\StandingsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StandingsController extends Controller
{
    public function __construct(
        private readonly StandingsService $standings,
    ) {}

    public function index(Tournament $tournament): AnonymousResourceCollection
    {
        return StandingsRowResource::collection($this->standings->compute($tournament));
    }

    /**
     * Standings AS OF the end of the given week — used by the frontend's
     * WeekProgressBar to replay season state.
     */
    public function show(Tournament $tournament, int $week): AnonymousResourceCollection
    {
        return StandingsRowResource::collection(
            $this->standings->computeAsOfWeek($tournament, $week),
        );
    }
}
