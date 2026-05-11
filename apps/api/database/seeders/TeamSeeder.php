<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Static seed data for the 4-team mini league.
 *
 * Hand-picked rankings (set by product, not by real-world stats):
 *   Attack  (high → low): PSG > Bayern > Arsenal > Atletico
 *   Defense (high → low): Arsenal > Atletico > Bayern > PSG
 *
 * → PSG is the goal-scoring machine but leaks at the back.
 * → Arsenal is the rock at the back, scores enough.
 * → Bayern is the all-action attacking team with average defense.
 * → Atletico is the grinder (low scoring, hard to break down).
 *
 * Each pair has a different "matchup story" — PSG vs Arsenal is the league's
 * defining game (best attack vs best defense).
 *
 * Idempotent: re-running the seeder upserts existing rows by name.
 */
final class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'PSG',      'attack_strength' => 92, 'defense_strength' => 68],
            ['name' => 'Bayern',   'attack_strength' => 85, 'defense_strength' => 75],
            ['name' => 'Arsenal',  'attack_strength' => 75, 'defense_strength' => 90],
            ['name' => 'Atletico', 'attack_strength' => 68, 'defense_strength' => 82],
        ];

        foreach ($teams as $team) {
            Team::updateOrCreate(['name' => $team['name']], $team);
        }
    }
}
