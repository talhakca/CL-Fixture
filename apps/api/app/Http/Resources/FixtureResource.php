<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fixture
 *
 * Two derived flags help the frontend render without re-deriving:
 *   - is_played: pure existence flag (did this match happen yet?)
 *   - winner_team_id: 0 = draw, otherwise the winning team's id, or null
 *     if the match hasn't been played.
 */
final class FixtureResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   week: int,
     *   home_team: TeamResource,
     *   away_team: TeamResource,
     *   home_team_id: int,
     *   away_team_id: int,
     *   home_goals: int|null,
     *   away_goals: int|null,
     *   is_played: bool,
     *   played_at: string|null,
     *   winner_team_id: int|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week' => $this->week,
            'home_team' => new TeamResource($this->whenLoaded('homeTeam')),
            'away_team' => new TeamResource($this->whenLoaded('awayTeam')),
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'home_goals' => $this->home_goals,
            'away_goals' => $this->away_goals,
            'is_played' => $this->played_at !== null,
            'played_at' => $this->played_at?->toIso8601String(),
            'winner_team_id' => $this->winnerTeamId(),
        ];
    }

    /**
     * 0 = draw, null = not played, else the winning team id.
     */
    private function winnerTeamId(): ?int
    {
        if ($this->played_at === null || $this->home_goals === null || $this->away_goals === null) {
            return null;
        }
        if ($this->home_goals > $this->away_goals) {
            return $this->home_team_id;
        }
        if ($this->home_goals < $this->away_goals) {
            return $this->away_team_id;
        }

        return 0;
    }
}
