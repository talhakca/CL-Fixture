<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\StandingsRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a StandingsRow DTO (not an Eloquent model) so the controller's
 * StandingsService output can flow through the same Resource pipeline.
 *
 * @mixin StandingsRow
 */
final class StandingsRowResource extends JsonResource
{
    /**
     * @param  StandingsRow  $resource
     */
    public function __construct(StandingsRow $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *   team_id: int,
     *   team_name: string,
     *   played: int,
     *   won: int,
     *   drawn: int,
     *   lost: int,
     *   goals_for: int,
     *   goals_against: int,
     *   goal_difference: int,
     *   points: int,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var StandingsRow $row */
        $row = $this->resource;

        return [
            'team_id' => $row->teamId,
            'team_name' => $row->teamName,
            'played' => $row->played,
            'won' => $row->won,
            'drawn' => $row->drawn,
            'lost' => $row->lost,
            'goals_for' => $row->goalsFor,
            'goals_against' => $row->goalsAgainst,
            'goal_difference' => $row->goalDifference,
            'points' => $row->points,
        ];
    }
}
