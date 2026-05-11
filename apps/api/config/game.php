<?php

declare(strict_types=1);

/*
 * Tournament & match-simulation tunables.
 *
 * Every magic number used by the simulator lives here so that:
 *   1. The algorithm is auditable in one place.
 *   2. Tests can override values via Config::set() without touching code.
 *   3. Tweaking the league feel (more chaos, fewer goals, etc.) is one edit.
 */
return [
    /*
     * Total goals expected in an evenly-matched game (sum of both teams' λ).
     * Real Champions League average is ~2.7 goals/match. 2.5 is a reasonable
     * starting point; the actual total per match varies based on the attack
     * vs defense balance of the two teams (weak defenses → higher-scoring).
     */
    'base_goals' => 2.5,

    /*
     * Bonus added to the home team's effective ATTACK strength before the
     * λ split. Models the home-advantage effect (familiar pitch, supporters,
     * shorter travel). Applied to attack only — defense is already at home.
     */
    'home_advantage' => 5,

    /*
     * Luck slider in [0, 1]. Multiplied by noise_range to get the maximum
     * absolute strength noise per draw.
     *   0   → only Poisson sampling variance remains.
     *   0.3 → moderate noise (default), underdogs sometimes win.
     *   1   → chaotic: strength almost irrelevant.
     */
    'luck_amplitude' => 0.3,

    /*
     * Maximum absolute strength noise when luck_amplitude = 1. Tuned to the
     * 0..100 strength scale: ±30 lets a 90-strength team drop to ~60 and a
     * 40-strength team climb to ~70 in the most extreme luck draws.
     */
    'noise_range' => 30,

    /*
     * How many full-season simulations Monte Carlo runs to estimate
     * championship probabilities. 10k is fast enough on-demand (<100 ms)
     * for a 4-team / 12-match league and stable to roughly ±1% accuracy.
     */
    'monte_carlo_iterations' => 10000,
];
