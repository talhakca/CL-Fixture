<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 *
 * Real teams are seeded statically in DatabaseSeeder. This factory exists
 * for unit tests that need ad-hoc teams with custom strengths — e.g.,
 * asserting simulator behavior with attack=100 vs defense=0.
 */
final class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'attack_strength' => $this->faker->numberBetween(40, 90),
            'defense_strength' => $this->faker->numberBetween(40, 90),
        ];
    }
}
