<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Prediction;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
final class PredictionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week' => 1,
            'team_id' => Team::factory(),
            'championship_probability' => 0.25,
            'computed_at' => now(),
        ];
    }
}
