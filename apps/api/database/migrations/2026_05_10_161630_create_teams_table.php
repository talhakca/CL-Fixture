<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Teams are static seed data for the 4-team mini league.
        // Strength is split into attack and defense (Dixon-Coles inspired)
        // so the simulator can model "good attack / shaky defense" teams,
        // which yields more realistic high-scoring vs low-scoring matchups.
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();

            // Attack: how well the team converts possession into goals.
            // Defense: how well the team prevents the opponent from scoring.
            // Both on a 0..100 scale to align with noise_range / luck math.
            $table->unsignedSmallInteger('attack_strength');
            $table->unsignedSmallInteger('defense_strength');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
