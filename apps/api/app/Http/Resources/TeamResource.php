<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 *
 * Exposes the public shape of a Team. Strength fields are exposed because
 * the frontend uses them for matchup hints (e.g., "Arsenal's strong defense
 * vs PSG's elite attack").
 */
final class TeamResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   attack_strength: int,
     *   defense_strength: int,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'attack_strength' => $this->attack_strength,
            'defense_strength' => $this->defense_strength,
        ];
    }
}
