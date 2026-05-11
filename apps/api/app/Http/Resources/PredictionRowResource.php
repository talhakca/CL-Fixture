<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\PredictionRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PredictionRow
 */
final class PredictionRowResource extends JsonResource
{
    public function __construct(PredictionRow $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *   team_id: int,
     *   team_name: string,
     *   championship_probability: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PredictionRow $row */
        $row = $this->resource;

        return [
            'team_id' => $row->teamId,
            'team_name' => $row->teamName,
            'championship_probability' => $row->championshipProbability,
        ];
    }
}
