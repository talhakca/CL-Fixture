<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The 4 teams of the league are static, hand-tuned to give the simulator
     * varied dynamics — see TeamSeeder for the rationale on each strength.
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);
    }
}
