<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MatchResult;
use App\Models\Team;
use App\Models\Tournament;
use Random\IntervalBoundary;
use Random\Randomizer;

/**
 * Simulates a single match between two teams.
 *
 * Algorithm (in plain words):
 *   1. Add luck-driven noise to each team's attack & defense ratings.
 *      Noise amplitude is config('game.luck_amplitude') × noise_range.
 *   2. Compute λ_home = base_goals × home_attack / (home_attack + away_defense).
 *      Mirror for λ_away. λ here is the "expected goals" parameter for Poisson.
 *   3. Sample home/away goals from Poisson(λ) using Knuth's algorithm.
 *   4. Return the score; W/D/L falls out of comparing the two ints.
 *
 * Determinism is delegated to Random\Randomizer:
 *   - Production:    new Randomizer()                 — entropy-seeded
 *   - Tests / MC:    new Randomizer(new Mt19937(42))  — reproducible
 *
 * Each instance owns its own RNG state, so Monte Carlo can spin up its own
 * Randomizer without polluting the simulator that drives "real" matches.
 */
final class MatchSimulatorService
{
    public function __construct(
        private readonly Randomizer $rng = new Randomizer(),
    ) {}

    public function simulate(Tournament $tournament, Team $home, Team $away): MatchResult
    {
        // Step 0 — pull tunables: tournament-specific overrides win,
        // global config/game.php is the fallback (see Tournament::config).
        $baseGoals = (float) $tournament->config('base_goals');
        $homeAdvantage = (int) $tournament->config('home_advantage');
        $luckAmplitude = (float) $tournament->config('luck_amplitude');
        $noiseRange = (int) $tournament->config('noise_range');
        $maxNoise = $luckAmplitude * $noiseRange;

        // Step 1 — effective ratings: base + (home bonus on attack only) + noise.
        // 4 independent noise draws so a team can be "unlucky in attack but
        // lucky in defense" in the same match — more realistic than one draw.
        $homeAttackEff = $home->attack_strength + $homeAdvantage + $this->noise($maxNoise);
        $homeDefenseEff = $home->defense_strength + $this->noise($maxNoise);
        $awayAttackEff = $away->attack_strength + $this->noise($maxNoise);
        $awayDefenseEff = $away->defense_strength + $this->noise($maxNoise);

        // Step 2 — λ derivation. The denominator can theoretically reach 0 if
        // someone configures attack=0/defense=0 with extreme negative noise;
        // max(1, ...) is a defensive lower bound to keep λ > 0.
        $lambdaHome = $baseGoals * ($homeAttackEff / max(1.0, $homeAttackEff + $awayDefenseEff));
        $lambdaAway = $baseGoals * ($awayAttackEff / max(1.0, $awayAttackEff + $homeDefenseEff));

        // Step 3 — Poisson-sample the goal counts.
        return new MatchResult(
            homeGoals: $this->poisson($lambdaHome),
            awayGoals: $this->poisson($lambdaAway),
        );
    }

    /**
     * Uniform noise in [-max, +max]. When max ≤ 0 (luck_amplitude = 0)
     * we short-circuit to 0 so the simulation is fully strength-driven.
     */
    private function noise(float $max): float
    {
        if ($max <= 0.0) {
            return 0.0;
        }

        return $this->rng->getFloat(-$max, $max, IntervalBoundary::ClosedClosed);
    }

    /**
     * Knuth's Poisson sampler: multiply uniform(0,1) draws until the running
     * product drops below e^(-λ); return the iteration count - 1.
     *
     * Cheap for the small λ values football produces (~1..3 → ~3-5 iters).
     */
    private function poisson(float $lambda): int
    {
        if ($lambda <= 0.0) {
            return 0;
        }

        $threshold = exp(-$lambda);
        $k = 0;
        $product = 1.0;

        do {
            $k++;
            // nextFloat() returns float in [0, 1). The 0 case means the
            // product becomes 0 instantly and we exit on the next check —
            // numerically harmless.
            $product *= $this->rng->nextFloat();
        } while ($product > $threshold);

        return $k - 1;
    }
}
