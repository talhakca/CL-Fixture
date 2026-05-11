<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tournament;
use App\Repositories\Interfaces\FixtureRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tournament
 *
 * Carries the meta the frontend needs to render the league header and to
 * decide whether the season is finished (`last_played_week === total_weeks`).
 */
final class TournamentResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   settings: array<string, mixed>|null,
     *   started_at: string,
     *   total_weeks: int,
     *   last_played_week: int,
     * }
     */
    public function toArray(Request $request): array
    {
        $fixtures = app(FixtureRepositoryInterface::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'settings' => $this->settings,
            'started_at' => $this->started_at->toIso8601String(),
            'total_weeks' => $fixtures->totalWeeks($this->resource),
            'last_played_week' => $fixtures->lastPlayedWeek($this->resource),
        ];
    }
}
