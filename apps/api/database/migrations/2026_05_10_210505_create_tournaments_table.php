<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tournament aggregate — owns its fixtures and predictions. A
        // single league simulation is one row here; resetting the season
        // means starting a new tournament, not editing this one.
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            // Per-tournament overrides for game.* config keys. NULL means
            // "use the global config defaults" (config/game.php). When
            // present, the keys mirror config keys: base_goals,
            // home_advantage, luck_amplitude, monte_carlo_iterations,
            // noise_range. Stored as JSON so the schema doesn't churn
            // every time we add a tunable.
            $table->json('settings')->nullable();

            $table->timestamp('started_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
