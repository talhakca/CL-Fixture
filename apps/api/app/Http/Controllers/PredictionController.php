<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PredictionRowResource;
use App\Models\Tournament;
use App\Services\PredictionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Predictions are persisted week-by-week by GameService. This controller
 * is purely read-side — no Monte Carlo runs at request time.
 */
final class PredictionController extends Controller
{
    public function __construct(
        private readonly PredictionService $predictions,
    ) {}

    /**
     * Latest snapshot. Empty array when nothing has been computed yet.
     */
    public function index(Tournament $tournament): AnonymousResourceCollection
    {
        return PredictionRowResource::collection(
            $this->predictions->latest($tournament) ?? [],
        );
    }

    /**
     * Specific week snapshot.
     */
    public function show(Tournament $tournament, int $week): AnonymousResourceCollection
    {
        $rows = $this->predictions->forWeek($tournament, $week);
        abort_if(empty($rows), 404, "No predictions for week {$week}.");

        return PredictionRowResource::collection($rows);
    }
}
