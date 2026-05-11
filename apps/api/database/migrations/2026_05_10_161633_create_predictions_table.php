<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot of championship probabilities AS OF the end of a given
        // week. Computed once (after play-week / play-all / score-edit /
        // reset) and read freely — no Monte Carlo on every request render.
        Schema::create('predictions', function (Blueprint $table): void {
            $table->id();

            // The week-end this snapshot represents (1..6).
            $table->unsignedTinyInteger('week');

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            // Probability in [0, 1]. Stored as a precise decimal so the
            // four teams' values can sum to exactly 1 without float drift.
            $table->decimal('championship_probability', 6, 5);

            // When this snapshot was computed. Useful for UI ("predictions
            // updated 5 min ago") and for telemetry on stale snapshots.
            $table->timestamp('computed_at')->useCurrent();

            $table->timestamps();

            // One probability per (team, week). If a past match is edited
            // and predictions are recomputed, we upsert on this key.
            $table->unique(['week', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
